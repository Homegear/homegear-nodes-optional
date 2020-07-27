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

#ifndef CRESTRON_SERIAL_IN_H_
#define CRESTRON_SERIAL_IN_H_

#include <homegear-node/INode.h>
#include <thread>
#include <mutex>
#include <queue>
#include <unordered_map>

namespace CrestronSerialIn
{

class CrestronSerialIn: public Flows::INode
{
 public:
  CrestronSerialIn(std::string path, std::string nodeNamespace, std::string type, const std::atomic_bool* frontendConnected);
  ~CrestronSerialIn() override;

  bool init(Flows::PNodeInfo info) override;
  void configNodesStarted() override;
 private:
  enum class VariableType {
    kDigital = 0,
    kAnalog = 1,
    kFc = 2,
    kFd = 3
  };

  struct VariableInfo
  {
    VariableType type = VariableType::kDigital;
    uint32_t outputIndex = 0;
    uint32_t index = 0;
    std::vector<uint8_t> lastValue;
  };

  std::string _serial;
  uint32_t _outputs = 0;
  std::unordered_map<uint32_t, std::shared_ptr<VariableInfo>> _digital;
  std::unordered_map<uint32_t, std::shared_ptr<VariableInfo>> _analog;

  //{{{ RPC methods
  Flows::PVariable packetReceived(Flows::PArray parameters);
  Flows::PVariable setConnectionState(Flows::PArray parameters);
  //}}}
};

}

#endif
