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

    public function input(array $nodeInfoLocal, int $inputIndex, array $message)
    {
        $server = (isset($message['payload']['server']) ? $message['payload']['server'] : $nodeInfoLocal['info']['server']);
        $port = (isset($message['payload']['port']) ? $message['payload']['port'] : $nodeInfoLocal['info']['port']);
        $player = (isset($message['payload']['player']) ? $message['payload']['player'] : $nodeInfoLocal['info']['player']);

        $p0 = (isset($message['payload']['p0']) ? $message['payload']['p0'] : $nodeInfoLocal['info']['p0']);
        $p1 = (isset($message['payload']['p1']) ? $message['payload']['p1'] : $nodeInfoLocal['info']['p1']);
        $p2 = (isset($message['payload']['p2']) ? $message['payload']['p2'] : $nodeInfoLocal['info']['p2']);
        $p3 = (isset($message['payload']['p3']) ? $message['payload']['p3'] : $nodeInfoLocal['info']['p3']);
        $p4 = (isset($message['payload']['p4']) ? $message['payload']['p4'] : $nodeInfoLocal['info']['p4']);

        $this->_sendMessage($server, $port, $player, $p0, $p1, $p2, $p3, $p4);
    }
    // GET request
    private function _sendMessage($server = false, $port = false, $player = false, $p0, $p1, $p2, $p3, $p4)
    {
        if ($server == false) {$this->log(4, "squeezebox: server missing!");return;}
        if ($port == false) {$this->log(4, "squeezebox: port missing!");return;}
        if ($player == false) {$this->log(4, "squeezebox: player missing!");return;}

        //GET Request
        $url = "http://$server:$port/status.html?p0=$p0&p1=$p1&p2=$p2&p3=$p3&p4=$p4&player=$player";
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HEADER, false);
        $result = curl_exec($ch);
        curl_close($ch);

        $this->output(0, array('payload' => $result));
    }

}
