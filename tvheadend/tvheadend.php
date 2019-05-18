<?php
declare(strict_types=1);

class HomegearNode extends HomegearNodeBase
{
    private $hg = NULL;
    private $nodeInfo = NULL;

    public function __construct()
    {
	    $this->hg = new \Homegear\Homegear();
    }

    private function _getChannelUUID($server = false, $port = false, $userpasswd = false,$channel = false)
    {
        if ($server == false) { $this->log(4,"tvheadend: server missing!"); return; }
        if ($port == false) { $this->log(4,"tvheadend: port missing!"); return; }
        
        if ($channel == false) { $this->log(4,"tvheadend: channel missing!"); return; }

        $url = "http://$userpasswd$server:$port/api/channel/list";
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_POST, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        
        $result = curl_exec($ch);
        curl_close($ch); 

        $channels = json_decode($result, true);

        foreach ($channels['entries'] as $entry) 
        {
            if($entry['val'] == $channel) 
            {
                $channelUUID = $entry['key'];
            }
        }

        return $channelUUID;
    }
    public function input(array $nodeInfoLocal, int $inputIndex, array $message)
    {
        $server = (isset($message['payload']['server']) ? $message['payload']['server'] : $nodeInfoLocal['info']['server']);
        $port = (isset($message['payload']['port']) ? $message['payload']['port'] : $nodeInfoLocal['info']['port']);
        $user = (isset($message['payload']['user']) ? $message['payload']['user'] : $nodeInfoLocal['info']['user']);
        $password = (isset($message['payload']['password']) ? $message['payload']['password'] : $nodeInfoLocal['info']['password']);

        if($user!="" && $password!="" ) 
        {
            $userpasswd = $user . ":" . $password . "@";
        }
        else
        {
            $userpasswd = "";
        }

        $channel = (isset($message['payload']['channel']) ? $message['payload']['channel'] : $nodeInfoLocal['info']['channel']);
        $config = (isset($message['payload']['config']) ? $message['payload']['config'] : $nodeInfoLocal['info']['config']);
        $duration = (isset($message['payload']['duration']) ? $message['payload']['duration'] : $nodeInfoLocal['info']['duration']);
        
        $channelUUID = $this->_getChannelUUID($server,$port,$userpasswd,$channel);

        $this->_sendMessage($server,$port,$userpasswd,$channel,$channelUUID,$config,$duration);
    }

    private function _sendMessage($server = false, $port = false, $userpasswd = false,$channel = false,$channelUUID = false ,$config ="",$duration = 300)
    {
        if ($server == false) { $this->log(4,"tvheadend: server missing!"); return; }
        if ($port == false) { $this->log(4,"tvheadend: port missing!"); return; }
        
        if ($channelUUID == false) { $this->log(4,"tvheadend: channel missing!"); return; }
        if ($duration <= 30) { $this->log(4,"tvheadend: duration too short!"); return; }
        $start=time();
        $stop=$start + $duration;

        // $laststop=getNodeData("laststop");
        // if ($laststop==null) {
        //     $laststop=$stop-60;
        // }   
        
        // if ($laststop<$start) {           
            $conf=urlencode('{
            "disp_title":"' . $channel . '",
            "start":' . $start . ',
            "start_extra":0,
            "stop":' . $stop . ',
            "stop_extra":0,
            "channel":"' . $channelUUID . '",
            "config_name":"' . $config .'",
            "comment":""}');
        // }

        $url = "http://$userpasswd$server:$port/api/dvr/entry/create?conf=$conf";
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HEADER, false);
        $result = curl_exec($ch);
        curl_close($ch); 

        $this->output(0, array('payload' => $result));
    }

}
?>