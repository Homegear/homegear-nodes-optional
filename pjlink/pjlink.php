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
        $projector = (isset($message['payload']['projector']) ? $message['payload']['projector'] : $nodeInfoLocal['info']['projector']);
        $port = (isset($message['payload']['port']) ? $message['payload']['port'] : $nodeInfoLocal['info']['port']);
        $timeout = (isset($message['payload']['timeout']) ? $message['payload']['timeout'] : $nodeInfoLocal['info']['timeout']);
        
        $command = (isset($message['payload']['command']) ? $message['payload']['command'] : $nodeInfoLocal['info']['command']);
        $param = (isset($message['payload']['param']) ? $message['payload']['param'] : $nodeInfoLocal['info']['param']);
        
        $this->_sendMessage($projector,$port,$timeout,$command,$param);
    }

    private function _getResponse($socket)
    {
        $response = "";
		while (true) {
			$char = fgetc($socket);
			if (($char !== false) && ($char !== chr(13))) {
				$response.= $char;
			} else {
				return $response;
			}
		}        
    }

    private function _sendMessage($projector = false, $port = false, $timeout = false, $command = false, $param = false)
    {
        if ($projector == false) { $this->log(4,"pjlink: projector missing!"); return; }
        if ($port == 0) { $port = 4352; }
        if ($timeout == 0 ) { $timeout = 5; }
        if ($command == false) { $this->log(4,"pjlink: command missing!"); return; }
        if ($param == false) { $this->log(4,"pjlink: param missing!"); return; }

        $socket=fsockopen($projector,intval($port), $errno, $errstr, intval($timeout));
        if (!$socket) 
        {
            $result="$errstr ($errno)";            
            $this->log(4, $result);
        } 
        else 
        {
            stream_set_timeout($socket,intval($timeout), 0);
            $result = $this->_getResponse($socket);            
            $this->log(4, $result);
            
            if(false !== strpos($result, "PJLINK 0")) 
            {
                $cmd="%1" . $command . " " . $param;
                $this->log(4, $cmd);
                fwrite($socket, $cmd . chr(13));
                $result = $this->_getResponse($socket);
                
                $this->log(4, $result);
            }
            else
            {
                $result = "only unauthorized connections";                
                $this->log(4, $result);
            }
            fclose($socket);
        }
        $this->output(0, array('payload' => $result));
    }

}
?>