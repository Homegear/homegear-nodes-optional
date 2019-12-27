<?php
declare (strict_types = 1);

class HomegearNode extends HomegearNodeBase
{
    private $hg = null;
    private $nodeInfo = null;

    public function __construct()
    {
        $this->hg = new \Homegear\Homegear();
    }
    private function _getChannelUUID($connection = false, $channel = false)
    {
        if ($connection == false) {$this->log(4, "tvheadend: connection missing!");return;}

        if ($channel == false) {$this->log(4, "tvheadend: channel missing!");return;}

        $url = "$connection/api/channel/list";

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_POST, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

        $result = curl_exec($ch);
        curl_close($ch);

        $channels = json_decode($result, true);

        foreach ($channels['entries'] as $entry) {
            if ($entry['val'] == $channel) {
                $channelUUID = $entry['key'];
            }
        }

        return $channelUUID;
    }
    private function _getConfigUUID($connection = false, $config = false)
    {
        if ($config == false) {return;}

        if ($connection == false) {$this->log(4, "tvheadend: connection missing!");return;}

        $url = "$connection/api/dvr/config/grid";

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_POST, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

        $result = curl_exec($ch);
        curl_close($ch);

        $configs = json_decode($result, true);

        $configUUID = $config;
        foreach ($configs['entries'] as $entry) {
            if ($entry['name'] == $config) {
                $configUUID = $entry['uuid'];
            }
        }

        return $configUUID;
    }
    private function _getCurrentEndtime($connection = false, $channel = false)
    {
        if ($connection == "") {$this->log(4, "tvheadend: connection missing!");return;}

        if ($channel == false) {$this->log(4, "tvheadend: channel missing!");return;}

        $url = "$connection/api/dvr/entry/grid_upcoming?limit=300";

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_POST, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

        $result = curl_exec($ch);
        curl_close($ch);

        $recordings = json_decode($result, true);

        $currentstop = 0;
        foreach ($recordings['entries'] as $entry) {
            if ($entry['channel'] == $channel && $entry['status'] == "Running") {
                if ($currentstop < $entry['stop_real']) {
                    $currentstop = $entry['stop_real'];
                }

            }
        }

        return $currentstop;
    }
    public function _getConnection($server, $port, $user, $password)
    {
        if ($user != "" && $password != "") {
            $userpasswd = $user . ":" . $password . "@";
        } else {
            $userpasswd = "";
        }
        $connection = "http://$userpasswd$server:$port";

        return $connection;
    }

    public function input(array $nodeInfoLocal, int $inputIndex, array $message)
    {
        $server = (isset($message['payload']['server']) ? $message['payload']['server'] : $nodeInfoLocal['info']['server']);
        $port = (isset($message['payload']['port']) ? $message['payload']['port'] : $nodeInfoLocal['info']['port']);
        $user = (isset($message['payload']['user']) ? $message['payload']['user'] : $nodeInfoLocal['info']['user']);
        $password = (isset($message['payload']['password']) ? $message['payload']['password'] : $nodeInfoLocal['info']['password']);
        $connection = $this->_getConnection($server, $port, $user, $password);

        $channel = (isset($message['payload']['channel']) ? $message['payload']['channel'] : $nodeInfoLocal['info']['channel']);
        $channelUUID = $this->_getChannelUUID($connection, $channel);

        $config = (isset($message['payload']['config']) ? $message['payload']['config'] : $nodeInfoLocal['info']['config']);
        $configUUID = $this->_getConfigUUID($connection, $config);

        $duration = (isset($message['payload']['duration']) ? $message['payload']['duration'] : $nodeInfoLocal['info']['duration']);

        $this->_startRecording($connection, $channel, $channelUUID, $configUUID, $duration);
    }

    private function _startRecording($connection = false, $channel = false, $channelUUID = false, $config = "", $duration = 300)
    {
        if ($connection == "") {$this->log(4, "tvheadend: connection missing!");return;}

        if ($channelUUID == false) {$this->log(4, "tvheadend: channel missing!");return;}
        if ($duration <= 30) {$this->log(4, "tvheadend: duration too short!");return;}

        $start = time();
        $stop = $start + $duration;

        $current_stop = $this->_getCurrentEndtime($connection, $channelUUID);

        if ($current_stop - 60 <= $start) {
            $conf = urlencode('{
            "disp_title":"' . $channel . '",
            "start":' . $start . ',
            "start_extra":0,
            "stop":' . $stop . ',
            "stop_extra":0,
            "channel":"' . $channelUUID . '",
            "config_name":"' . $config . '",
            "comment":""}');

            $url = "$connection/api/dvr/entry/create?conf=$conf";
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_HEADER, false);
            $result = curl_exec($ch);
            curl_close($ch);

            $this->endtimes[$channel] = $stop - 60;
        } else {
            $result = false;
        }

        $this->output(0, array('payload' => $result));
    }

}
