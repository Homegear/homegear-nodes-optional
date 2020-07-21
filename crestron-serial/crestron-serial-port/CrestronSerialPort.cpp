/* Copyright 2013-2019 Homegear GmbH
 *
 * Homegear is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Homegear is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Homegear.  If not, see <http://www.gnu.org/licenses/>.
 *
 * In addition, as a special exception, the copyright holders give
 * permission to link the code of portions of this program with the
 * OpenSSL library under certain conditions as described in each
 * individual source file, and distribute linked combinations
 * including the two.
 * You must obey the GNU General Public License in all respects
 * for all of the code used other than OpenSSL.  If you modify
 * file(s) with this exception, you may extend this exception to your
 * version of the file(s), but you are not obligated to do so.  If you
 * do not wish to do so, delete this exception statement from your
 * version.  If you delete this exception statement from all source
 * files in the program, then also delete it here.
 */

#include "CrestronSerialPort.h"

namespace CrestronSerial {

CrestronSerialPort::CrestronSerialPort(std::string path, std::string nodeNamespace, std::string type, const std::atomic_bool *frontendConnected) : Flows::INode(path, nodeNamespace, type, frontendConnected) {
  _stopThread = false;

  _localRpcMethods.emplace("registerNode", std::bind(&CrestronSerialPort::registerNode, this, std::placeholders::_1));
  _localRpcMethods.emplace("write", std::bind(&CrestronSerialPort::write, this, std::placeholders::_1));
}

CrestronSerialPort::~CrestronSerialPort() {
}

bool CrestronSerialPort::init(Flows::PNodeInfo info) {
  try {
    _nodeInfo = info;
    return true;
  }
  catch (const std::exception &ex) {
    _out->printEx(__FILE__, __LINE__, __PRETTY_FUNCTION__, ex.what());
  }
  return false;
}

bool CrestronSerialPort::start() {
  try {
    auto settingsIterator = _nodeInfo->info->structValue->find("serialport");
    if (settingsIterator != _nodeInfo->info->structValue->end()) _serialPort = settingsIterator->second->stringValue;

    if (_serialPort.empty()) {
      _out->printError("Error: No serial device specified.");
      return false;
    }

    settingsIterator = _nodeInfo->info->structValue->find("serialbaud");
    if (settingsIterator != _nodeInfo->info->structValue->end()) _baudRate = Flows::Math::getNumber(settingsIterator->second->stringValue);

    if (_baudRate <= 0) {
      _out->printError("Error: Invalid baudrate specified.");
      return false;
    }

    settingsIterator = _nodeInfo->info->structValue->find("databits");
    if (settingsIterator != _nodeInfo->info->structValue->end()) {
      int32_t bits = Flows::Math::getNumber(settingsIterator->second->stringValue);

      if (bits == 8) _dataBits = SerialReaderWriter::CharacterSize::Eight;
      else if (bits == 7) _dataBits = SerialReaderWriter::CharacterSize::Seven;
      else if (bits == 6) _dataBits = SerialReaderWriter::CharacterSize::Six;
      else if (bits == 5) _dataBits = SerialReaderWriter::CharacterSize::Five;
      else {
        _out->printError("Error: Invalid character size specified.");
        return false;
      }
    }

    settingsIterator = _nodeInfo->info->structValue->find("parity");
    if (settingsIterator != _nodeInfo->info->structValue->end()) {
      _evenParity = false;
      _oddParity = false;
      _evenParity = (settingsIterator->second->stringValue == "even");
      _oddParity = (settingsIterator->second->stringValue == "odd");
    }

    settingsIterator = _nodeInfo->info->structValue->find("stopbits");
    if (settingsIterator != _nodeInfo->info->structValue->end()) _stopBits = Flows::Math::getNumber(settingsIterator->second->stringValue);

    _serial = std::make_shared<SerialReaderWriter>(_out, _serialPort, _baudRate, 0);
    reopen();

    _stopThread = false;
    _readThread = std::thread(&CrestronSerialPort::listenThread, this);

    return true;
  }
  catch (const std::exception &ex) {
    _out->printEx(__FILE__, __LINE__, __PRETTY_FUNCTION__, ex.what());
  }
  return false;
}

void CrestronSerialPort::stop() {
  try {
    _stopThread = true;
  }
  catch (const std::exception &ex) {
    _out->printEx(__FILE__, __LINE__, __PRETTY_FUNCTION__, ex.what());
  }
}

void CrestronSerialPort::waitForStop() {
  try {
    _stopThread = true;
    if(_readThread.joinable()) _readThread.join();
  }
  catch (const std::exception &ex) {
    _out->printEx(__FILE__, __LINE__, __PRETTY_FUNCTION__, ex.what());
  }
}

Flows::PVariable CrestronSerialPort::getConfigParameterIncoming(std::string name) {
  try {
    auto settingsIterator = _nodeInfo->info->structValue->find(name);
    if (settingsIterator != _nodeInfo->info->structValue->end()) return settingsIterator->second;
  }
  catch (const std::exception &ex) {
    _out->printEx(__FILE__, __LINE__, __PRETTY_FUNCTION__, ex.what());
  }
  return std::make_shared<Flows::Variable>();
}

void CrestronSerialPort::reopen() {
  try {
    _serial->closeDevice();
    std::this_thread::sleep_for(std::chrono::milliseconds(1000));
    _out->printInfo("Opening serial device " + _serialPort);
    _serial->openDevice(_evenParity, _oddParity, false, _dataBits, _stopBits == 2);
    _out->printInfo("Serial device opened.");
  }
  catch (const std::exception &ex) {
    _out->printEx(__FILE__, __LINE__, __PRETTY_FUNCTION__, ex.what());
  }
}

void CrestronSerialPort::packetReceived(VariableType type, uint32_t index, const Flows::PVariable &value) {
  try {
    Flows::PArray parameters = std::make_shared<Flows::Array>();
    parameters->reserve(3);
    parameters->push_back(std::make_shared<Flows::Variable>((int32_t)type));
    parameters->push_back(std::make_shared<Flows::Variable>(index));
    parameters->push_back(value);
    std::lock_guard<std::mutex> nodesGuard(_nodesMutex);
    for (auto &node : _nodes) {
      auto typeIterator = node.second.find(type);
      if (typeIterator != node.second.end()) {
        auto indexIterator = typeIterator->second.find(index);
        if (indexIterator != typeIterator->second.end()) invokeNodeMethod(node.first, "packetReceived", parameters, false);
      }
    }
  }
  catch (const std::exception &ex) {
    _out->printEx(__FILE__, __LINE__, __PRETTY_FUNCTION__, ex.what());
  }
}

void CrestronSerialPort::listenThread() {
  int32_t readBytes = 0;
  std::vector<char> buffer;
  buffer.reserve(4);
  char data;
  while (!_stopThread) {
    try {
      readBytes = _serial->readChar(data);
      if (readBytes == 0) {
        if (buffer.empty() && !((uint8_t)data & 0x80u)) continue; //Only the start byte has the first bit set
        buffer.push_back(data);
        bool isAnalogPacket = ((uint8_t)buffer.at(0) & 0xC0u) == 0xC0u;
        if ((isAnalogPacket && buffer.size() == 4) || (!isAnalogPacket && buffer.size() == 2)) {
          if (isAnalogPacket) {
            uint32_t index = (uint32_t)((uint32_t)((uint8_t)buffer.at(0) & 0x07u) << 7u) | ((uint8_t)buffer.at(1) & 0x7Fu);
            uint32_t value = (uint32_t)((uint32_t)((uint8_t)buffer.at(0) & 0x30u) << 10u) | ((uint32_t)((uint8_t)buffer.at(2) & 0x7Fu) << 7u) | ((uint8_t)buffer.at(3) & 0x7Fu);
            packetReceived(VariableType::kAnalog, index, std::make_shared<Flows::Variable>(value));
          } else {
            uint32_t index = (((uint32_t)((uint8_t)buffer.at(0) & 0x1Fu) << 7u) | ((uint8_t)buffer.at(1) & 0x7Fu)) - 1024;
            bool value = !((uint8_t)buffer.at(0) & 0x20u);
            packetReceived(VariableType::kDigital, index, std::make_shared<Flows::Variable>(value));
          }
          buffer.clear();
        }
      } else if (readBytes == -1) reopen();
    }
    catch (const std::exception &ex) {
      _out->printEx(__FILE__, __LINE__, __PRETTY_FUNCTION__, ex.what());
    }
  }
}

//{{{ RPC methods
Flows::PVariable CrestronSerialPort::registerNode(Flows::PArray parameters) {
  try {
    if (parameters->size() != 2) return Flows::Variable::createError(-1, "Method expects exactly one parameter. " + std::to_string(parameters->size()) + " given.");
    if (parameters->at(0)->type != Flows::VariableType::tString || parameters->at(0)->stringValue.empty()) return Flows::Variable::createError(-1, "Parameter is not of type string.");
    if (parameters->at(1)->type != Flows::VariableType::tArray) return Flows::Variable::createError(-1, "Parameter 2 is not of type array.");

    for (auto &entry : *parameters->at(1)->arrayValue) {
      auto type = (VariableType)entry->arrayValue->at(0)->integerValue64;
      auto index = entry->arrayValue->at(1)->integerValue64;

      std::lock_guard<std::mutex> nodesGuard(_nodesMutex);
      _nodes[parameters->at(0)->stringValue][type].emplace(index);
    }

    return std::make_shared<Flows::Variable>();
  }
  catch (const std::exception &ex) {
    _out->printEx(__FILE__, __LINE__, __PRETTY_FUNCTION__, ex.what());
  }
  return Flows::Variable::createError(-32500, "Unknown application error.");
}

Flows::PVariable CrestronSerialPort::write(Flows::PArray parameters) {
  try {
    if (parameters->size() != 3) return Flows::Variable::createError(-1, "Method expects exactly three parameters.");
    if (parameters->at(0)->type != Flows::VariableType::tInteger && parameters->at(0)->type != Flows::VariableType::tInteger64) return Flows::Variable::createError(-1, "Parameter is not of type integer.");
    if (parameters->at(1)->type != Flows::VariableType::tInteger && parameters->at(1)->type != Flows::VariableType::tInteger64) return Flows::Variable::createError(-1, "Parameter 2 is not of type integer.");
    if (parameters->at(2)->type != Flows::VariableType::tInteger && parameters->at(2)->type != Flows::VariableType::tInteger64 && parameters->at(2)->type != Flows::VariableType::tBoolean) return Flows::Variable::createError(-1, "Parameter 3 is not of type integer or boolean.");

    auto type = (VariableType)parameters->at(0)->integerValue64;
    auto index = (uint16_t)(parameters->at(1)->integerValue64 + 1024);

    if(type == VariableType::kDigital) {
      std::vector<uint8_t> buffer;
      buffer.resize(2, 0);
      buffer.at(0) |= 0x80u;
      if(!parameters->at(2)->booleanValue) buffer.at(0) |= 0x20u;
      buffer.at(0) |= ((uint8_t)(index >> 7u) & 0x1Fu);
      buffer.at(1) = (index & 0x7Fu);
      _serial->writeData(buffer);
    } else if(type == VariableType::kAnalog) {
      auto value = (uint16_t)parameters->at(2)->integerValue64;
      std::vector<uint8_t> buffer;
      buffer.resize(4, 0);
      buffer.at(0) |= 0xC0u;
      buffer.at(0) |= ((uint8_t)(value >> 10u) & 0x30u);
      buffer.at(0) |= ((uint8_t)(index >> 7u) & 0x07u);
      buffer.at(1) = (index & 0x7Fu);
      buffer.at(2) = ((uint8_t)(value >> 7u) & 0x7Fu);
      buffer.at(3) = (value & 0x7Fu);
      _serial->writeData(buffer);
    } else if(type == VariableType::kFc) {
      _serial->writeData(std::vector<uint8_t>{0xFCu});
    } else if(type == VariableType::kFd) {
      _serial->writeData(std::vector<uint8_t>{0xFDu});
    }

    return std::make_shared<Flows::Variable>();
  }
  catch (const std::exception &ex) {
    _out->printEx(__FILE__, __LINE__, __PRETTY_FUNCTION__, ex.what());
  }
  return Flows::Variable::createError(-32500, "Unknown application error.");
}
//}}}

}
