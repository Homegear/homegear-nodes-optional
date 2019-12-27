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
        array_map('unlink', glob("/tmp/*_homegear-backup.tar.gz"));
        $id = $this->hg->managementCreateBackup();
        $result = $this->_getCommandStatus($id);
        if ($result['exitCode'] == 0) {
            $this->output(0, array('payload' => $result['metadata']['filename']));
            return;
        }
        $this->output(0, array('payload' => 'error creating backup'));
        return;
    }

    private function _getCommandStatus($id) {
        $result = $this->hg->managementGetCommandStatus($id);
        while (!$result['finished']) {
            sleep(1);
            $result = $this->hg->managementGetCommandStatus($id);
        }
        return $result;
    }

}