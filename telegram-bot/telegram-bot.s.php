<?php
declare (strict_types = 1);

class SharedData extends Threaded
{
    public $scriptId = 0;
    public $nodeId = "";
    public $botId = "";
    public $lastUpdateId = 0;
    public $stop = false;
    public function run()
    {
    }
}

class TelegramBotThread extends Thread
{
    private $sharedData;
    private $hg;

    public function __construct($sharedData)
    {
        $this->sharedData = $sharedData;
        $this->hg = new \Homegear\Homegear();
        $this->sharedData->lastUpdateId = 0;
    }

    private function _getLatestUpdate()
    {
        $result = $this->_getUpdates(10);
        $json = json_decode($result, false);

        $id = 0;
        while (isset($json->result[$id])) {
            $this->hg->log(4, "telegram-bot: $id");
            $this->sharedData->lastUpdateId = (int) ($json->result[$id]->update_id) + 1;
            $id++;
        }

    }

    private function _getUpdates($limit = 1)
    {
        $botId = $this->sharedData->botId;
        $lastUpdateId = $this->sharedData->lastUpdateId;
        $url = "https://api.telegram.org/bot$botId/getUpdates?offset=$lastUpdateId&limit=$limit";

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        $result = curl_exec($ch);
        curl_close($ch);

        return $result;
    }
    public function run()
    {

        if ($this->hg->registerThread($this->sharedData->scriptId) === false) {
            $this->hg->log(2, "telegram-bot: Could not register thread.");
            return;
        }
        if ($this->sharedData->botId == "") {
            $this->hg->log(2, "telegram-bot: No Bot id.");
            return;
        }

        $this->_getLatestUpdate();

        while (!$this->sharedData->stop) {

            $result = $this->_getUpdates();
            $this->hg->log(4, "telegram-bot: $result");
            if ($result != "") {
                $json = json_decode($result, false);

                if (isset($json->result[0])) {
                    $this->hg->nodeOutput($this->sharedData->nodeId, 0, array('payload' => json_encode($json->result[0]->message)));
                    $this->sharedData->lastUpdateId = (int) ($json->result[0]->update_id) + 1;
                }
            }
            sleep(2);
        }
    }
}
class HomegearNode extends HomegearNodeBase
{
    private $hg = null;
    private $nodeInfo = null;
    private $sharedData = null;
    private $thread = null;
    public function __construct()
    {
        $this->hg = new \Homegear\Homegear();

    }
    public function __destruct()
    {
        $this->stop();
    }
    public function init(array $nodeInfo): bool
    {
        $this->nodeInfo = $nodeInfo;
        return true;
    }
    public function start(): bool
    {
        $this->sharedData = new SharedData();
        $this->sharedData->scriptId = $this->hg->getScriptId();
        $this->sharedData->botId = $this->nodeInfo['info']['botId'];
        $this->sharedData->nodeId = $this->nodeInfo['id'];
        $this->thread = new TelegramBotThread($this->sharedData);
        $this->thread->start();
        return true;
    }
    public function stop()
    {
        if ($this->thread) {
            $this->sharedData->stop = true;
        }

    }
    public function waitForStop()
    {
        if ($this->thread) {
            $this->thread->join();
        }

        $this->thread = null;
    }
}
