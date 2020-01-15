<?php
declare(strict_types=1);

class HomegearNode extends HomegearNodeBase
{

    const PUSHOVER_API_URL = 'https://api.pushover.net/1/messages.json';

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
        //$this->invokeNodeMethod($this->nodeInfo['info']['server'], 'registerNode', [$this->nodeInfo], true);


    }

    public function startUpComplete()
    {
    }


    public function input(array $nodeInfoLocal, int $inputIndex, array $message)
    {

        //var_dump($nodeInfoLocal);

        $nodeId = $nodeInfoLocal['id'];

        try {

            if (empty($nodeInfoLocal['info']['userKey']) || empty($nodeInfoLocal['info']['apiToken'])) {
                HomegearNodeBase::log(2, 'Missing userKey or apiToken');
                throw new \RuntimeException('check User Key/API Token');
            }

            if (empty($message['payload'])) {
                HomegearNodeBase::log(2, 'Missing payload for pushover message');
                throw new \RuntimeException('empty Payload');
            }

            //check for device in message or info, else let it be empty and send to all devices
            $device = null;
            if (!empty($message['device'])) {
                $device = $message['device'];
            } elseif (!empty($nodeInfoLocal['info']['device'])) {
                $device = $nodeInfoLocal['info']['device'];
            }

            //do we have a user supplied title?
            $title = 'Homegear';
            if (!empty($message['topic'])) {
                $title = $message['topic'];
            } elseif (!empty($nodeInfoLocal['info']['title'])) {
                $title = $nodeInfoLocal['info']['title'];
            }


            $requestData = [
                'token' => filter_var($nodeInfoLocal['info']['apiToken'], FILTER_SANITIZE_STRING),
                'user' => filter_var($nodeInfoLocal['info']['userKey'], FILTER_SANITIZE_STRING),
                'message' => filter_var($message['payload'], FILTER_SANITIZE_STRING),
                'title' => filter_var($title, FILTER_SANITIZE_STRING)
            ];

            if (!empty($device)) {
                $requestData['device'] = filter_var($device, FILTER_SANITIZE_STRING);
            }

            //do the curl magic
            $ch = curl_init();

            curl_setopt($ch, CURLOPT_URL, SELF::PUSHOVER_API_URL);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $requestData);

            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

            $response = curl_exec($ch);
            $error = curl_error($ch);
            $errno = curl_errno($ch);

            if (is_resource($ch)) {
                curl_close($ch);
            }

            if (0 !== $errno) {
                throw new \RuntimeException($error, $errno);
            }

            $response = json_decode($response, true);

            if ($response['status'] === 0) {
                HomegearNodeBase::log(2, 'Got error(s) from Pushover API: ' . implode(', ', $response['errors']));
                throw new \RuntimeException('ERROR! See Log');
            }

            HomegearNodeBase::nodeEvent('statusBottom/' . $nodeId,
                ['text' => 'message sent (' . date('Y-m-d H:i:s') . ')', 'fill' => 'green', 'shape' => 'dot']);

        } catch (\RuntimeException $e) {
            HomegearNodeBase::nodeEvent('statusBottom/' . $nodeId,
                ['text' => $e->getMessage(), 'fill' => 'red', 'shape' => 'dot']);
        }

    }

    public function stop()
    {
    }

    public function waitForStop()
    {
        //cloud block max 30s
    }


}
