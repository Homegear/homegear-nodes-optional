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
        $profileId = intval($localNodeInfo['info']['profile']);
        if (isset($profileId) && $profileId != '') {
            try {
                $result = $this->hg->activateVariableProfile(intval($profileId));
            } catch(\Homegear\HomegearException $e) {
                $this->log(4, 'variable-profiles: Exception: '.$e->getMessage());
                $this->output(0, array('payload' => false, 'exception' => $e->getMessage()));
                return;
            }
            $this->output(0, array('payload' => true));
            return;
        }
        $this->output(0, array('payload' => false, 'exception' => 'No profile-id'));
    }
}