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
        $botId = (isset($message['telegram-botId']) ? $message['telegram-botId'] : $nodeInfoLocal['info']['botId']);
        $chatId = (isset($message['telegram-chatId']) ? $message['telegram-chatId'] : $nodeInfoLocal['info']['chatId']);
        $msg = (isset($nodeInfoLocal) ? $nodeInfoLocal['info']['message'] : '');
        if ($msg == '') { 
            $msg = $message['payload'];
        }
        $this->_sendMessage($botId,$chatId,$msg);
    }

    private function _sendMessage($botId = false, $chatId = false, $msg = false)
    {
        if ($botId == false) { $this->log(4,"telegram: Bot ID missing!"); return; }
        if ($chatId == false) { $this->log(4,"telegram: Chat ID missing!"); return; }
        if ($msg == false) { $this->log(4,"telegram: message missing!"); return; }
        $url = "https://api.telegram.org/bot$botId/sendMessage";
        $params = ['chat_id' => $chatId, 'text' => $msg];
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, ($params));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        $result = curl_exec($ch);
        curl_close($ch);
    }
}
?>