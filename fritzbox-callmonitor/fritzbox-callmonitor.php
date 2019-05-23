<?php
declare(strict_types=1);
// use Homegear\HomegearGpio as HomegearGpio;
class SharedData extends Threaded
{
	public $scriptId = 0;
	public $nodeId = "";
    // public $gpioIndex = 0;
    public $trueOnly = false;
    public $stop = false;
    public function run() {}
}
class CallManagerThread extends Thread
{
    private $sharedData;
	public function __construct($sharedData)
	{
		$this->sharedData = $sharedData;
	}
	public function run()
	{
		$hg = new \Homegear\Homegear();
		if($hg->registerThread($this->sharedData->scriptId) === false)
		{
			$hg->log(2, "Could not register thread.");
			return;
		}

		$fritzboxSocket = fsockopen($this->sharedData->fritzHost, $this->sharedData->fritzPort);

		while(!$this->sharedData->stop)
		{
			stream_set_timeout($fritzboxSocket, 10);
			$result = fgets($fritzboxSocket);
			
			if($result!="") 
			{
				$hg->nodeOutput($this->sharedData->nodeId, 0, array('payload' => $result));
			}
		}
		fclose($fritzboxSocket);
	}
}
class HomegearNode extends HomegearNodeBase
{
private $hg = NULL;
private $nodeInfo = NULL;
private $sharedData = NULL;
private $thread = NULL;
function __construct()
{
	$this->hg = new \Homegear\Homegear();
}
function __destruct()
{
	$this->stop();
}
public function init(array $nodeInfo) : bool
{
	$this->nodeInfo = $nodeInfo;
	return true;
}
public function start() : bool
{
	$this->sharedData = new SharedData();
	$this->sharedData->scriptId = $this->hg->getScriptId();
	$this->sharedData->fritzHost = (int)$this->nodeInfo['info']['fritzbox'];
	$this->sharedData->fritzPort = (int)$this->nodeInfo['info']['port'];
	$this->sharedData->nodeId = $this->nodeInfo['id'];
	$this->thread = new CallManagerThread($this->sharedData);
	$this->thread->start();
	return true;
}
public function stop()
{
	if($this->thread) $this->sharedData->stop = true;
}
public function waitForStop()
{
	if($this->thread) $this->thread->join();
	$this->thread = NULL;
}
}
