<?php
declare(strict_types=1);

class SharedData extends Threaded
{
    public $scriptId = 0;
    public $nodeId = '';
    public $stop = false;

    public $username = '';
    public $password = '';
    public $clientid = '';
    public $clientsecret = '';
    public $country = '';
    public $language = '';

    public function run() {}
}

class RestThread extends Thread
{
    private $sharedData;
    private $authorizationCode = '';
    private $accessToken = '';
    private $refreshToken = '';
    private $deviceData = array();

    public function __construct($sharedData)
    {
        $this->sharedData = $sharedData;
    }

    private function curlRequest($url, $method, $contentType, $data, $token, &$responseCode)
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $headers = array();
        if($contentType) $headers[] = 'Content-Type: '.$contentType;
        if($token) $headers[] = 'Authorization: Bearer '.urlencode($token);
        if(count($headers) > 0) curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        if($method == 'POST') curl_setopt($ch, CURLOPT_POSTFIELDS, $data);

        $result = curl_exec($ch);
        $returnValue = false;
        if($result !== false)
        {
            $responseCode = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            if($responseCode == 302) $returnValue = curl_getinfo($ch, CURLINFO_REDIRECT_URL);
            else if($responseCode >= 200 && $responseCode < 300) $returnValue = $result;
        }
        curl_close($ch);
        return $returnValue;
    }

    private function login()
    {
        $this->accessToken = '';
        if($this->refreshToken && $this->sharedData->clientid && $this->sharedData->clientsecret)
        {
            $url = 'https://api.mcs3.miele.com/thirdparty/token?client_id='.urlencode($this->sharedData->clientid).'&client_secret='.urlencode($this->sharedData->clientsecret).'&refresh_token='.urlencode($this->refreshToken).'&redirect_uri=%2Fv1%2Fdevices&grant_type=refresh_token&state=token';
            $responseCode = 0;
            $result = $this->curlRequest($url, 'GET', '', '', '', $responseCode);
            if($result === false || $responseCode == 0) return false;
            else if($responseCode == 200)
            {
                $tokens = json_decode($result, true);
                $this->accessToken = $tokens['access_token'] ?? '';
                $this->refreshToken = $tokens['refresh_token'] ?? '';
                if($this->accessToken)
                {
                    HomegearNodeBase::log(4, 'Successfully refreshed access token.');
                    return true;
                }
            }
        }

        if($this->sharedData->userid && $this->sharedData->password && $this->sharedData->clientid && $this->sharedData->clientsecret && $this->sharedData->country)
        {
            $this->authorizationCode = '';

            $url = 'https://api.mcs3.miele.com/oauth/auth';
            $data = 'email='.urlencode($this->sharedData->userid).'&password='.urlencode($this->sharedData->password).'&redirect_uri=%2Fv1%2Fdevices&state=login&response_type=code&client_id='.urlencode($this->sharedData->clientid).'&vgInformationSelector='.urlencode($this->sharedData->country);
            $responseCode = 0;
            $result = $this->curlRequest($url, 'POST', 'application/x-www-form-urlencoded', $data, '', $responseCode);
            if($result === false || $responseCode == 0)
            {
                HomegearNodeBase::log(2, 'Unknown error obtaining authorization code. E. g. server is not reachable.');
                return false;
            }
            else if($responseCode == 302)
            {
                if(is_string($result))
                {
                    $parameters = explode('?', $result);
                    if(count($parameters) > 1)
                    {
                        $parameters = $parameters[1];
                        $parameters = explode('&', $parameters);
                        foreach($parameters as $parameter)
                        {
                            $parameter = explode('=', $parameter);
                            if(count($parameter) == 2 && $parameter[0] == 'code') $this->authorizationCode = $parameter[1];
                        }
                    }
                }
            }
            else
            {
                HomegearNodeBase::log(2, 'Error obtaining authorization code (response code '.$responseCode.'): '.$result);
            }

            if($this->authorizationCode)
            {
                $url = 'https://api.mcs3.miele.com/thirdparty/token?client_id='.urlencode($this->sharedData->clientid).'&client_secret='.urlencode($this->sharedData->clientsecret).'&code='.urlencode($this->authorizationCode).'&redirect_uri=%2Fv1%2Fdevices&grant_type=authorization_code&state=token';
                $responseCode = 0;
                $result = $this->curlRequest($url, 'POST', 'application/x-www-form-urlencoded', '', '', $responseCode);
                if($result === false || $responseCode == 0)
                {
                    HomegearNodeBase::log(2, 'Unknown error obtaining access token. E. g. server is not reachable.');
                    return false;
                }
                else if($responseCode == 200)
                {
                    $tokens = json_decode($result, true);
                    $this->accessToken = $tokens['access_token'] ?? '';
                    $this->refreshToken = $tokens['refresh_token'] ?? '';
                    if($this->accessToken)
                    {
                        HomegearNodeBase::log(4, 'Successfully obtained access token.');
                        return true;
                    }
                }
                else
                {
                    HomegearNodeBase::log(2, 'Error obtaining access token (response code '.$responseCode.'): '.$result);
                    return false;
                }
            }
        }
        else
        {
            HomegearNodeBase::log(2, 'Node is not fully configured.');
        }

        return false;
    }

    private function restRequest($url, $method, $contentType = '', $data = '')
    {
        if(!$this->accessToken)
        {
            if($this->login() === false) return false;
        }

        $responseCode = 0;
        $result = $this->curlRequest($url, $method, $contentType, $data, $this->accessToken, $responseCode);
        if($result === false || $responseCode == 0)
        {
            HomegearNodeBase::log(2, 'Unknown error calling URL '.$url.'. E. g. server is not reachable.');
            return false;
        }
        else if($responseCode == 401)
        {
            if($this->login() === false) return false;
            $result = $this->curlRequest($url, $method, $contentType, $data, $this->accessToken, $responseCode);
        }

        if($responseCode >= 200 && $responseCode < 300) return json_decode($result, true);
        else
        {
            HomegearNodeBase::log(2, 'Error calling URL '.$url.' (response code '.$responseCode.'): '.$result);
        }

        return false;
    }

    private function getDevices()
    {
        return $this->restRequest('https://api.mcs3.miele.com/v1/devices/'.($this->sharedData->language ? '?language='.urlencode($this->sharedData->language) : ''), 'GET');
    }

    private function processDevices($devices)
    {
        foreach ($devices as $device)
        {
            $deviceData = array();
            $serialNumber = $device['ident']['deviceIdentLabel']['fabNumber'] ?? '';
            if(!$serialNumber) continue;
            $deviceData['serialNumber'] = $serialNumber;
            $deviceData['typeId'] = $device['ident']['type']['value_raw'] ?? 0;
            $deviceData['typeLabel'] = $device['ident']['type']['value_localized'] ?? '';
            $deviceData['deviceName'] = $device['ident']['deviceName'] ?? '';
            $deviceData['state'] = $device['state'] ?? NULL;
            $deviceData['hash'] = md5(serialize($deviceData));

            if(isset($this->deviceData[$serialNumber]) && isset($this->deviceData[$serialNumber]['hash']) && $this->deviceData[$serialNumber]['hash'] == $deviceData['hash'])
            {
                continue;
            }

            $this->deviceData[$serialNumber] = $deviceData;

            \Homegear\Homegear::nodeOutput($this->sharedData->nodeId, 0, array('payload' => $deviceData));
        }
    }

    public function run()
    {
        $hg = new \Homegear\Homegear();
        if($hg->registerThread($this->sharedData->scriptId) === false)
        {
            $hg->log(2, "Could not register thread.");
            return;
        }
        
        for($i = 0; $i < rand(50, 70); $i++)
        {
            if($this->sharedData->stop) break;
            $this->synchronized(function($thread){ $thread->wait(1000000); }, $this);
        }

        while(!$this->sharedData->stop)
        {
            $devices = $this->getDevices();
            if($devices !== false)
            {
                $this->processDevices($devices);
            }

            for($i = 0; $i < 60; $i++)
            {
                if($this->sharedData->stop) break;
                $this->synchronized(function($thread){ $thread->wait(1000000); }, $this);
            }
        }
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
    $this->sharedData->nodeId = $this->nodeInfo['id'];

    $this->sharedData->userid = $this->nodeInfo['info']['userid'];
    $this->sharedData->password = $this->getNodeData('user-password');
    $this->sharedData->clientid = $this->nodeInfo['info']['clientid'];
    $this->sharedData->clientsecret = $this->getNodeData('clientsecret-password');
    $this->sharedData->country = $this->nodeInfo['info']['country'];
    $this->sharedData->language = $this->nodeInfo['info']['language'];
    $this->thread = new RestThread($this->sharedData);
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
