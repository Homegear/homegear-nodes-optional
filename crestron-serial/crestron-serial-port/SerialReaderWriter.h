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

#ifndef CRESTRON_SERIAL_SERIAL_H_
#define CRESTRON_SERIAL_SERIAL_H_

#include <homegear-node/Output.h>

#include <thread>
#include <atomic>
#include <mutex>
#include <vector>

#include <unistd.h>
#include <fcntl.h>
#include <termios.h>
#include <csignal>

namespace CrestronSerial {
class SerialReaderWriterException : public std::runtime_error {
 public:
  explicit SerialReaderWriterException(const std::string &message) : std::runtime_error(message) {}
  ~SerialReaderWriterException() override = default;
};

class SerialReaderWriter {
 public:
  enum class CharacterSize : tcflag_t {
    Five = CS5,
    Six = CS6,
    Seven = CS7,
    Eight = CS8
  };

  /**
   * Constructor.
   *
   * @param device The device to use (e. g. "/dev/ttyUSB0")
   * @param baudrate The baudrate (e. g. 115200)
   * @param flags Flags passed to the C function "open". 0 should be fine for most cases. "O_NDELAY" is always added by the constructor. By default "O_RDWR | O_NOCTTY | O_NDELAY" is used.
   */
  SerialReaderWriter(const std::shared_ptr<Flows::Output> &output, const std::string &device, int32_t baudrate, int32_t flags);

  /**
   * Destructor.
   */
  ~SerialReaderWriter();

  bool isOpen() const { return _fileDescriptor != -1; }

  /**
   * Opens the serial device.
   *
   * @param evenParity Enable parity checking using an even parity bit.
   * @param oddParity Enable parity checking using an odd parity bit. "evenParity" and "oddParity" are mutually exclusive.
   * @param events Enable events. This starts a thread which calls "lineReceived()" in a derived class for each received packet.
   * @param characterSize Set the character Size.
   * @param twoStopBits Enable two stop bits instead of one.
   */
  void openDevice(bool parity, bool oddParity, bool events = true, CharacterSize characterSize = CharacterSize::Eight, bool twoStopBits = false);

  /**
   * Closes the serial device.
   */
  void closeDevice();

  /**
   * SerialReaderWriter can either be used through events (by implementing ISerialReaderWriterEventSink and usage of addEventHandler) or by polling using this method.
   * @param data The variable to write the returned character into.
   * @param timeout The maximum amount of time to wait in microseconds before the function returns (default: 500000).
   * @return Returns "0" on success, "1" on timeout or "-1" on error.
   */
  int32_t readChar(char &data, uint32_t timeout = 500000);

  /**
   * Writes binary data to the serial device.
   * @param data The data to write. It is written as is without any modification.
   */
  void writeData(const std::vector<uint8_t> &data);

  /**
   * Writes one character to the serial device.
   * @param data The (binary) character to write.
   */
  void writeChar(char data);
 private:
  std::shared_ptr<Flows::Output> _out;
  int32_t _fileDescriptor = -1;
  std::string _device;
  termios _termios{};
  int32_t _baudrate = 0;
  int32_t _flags = 0;

  std::atomic_bool _stop{false};
  std::mutex _sendMutex;

  static bool writeLockFile(int fileDescriptor, bool wait);
};

}
#endif
