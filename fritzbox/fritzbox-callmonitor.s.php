<?php
declare(strict_types=1);
class SharedData extends Threaded
{
	public $scriptId = 0;
	public $nodeId = "";
	public $fritzHost = "";
	public $fritzPort = 0;
	public $trueOnly = false;
	public $stop = false;
	public function run() 
	{
	}
}
class Message 
{
	public $type;
	public $id;
	public $timestamp;
	public $caller;
	public $callee;
	public $extension;
	public $length;

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

class CallManagerThread extends Thread
{
	private $sharedData;
	private $hg;
	private $connections;
  public function __construct($sharedData)
    {
		$this->sharedData = $sharedData;
		$this->hg = new \Homegear\Homegear();   
		$this->connections = array();
	}
	private function _parseCallRecord($record)
	{
		$columns = explode(";",$record);
		$timestamp = $columns[0];
		$type = $columns[1];
		$id = $columns[2];
		$msg = new Message();
		$msg->id = $id;
		$msg->timestamp = $timestamp;

		switch($type) {
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
			  $msg->copyEndpoints($this->connections[$id]);
				switch($msg->type) {
					case "INBOUND":
						$msg->type = "MISSED";
						break;
					case "CONNECT":
					  $msg->setDuration($this->connections[$id]);
						$msg->type = "DISCONNECT";
						break;
					case "OUTBOUND":
						$msg->type = "UNREACHED";
						break;
				}
				$this->connections[$id] = NULL;
				break;
		}

		return json_encode($msg);

	}
		public function run()
		{

		if($this->hg->registerThread($this->sharedData->scriptId) === false)
		{
			$this->hg->log(2, "fritzbox: Could not register thread.");
			return;
		}

		$fritzboxSocket = fsockopen($this->sharedData->fritzHost, $this->sharedData->fritzPort);

		while(!$this->sharedData->stop)
		{
			stream_set_timeout($fritzboxSocket, 10);
			$result = fgets($fritzboxSocket);
			
			if($result!="") 
			{
				$this->hg->log(4,"fritzbox: $result");
				$payload=$this->_parseCallRecord($result);
				$this->hg->nodeOutput($this->sharedData->nodeId, 0, array('payload' => $payload));
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
		$this->sharedData->fritzHost = $this->nodeInfo['info']['fritzbox'];
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
