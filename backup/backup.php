<?php

class HomegearNode extends HomegearNodeBase
{
    private $hg = null;

    public function __construct()
    { 
        $this->hg = new \Homegear\Homegear();
    }

    public function input(array $localNodeInfo, int $inputIndex, array $message)
    {
        $result = $this->hg->managementCreateBackup();
        $tmp = $this->_getCommandStatus($this->hg);
        if ($result != '') {
            $this->output(0, array('payload' => $result));
            return;
        }
        $this->output(0, array('payload' => 'error creating backup'));
        return;
    }

    private function _getCommandStatus($hg) {
        $result = $hg->managementGetCommandStatus();
        while ($result[0] == 256) {
            sleep(1);
            $result = $hg->managementGetCommandStatus();
        }
        return $result;
    }

}