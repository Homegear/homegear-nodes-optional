<?php
declare (strict_types = 1);

use parallel\{Channel,Runtime,Events,Events\Event};

class Message
{
    public $type;
    public $id;
    public $timestamp;
    public $caller;
    public $callee;
    public $extension;
    public $duration;

    public function copyEndpoints($msg)
    {
        $this->caller = $msg->caller;
        $this->callee = $msg->callee;
    }

    public function setDuration($msg)
    {
        $this->duration = strtotime($this->timestamp) - strtotime($msg->timestamp);
    }
}

$callParser = new class
{
    private $connections = [];

    public function __construct()
    {
    }

    public function parseCallRecord($record)
    {
        $columns = explode(";", $record);
        $timestamp = $columns[0];
        $type = $columns[1];
        $id = $columns[2];
        $msg = new Message();
        $msg->id = $id;
        $msg->timestamp = $timestamp;

        switch ($type)
        {
            case "CALL":
                $msg->type = "OUTBOUND";
                $msg->caller = $columns[4];
                $msg->callee = $columns[5];
                $msg->extension = $columns[3];
                $this->connections[$id] = $msg;
                break;
            case "RING":
                $msg->type = "INBOUND";
                $msg->caller = $columns[3];
                $msg->callee = $columns[4];
                $this->connections[$id] = $msg;
                break;
            case "CONNECT":
                $msg->copyEndpoints($this->connections[$id]);
                $msg->type = "CONNECT";
                $msg->extension = $columns[3];
                $this->connections[$id] = $msg;
                break;
            case "DISCONNECT":
                $cnn = $this->connections[$id];
                $msg->copyEndpoints($cnn);
                switch ($cnn->type)
                {
                    case "INBOUND":
                        $msg->type = "MISSED";
                        break;
                    case "CONNECT":
                        $msg->setDuration($cnn);
                        $msg->extension = $cnn->extension;
                        $msg->type = "DISCONNECT";
                        break;
                    case "OUTBOUND":
                        $msg->type = "UNREACHED";
                        break;
                }
                $this->connections[$id] = null;
                break;
        }

        return json_encode($msg);

    }
};

$callManagerThread = function(string $scriptId, string $nodeId, string $fritzHost, int $fritzPort, Channel $homegearChannel) use (&$callParser)
{
    $hg = new \Homegear\Homegear();

    if ($hg->registerThread($scriptId) === false)
    {
        $hg->log(2, "fritzbox: Could not register thread.");
        return;
    }

    $events = new Events();
    $events->addChannel($homegearChannel);
    $events->setTimeout(100000);

    $fritzboxSocket = @fsockopen($fritzHost, $fritzPort);

    while (true)
    {
        try
        {
            if($fritzboxSocket)
            {
                $events->setTimeout(100000);
                stream_set_timeout($fritzboxSocket, 10);
                $result = fgets($fritzboxSocket);

                if ($result === false)
                {
                    $hg->log(4, "fritzbox: disconnected.");
                    $fritzboxSocket = false;
                }
                else if ($result != "")
                {
                    $hg->log(4, "fritzbox: $result");
                    $payload = $callParser->parseCallRecord($result);
                    $hg->nodeOutput($nodeId, 0, array('payload' => $payload));
                }
            }
            else
            {

                $events->setTimeout(10000000);
                $hg->log(4, "fritzbox: Trying to reconnect.");
                $fritzboxSocket = @fsockopen($fritzHost, $fritzPort);
                if(!$fritzboxSocket) $hg->log(4, "fritzbox: Could not connect.");
                else $hg->log(4, "fritzbox: connected.");
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
    if($fritzboxSocket !== false) fclose($fritzboxSocket);
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
    }
    
    public function init(array $nodeInfo) : bool
    {
        $this->nodeInfo = $nodeInfo;
        return true;
    }
    
    public function start(): bool
    {
        $scriptId = $this->hg->getScriptId();
        $nodeId = $this->nodeInfo['id'];
        $fritzHost = $this->nodeInfo['info']['fritzbox'];
        $fritzPort = intval($this->nodeInfo['info']['port'] ?? 1012);

        $this->mainRuntime = new Runtime();
        $this->mainHomegearChannel = Channel::make('mainHomegearChannelNode'.$this->nodeInfo['id'], Channel::Infinite);

        global $callManagerThread;
        $this->mainFuture = $this->mainRuntime->run($callManagerThread, [$scriptId, $nodeId, $fritzHost, $fritzPort, $this->mainHomegearChannel]);

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
