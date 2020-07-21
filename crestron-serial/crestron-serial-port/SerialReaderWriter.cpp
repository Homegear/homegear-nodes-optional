/* Copyright 2013-2019 Homegear GmbH
 *
 * libhomegear-base is free software: you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * libhomegear-base is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public
 * License along with libhomegear-base.  If not, see
 * <http://www.gnu.org/licenses/>.
 *
 * In addition, as a special exception, the copyright holders give
 * permission to link the code of portions of this program with the
 * OpenSSL library under certain conditions as described in each
 * individual source file, and distribute linked combinations
 * including the two.
 * You must obey the GNU Lesser General Public License in all respects
 * for all of the code used other than OpenSSL.  If you modify
 * file(s) with this exception, you may extend this exception to your
 * version of the file(s), but you are not obligated to do so.  If you
 * do not wish to do so, delete this exception statement from your
 * version.  If you delete this exception statement from all source
 * files in the program, then also delete it here.
*/

#include "SerialReaderWriter.h"

#include <cstring>

namespace CrestronSerial {

SerialReaderWriter::SerialReaderWriter(const std::shared_ptr<Flows::Output> &output, const std::string& device, int32_t baudrate, int32_t flags) {
  _out = output;
  _device = device;
  _baudrate = baudrate;
  _flags = flags;
  if (_flags == 0) _flags = O_RDWR | O_NOCTTY | O_NDELAY;
  else _flags |= O_NDELAY;
}

SerialReaderWriter::~SerialReaderWriter() {
  closeDevice();
}

bool SerialReaderWriter::writeLockFile(int fileDescriptor, bool wait)
{
  struct flock lock{};
  lock.l_type = F_WRLCK;
  lock.l_start = 0;
  lock.l_whence = SEEK_SET;
  lock.l_len = 0;
  return fcntl(fileDescriptor, wait ? F_SETLKW : F_SETLK, &lock) != -1;
}

void SerialReaderWriter::openDevice(bool parity, bool oddParity, bool events, CharacterSize characterSize, bool twoStopBits) {
  if (_fileDescriptor != -1) return;
  _fileDescriptor = open(_device.c_str(), _flags);
  if (_fileDescriptor == -1) throw SerialReaderWriterException("Couldn't open device \"" + _device + "\": " + strerror(errno));

  if (!writeLockFile(_fileDescriptor, false)) {
    throw SerialReaderWriterException("Couldn't open device \"" + _device + "\": Device is locked.");
  }

  tcflag_t baudrate;
  switch (_baudrate) {
    case 50: baudrate = B50;
      break;
    case 75: baudrate = B75;
      break;
    case 110: baudrate = B110;
      break;
    case 134: baudrate = B134;
      break;
    case 150: baudrate = B150;
      break;
    case 200: baudrate = B200;
      break;
    case 300: baudrate = B300;
      break;
    case 600: baudrate = B600;
      break;
    case 1200: baudrate = B1200;
      break;
    case 1800: baudrate = B1800;
      break;
    case 2400: baudrate = B2400;
      break;
    case 4800: baudrate = B4800;
      break;
    case 9600: baudrate = B9600;
      break;
    case 19200: baudrate = B19200;
      break;
    case 38400: baudrate = B38400;
      break;
    case 57600: baudrate = B57600;
      break;
    case 115200: baudrate = B115200;
      break;
    case 230400: baudrate = B230400;
      break;
    case 460800: baudrate = B460800;
      break;
    case 500000: baudrate = B500000;
      break;
    case 576000: baudrate = B576000;
      break;
    case 921600: baudrate = B921600;
      break;
    case 1000000: baudrate = B1000000;
      break;
    case 1152000: baudrate = B1152000;
      break;
    case 1500000: baudrate = B1500000;
      break;
    case 2000000: baudrate = B2000000;
      break;
    case 2500000: baudrate = B2500000;
      break;
    case 3000000: baudrate = B3000000;
      break;
    case 3500000: baudrate = B3500000;
      break;
    case 4000000: baudrate = B4000000;
      break;
    default: throw SerialReaderWriterException("Couldn't setup device \"" + _device + "\": Unsupported baudrate.");
  }
  memset(&_termios, 0, sizeof(termios));
  _termios.c_cflag = baudrate | (tcflag_t)characterSize | CREAD;
  if (parity) _termios.c_cflag |= PARENB;
  if (oddParity) _termios.c_cflag |= PARENB | PARODD;
  if (twoStopBits) _termios.c_cflag |= CSTOPB;
  _termios.c_iflag = 0;
  _termios.c_oflag = 0;
  _termios.c_lflag = 0;
  _termios.c_cc[VMIN] = 1;
  _termios.c_cc[VTIME] = 0;
  cfsetispeed(&_termios, baudrate);
  cfsetospeed(&_termios, baudrate);
  if (tcflush(_fileDescriptor, TCIOFLUSH) == -1) throw SerialReaderWriterException("Couldn't flush device " + _device);
  if (tcsetattr(_fileDescriptor, TCSANOW, &_termios) == -1) throw SerialReaderWriterException("Couldn't set device settings for device " + _device);

  int flags = fcntl(_fileDescriptor, F_GETFL);
  if (!(flags & O_NONBLOCK)) {
    if (fcntl(_fileDescriptor, F_SETFL, flags | O_NONBLOCK) == -1) throw SerialReaderWriterException("Couldn't set device to non blocking mode: " + _device);
  }

  _stop = false;
}

void SerialReaderWriter::closeDevice() {
  _stop = true;
  close(_fileDescriptor);
  _fileDescriptor = -1;
}

int32_t SerialReaderWriter::readChar(char &data, uint32_t timeout) {
  int32_t i = 0;
  fd_set readFileDescriptor;
  while (!_stop) {
    if (_fileDescriptor == -1) {
      return -1;
    }
    FD_ZERO(&readFileDescriptor);
    FD_SET(_fileDescriptor, &readFileDescriptor);
    //Timeout needs to be set every time, so don't put it outside of the while loop
    timeval timeval{};
    timeval.tv_sec = timeout / 1000000;
    timeval.tv_usec = timeout % 1000000;
    i = select(_fileDescriptor + 1, &readFileDescriptor, nullptr, nullptr, &timeval);
    switch (i) {
      case 0: //Timeout
        return 1;
      case 1: break;
      default:
        //Error
        close(_fileDescriptor);
        _fileDescriptor = -1;
        return -1;
    }
    i = read(_fileDescriptor, &data, 1);
    if (i == -1 || i == 0) {
      if (i == -1 && errno == EAGAIN) continue;
      close(_fileDescriptor);
      _fileDescriptor = -1;
      return -1;
    }
    return 0;
  }
  return -1;
}

void SerialReaderWriter::writeData(const std::vector<uint8_t> &data) {
  try {
    if (_fileDescriptor == -1) throw SerialReaderWriterException("Couldn't write to device \"" + _device + "\", because the file descriptor is not valid.");
    if (data.empty()) return;
    int32_t bytesWritten = 0;
    int32_t i;
    std::lock_guard<std::mutex> sendGuard(_sendMutex);
    while (bytesWritten < (signed)data.size()) {
      i = write(_fileDescriptor, (char *)data.data() + bytesWritten, data.size() - bytesWritten);
      if (i == -1) {
        if (errno == EAGAIN) continue;
        throw SerialReaderWriterException("Error writing to serial device \"" + _device + "\" (3, " + std::to_string(errno) + ").");
        return;
      }
      bytesWritten += i;
    }
    tcdrain(_fileDescriptor);
  }
  catch (const std::exception &ex) {
    _out->printEx(__FILE__, __LINE__, __PRETTY_FUNCTION__, ex.what());
  }
}

}
