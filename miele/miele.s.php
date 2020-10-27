<?php
declare(strict_types=1);

use parallel\{Channel,Runtime,Events,Events\Event};

$restThread = function(string $scriptId, string $nodeId, array $mieleSettings, Channel $homegearChannel)
{
    require('miele.classes.php'); //Bootstrapping in Runtime constructor does not work for some reason.

    $hg = new \Homegear\Homegear();
    if($hg->registerThread($scriptId) === false)
    {
        $hg->log(2, "Could not register thread.");
        return;
    }
    
    $settings = new MieleSettings();
    $settings->userId = $mieleSettings['userId'];
    $settings->password = $mieleSettings['password'];
    $settings->clientId = $mieleSettings['clientId'];
    $settings->clientSecret = $mieleSettings['clientSecret'];
    $settings->country = $mieleSettings['country'];
    $settings->language = $mieleSettings['language'];

    $mieleRest = new MieleRest($settings);

    $events = new Events();
    $events->addChannel($homegearChannel);
    $events->setTimeout(1000000);

    $i = 0;
    $nextTo = rand(50, 70);

    while(true)
    {
        try
        {
            if($i < $nextTo)
            {
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
            }

            if($breakLoop) break;

            if($i < $nextTo) continue;
            $i = 0;
            $nextTo = rand(50, 70);

            $devices = $mieleRest->getDevices();
            if($devices !== false)
            {
                $mieleRest->processDevices($devices);
            }
        }
        catch(Events\Error\Timeout $ex)
        {
            $i++;
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

    function __construct()
    {
        $this->hg = new \Homegear\Homegear();
    }

    function __destruct()
    {
        $this->stop();
        $this->waitForStop();
    }

    public function init(array $nodeInfo) : bool
    {
        $this->nodeInfo = $nodeInfo;
        return true;
    }

    public function start() : bool
    {
        $scriptId = $this->hg->getScriptId();
        $nodeId = $this->nodeInfo['id'];
        
        $mieleSettings = array();
        $mieleSettings['userId'] = $this->nodeInfo['info']['userid'];
        $mieleSettings['password'] = $this->getNodeData('user-password');
        $mieleSettings['clientId'] = $this->nodeInfo['info']['clientid'];
        $mieleSettings['clientSecret'] = $this->getNodeData('clientsecret-password');
        $mieleSettings['country'] = $this->nodeInfo['info']['country'];
        $mieleSettings['language'] = $this->nodeInfo['info']['language'];

        $this->mainRuntime = new Runtime();
        $this->mainHomegearChannel = Channel::make('mainHomegearChannelNode'.$nodeId, Channel::Infinite);

        global $restThread;
        $this->mainFuture = $this->mainRuntime->run($restThread, [$scriptId, $nodeId, $mieleSettings, $this->mainHomegearChannel]);

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
