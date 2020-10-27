<?php
declare(strict_types=1);

class HomegearNode extends HomegearNodeBase
{
    private $hg = null;
    private $nodeInfo = null;

    public function __construct()
    {
        $this->hg = new \Homegear\Homegear();
    }

    function __destruct()
    {
        //$this->nodeInfo
    }

    //Init is called when the node is created directly after the constructor


    public function init(array $nodeInfo): bool
    {
        $this->nodeInfo = $nodeInfo;
        return true;
    }


    public function start(): bool
    {
        return true; //True means: "start" was successful
    }

    public function configNodesStarted()
    {
        /**
         * @param $nodeId string nodeId
         * @param $method string methodName
         * @param $parameters array parameter list
         * @param $wait bool waitForReturn (default true)
         */
        $this->invokeNodeMethod($this->nodeInfo['info']['server'], 'registerNode', [$this->nodeInfo], true);


    }

    public function startUpComplete()
    {
    }


    public function input(array $nodeInfoLocal, int $inputIndex, array $message)
    {
        $nodeId = $nodeInfoLocal['id'];
        $configNodeId = $this->nodeInfo['info']['server'];

        try {

            if (empty($message['topic'])) {
                HomegearNodeBase::log(2, 'Empty topic, thus no query');
                throw new \RuntimeException('empty query');
            }

            if (empty($message['payload'])) {
                $message['payload'] = [];
            }

            if ((strpos($message['topic'], '?') !== false) && empty($message['payload'])) {
                HomegearNodeBase::log(2, 'no data for placeholders in query');
                throw new \RuntimeException('not enough data');
            }


            if (!is_array($message['payload']) && !empty($message['payload'])) {
                $message['payload'] = [$message['payload']];
            }


            HomegearNodeBase::nodeEvent('statusBottom/' . $nodeId,
                ['text' => 'query sent', 'fill' => 'green', 'shape' => 'ring']);


            $message['payload'] = $this->invokeNodeMethod(
                $configNodeId,
                'query',
                [
                    $message['topic'],
                    $message['payload']
                ],
                true //wait for return
            );

            if (!$message['payload']) {
                throw new \RuntimeException('sql error, see log');
            }

            HomegearNodeBase::nodeEvent('statusBottom/' . $nodeId,
                ['text' => 'query executed', 'fill' => 'green', 'shape' => 'dot']);

            $this->output(0, $message);

        } catch (\RuntimeException $e) {
            HomegearNodeBase::nodeEvent('statusBottom/' . $nodeId,
                ['text' => $e->getMessage(), 'fill' => 'red', 'shape' => 'dot']);
        }

    }

    public function stop()
    {
        $this->invokeNodeMethod($this->nodeInfo['info']['server'], 'unregisterNode', [$this->nodeInfo], false);
    }

    public function waitForStop()
    {
        //cloud block max 30s
    }


}
