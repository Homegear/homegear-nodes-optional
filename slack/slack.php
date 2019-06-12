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

    public function input(array $nodeInfoLocal, int $inputIndex, array $message)
    {
        $webhook = (isset($message['slack-webhook']) ? $message['slack-webhook'] : $nodeInfoLocal['info']['webhook']);
        $msg = (isset($nodeInfoLocal) ? $nodeInfoLocal['info']['message'] : '');
        if ($msg == '') { 
            $msg = $message['payload'];
        }
        $result = $this->_sendMessage($webhook,$msg);
		$this->output(0, array('payload' => $msg, 'result' => $result));
    }

    private function _sendMessage($webhook = false,$msg = false)
    {
        if ($webhook == false) { $this->log(4,"slack: Webhook missing!"); return false; }
        if ($msg == false) { $this->log(4,"slack: message missing!"); return false; }
        $ch = curl_init($webhook);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(["text" => $msg]));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
		$result = curl_exec($ch);
        curl_close($ch);
        print_v($result);
		return $result;
    }
}
?>