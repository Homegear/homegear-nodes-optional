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

#include "CrestronSerialOut.h"

namespace CrestronSerialOut {

CrestronSerialOut::CrestronSerialOut(std::string path, std::string nodeNamespace, std::string type, const std::atomic_bool *frontendConnected) : Flows::INode(path, nodeNamespace, type, frontendConnected) {
  _localRpcMethods.emplace("packetReceived", std::bind(&CrestronSerialOut::packetReceived, this, std::placeholders::_1));
  _localRpcMethods.emplace("setConnectionState", std::bind(&CrestronSerialOut::setConnectionState, this, std::placeholders::_1));
}

CrestronSerialOut::~CrestronSerialOut() {
}

bool CrestronSerialOut::init(Flows::PNodeInfo info) {
  try {
    int32_t inputIndex = -1;

    auto settingsIterator = info->info->structValue->find("serial");
    if (settingsIterator != info->info->structValue->end()) _serial = settingsIterator->second->stringValue;

    settingsIterator = info->info->structValue->find("variables");
    if (settingsIterator != info->info->structValue->end()) {
      for (auto &element : *settingsIterator->second->arrayValue) {
        inputIndex++;

        auto typeIterator = element->structValue->find("vt");
        if (typeIterator == element->structValue->end()) continue;

        auto indexIterator = element->structValue->find("v");
        if (indexIterator == element->structValue->end()) continue;

        int32_t index = Flows::Math::getNumber(indexIterator->second->stringValue) - 1;

        if (index < 0) continue;

        auto variableInfo = std::make_shared<VariableInfo>();
        if (typeIterator->second->type == Flows::VariableType::tInteger || typeIterator->second->type == Flows::VariableType::tInteger64) variableInfo->type = (VariableType)typeIterator->second->integerValue;
        else variableInfo->type = (VariableType)Flows::Math::getNumber(typeIterator->second->stringValue);
        variableInfo->outputIndex = (uint32_t)inputIndex;
        variableInfo->index = (uint32_t)index;
        _variables.emplace(inputIndex, variableInfo);
      }
    }

    return true;
  }
  catch (const std::exception &ex) {
    _out->printEx(__FILE__, __LINE__, __PRETTY_FUNCTION__, ex.what());
  }
  return false;
}

void CrestronSerialOut::configNodesStarted() {
  try {
    if (_serial.empty()) {
      _out->printError("Error: This node has no serial node assigned.");
      return;
    }

    Flows::PArray parameters = std::make_shared<Flows::Array>();
    parameters->reserve(2);
    parameters->push_back(std::make_shared<Flows::Variable>(_id));
    Flows::PVariable variables = std::make_shared<Flows::Variable>(Flows::VariableType::tArray);
    variables->arrayValue->reserve(2);
    parameters->push_back(variables);

    {
      Flows::PVariable element = std::make_shared<Flows::Variable>(Flows::VariableType::tArray);
      element->arrayValue->reserve(2);
      element->arrayValue->push_back(std::make_shared<Flows::Variable>((int32_t)VariableType::kFc));
      element->arrayValue->push_back(std::make_shared<Flows::Variable>(0));
      variables->arrayValue->push_back(element);
    }

    {
      Flows::PVariable element = std::make_shared<Flows::Variable>(Flows::VariableType::tArray);
      element->arrayValue->reserve(2);
      element->arrayValue->push_back(std::make_shared<Flows::Variable>((int32_t)VariableType::kFd));
      element->arrayValue->push_back(std::make_shared<Flows::Variable>(0));
      variables->arrayValue->push_back(element);
    }

    Flows::PVariable result = invokeNodeMethod(_serial, "registerNode", parameters, true);
    if (result->errorStruct) _out->printError("Error: Could not register node: " + result->structValue->at("faultString")->stringValue);
  }
  catch (const std::exception &ex) {
    _out->printEx(__FILE__, __LINE__, __PRETTY_FUNCTION__, ex.what());
  }
  catch (...) {
    _out->printEx(__FILE__, __LINE__, __PRETTY_FUNCTION__);
  }
}

void CrestronSerialOut::input(const Flows::PNodeInfo info, uint32_t index, const Flows::PVariable message) {
  try {
    auto variablesIterator = _variables.find(index);
    if (variablesIterator == _variables.end()) return;

    Flows::PVariable payload = std::make_shared<Flows::Variable>();
    *payload = *(message->structValue->at("payload"));
    if (variablesIterator->second->type == VariableType::kDigital) {
      payload->booleanValue = (bool)(*payload);
      payload->type = Flows::VariableType::tBoolean;

      Flows::PArray parameters = std::make_shared<Flows::Array>();
      parameters->reserve(3);
      parameters->push_back(std::make_shared<Flows::Variable>((int32_t)VariableType::kDigital));
      parameters->push_back(std::make_shared<Flows::Variable>(variablesIterator->second->index));
      parameters->push_back(payload);

      invokeNodeMethod(_serial, "write", parameters, false);
      if (!variablesIterator->second->lastValue || *variablesIterator->second->lastValue != *payload) {
        setNodeData("output" + std::to_string(variablesIterator->second->index), payload);
        variablesIterator->second->lastValue = payload;
      }
    } else if (variablesIterator->second->type == VariableType::kAnalog) {
      Flows::PArray parameters = std::make_shared<Flows::Array>();
      parameters->reserve(3);
      parameters->push_back(std::make_shared<Flows::Variable>((int32_t)VariableType::kAnalog));
      parameters->push_back(std::make_shared<Flows::Variable>(variablesIterator->second->index));
      parameters->push_back(payload);

      invokeNodeMethod(_serial, "write", parameters, false);
      if (!variablesIterator->second->lastValue || *variablesIterator->second->lastValue != *payload) {
        setNodeData("output" + std::to_string(variablesIterator->second->index), payload);
        variablesIterator->second->lastValue = payload;
      }
    } else {
      Flows::PArray parameters = std::make_shared<Flows::Array>();
      parameters->reserve(3);
      parameters->push_back(std::make_shared<Flows::Variable>((int32_t)variablesIterator->second->type));
      parameters->push_back(std::make_shared<Flows::Variable>(variablesIterator->second->index));
      parameters->push_back(std::make_shared<Flows::Variable>(0));

      invokeNodeMethod(_serial, "write", parameters, false);
    }
  }
  catch (const std::exception &ex) {
    _out->printEx(__FILE__, __LINE__, __PRETTY_FUNCTION__, ex.what());
  }
}

//{{{ RPC methods
Flows::PVariable CrestronSerialOut::packetReceived(Flows::PArray parameters) {
  try {
    if (parameters->size() != 3) return Flows::Variable::createError(-1, "Method expects exactly three parameter. " + std::to_string(parameters->size()) + " given.");
    if (parameters->at(0)->type != Flows::VariableType::tInteger && parameters->at(0)->type != Flows::VariableType::tInteger64) return Flows::Variable::createError(-1, "Parameter 1 is not of type integer.");
    if (parameters->at(1)->type != Flows::VariableType::tInteger && parameters->at(1)->type != Flows::VariableType::tInteger64) return Flows::Variable::createError(-1, "Parameter 2 is not of type integer.");
    if (parameters->at(2)->type != Flows::VariableType::tInteger && parameters->at(2)->type != Flows::VariableType::tInteger64 && parameters->at(2)->type != Flows::VariableType::tBoolean)
      return Flows::Variable::createError(-1, "Parameter 3 is not of type integer or boolean.");
    auto type = (VariableType)parameters->at(0)->integerValue;
    if (type == VariableType::kFc) {
      for (auto &variable : _variables) {
        auto parameters2 = std::make_shared<Flows::Array>();
        parameters2->reserve(2);
        parameters2->push_back(std::make_shared<Flows::Variable>(_id));
        parameters2->push_back(std::make_shared<Flows::Variable>("output" + std::to_string(variable.second->index)));

        invoke("deleteNodeData", parameters2);
        variable.second->lastValue.reset();
      }
    } else if (type == VariableType::kFd) {
      for (auto &variable : _variables) {
        if (variable.second->type != VariableType::kDigital && variable.second->type != VariableType::kAnalog) continue;
        if (!variable.second->lastValue) variable.second->lastValue = getNodeData("output" + std::to_string(variable.second->index));
        if (!variable.second->lastValue) {
          if (variable.second->type == VariableType::kDigital) variable.second->lastValue = std::make_shared<Flows::Variable>(false);
          else variable.second->lastValue = std::make_shared<Flows::Variable>(0);
        }

        auto parameters2 = std::make_shared<Flows::Array>();
        parameters2->reserve(3);
        parameters2->push_back(std::make_shared<Flows::Variable>((int32_t)variable.second->type));
        parameters2->push_back(std::make_shared<Flows::Variable>(variable.second->index));
        parameters2->push_back(variable.second->lastValue);

        invokeNodeMethod(_serial, "write", parameters2, false);
      }
    }

    return std::make_shared<Flows::Variable>();
  }
  catch (const std::exception &ex) {
    _out->printEx(__FILE__, __LINE__, __PRETTY_FUNCTION__, ex.what());
  }
  catch (...) {
    _out->printEx(__FILE__, __LINE__, __PRETTY_FUNCTION__);
  }
  return Flows::Variable::createError(-32500, "Unknown application error.");
}

Flows::PVariable CrestronSerialOut::setConnectionState(Flows::PArray parameters) {
  try {
    if (parameters->size() != 1) return Flows::Variable::createError(-1, "Method expects exactly one parameter. " + std::to_string(parameters->size()) + " given.");
    if (parameters->at(0)->type != Flows::VariableType::tBoolean) return Flows::Variable::createError(-1, "Parameter is not of type boolean.");

    Flows::PVariable status = std::make_shared<Flows::Variable>(Flows::VariableType::tStruct);
    if (parameters->at(0)->booleanValue) {
      status->structValue->emplace("text", std::make_shared<Flows::Variable>("connected"));
      status->structValue->emplace("fill", std::make_shared<Flows::Variable>("green"));
      status->structValue->emplace("shape", std::make_shared<Flows::Variable>("dot"));
    } else {
      status->structValue->emplace("text", std::make_shared<Flows::Variable>("disconnected"));
      status->structValue->emplace("fill", std::make_shared<Flows::Variable>("red"));
      status->structValue->emplace("shape", std::make_shared<Flows::Variable>("dot"));
    }
    nodeEvent("statusBottom/" + _id, status);

    return std::make_shared<Flows::Variable>();
  }
  catch (const std::exception &ex) {
    _out->printEx(__FILE__, __LINE__, __PRETTY_FUNCTION__, ex.what());
  }
  return Flows::Variable::createError(-32500, "Unknown application error.");
}
//}}}

}

