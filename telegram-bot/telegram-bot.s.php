<?php
declare (strict_types = 1);

use parallel\{Channel,Runtime,Events,Events\Event};

$telegromBotThread = function(string $scriptId, string $nodeId, string $botId, Channel $homegearChannel)
{
    require('telegram-bot.classes.php'); //Bootstrapping in Runtime constructor does not work for some reason.

    $hg = new \Homegear\Homegear();
    $telegramBot = new TelegramBot($botId);

    if ($hg->registerThread($scriptId) === false)
    {
        $hg->log(2, "telegram-bot: Could not register thread.");
        return;
    }
    if ($botId == "")
    {
        $hg->log(2, "telegram-bot: No Bot id.");
        return;
    }

    $events = new Events();
    $events->addChannel($homegearChannel);
    $events->setTimeout(2000000);

    $telegramBot->getLatestUpdate();

    while (true)
    {
        try
        {
            $result = $telegramBot->getUpdates();
            $hg->log(4, "telegram-bot: $result");
            if ($result != "")
            {
                $json = json_decode($result, false);

                if (isset($json->result[0]))
                {
                    $hg->nodeOutput($nodeId, 0, array('payload' => json_encode($json->result[0]->message)));
                    $telegramBot->setLastUpdateId((int) ($json->result[0]->update_id) + 1);
                }
            }

            $breakLoop = false;
            $event = NULL;
            do
            {
                $event = $events->poll();
                if($event)
                {
                    if($event->source == 'mainHomegearChannelNode'.$nodeId)
                    {
                        $events->addChannel($homegearChannel);
                        if($event->type == Event\Type::Read)
                        {
                            if(is_array($event->value) && count($event->value) > 0)
                            {
                                if($event->value['name'] == 'stop') $breakLoop = true; //Stop
                            }
                        }
                        else if($event->type == Event\Type::Close) $breakLoop = true; //Stop
                    }
                }

                if($breakLoop) break;
            }
            while($event);

            if($breakLoop) break;
        }
        catch(Events\Error\Timeout $ex)
        {
        }
    }
};

class HomegearNode extends HomegearNodeBase
{
    private $hg = NULL;
    private $nodeInfo = NULL;
    private $mainRuntime = NULL;
    private $mainFuture = NULL;
    private $mainHomegearChannel = NULL; //Channel to pass Homegear events to main thread
    
    public function __construct()
    {
        $this->hg = new \Homegear\Homegear();

    }

    public function __destruct()
    {
        $this->stop();
        $this->waitForStop();
    }

    public function init(array $nodeInfo): bool
    {
        $this->nodeInfo = $nodeInfo;
        return true;
    }

    public function start(): bool
    {
        $scriptId = $this->hg->getScriptId();
        $nodeId = $this->nodeInfo['id'];
        $botId = (string)$this->nodeInfo['info']['botId'];

        $this->mainRuntime = new Runtime();
        $this->mainHomegearChannel = Channel::make('mainHomegearChannelNode'.$nodeId, Channel::Infinite);

        global $telegromBotThread;
        $this->mainFuture = $this->mainRuntime->run($telegromBotThread, [$scriptId, $nodeId, $botId, $this->mainHomegearChannel]);

        return true;
    }

    public function stop()
    {
        if($this->mainHomegearChannel) $this->mainHomegearChannel->send(['name' => 'stop', 'value' => true]);
    }

    public function waitForStop()
    {
        if($this->mainFuture)
        {
            $this->mainFuture->value();
            $this->mainFuture = NULL;
        }

        if($this->mainHomegearChannel)
        {
            $this->mainHomegearChannel->close();
            $this->mainHomegearChannel = NULL;
        }

        if($this->mainRuntime)
        {
            $this->mainRuntime->close();
            $this->mainRuntime = NULL;
        }
    }
}
