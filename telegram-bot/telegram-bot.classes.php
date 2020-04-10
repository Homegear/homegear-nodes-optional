<?php
declare (strict_types = 1);

class TelegramBot
{
    private $hg;
    private $botId;
    private $lastUpdateId;

    public function setLastUpdateId(int $value)
    {
    	$this->lastUpdateId = $value;
    }

    public function __construct(string $botId)
    {
        $this->hg = new \Homegear\Homegear();
        $this->lastUpdateId = 0;
        $this->botId = $botId;
    }

    public function getLatestUpdate()
    {
        $result = $this->getUpdates(10);
        $json = json_decode($result, false);

        $id = 0;
        while (isset($json->result[$id]))
        {
            $this->hg->log(4, "telegram-bot: $id");
            $this->lastUpdateId = (int)($json->result[$id]->update_id) + 1;
            $id++;
        }
    }

    public function getUpdates($limit = 1)
    {
        $url = "https://api.telegram.org/bot".$this->botId."/getUpdates?offset=".$this->lastUpdateId."&limit=$limit";

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        $result = curl_exec($ch);
        curl_close($ch);

        return $result;
    }
};