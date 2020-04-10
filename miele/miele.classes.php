<?php
declare(strict_types=1);

class MieleSettings
{
	public $userId = '';
    public $password = '';
    public $clientId = '';
    public $clientSecret = '';
    public $country = '';
    public $language = '';
}

class MieleRest
{
	private $settings = NULL;
    private $authorizationCode = '';
    private $accessToken = '';
    private $refreshToken = '';
    private $deviceData = array();

    public function __construct(MieleSettings $settings)
    {
        $this->settings = $settings;
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
        if($this->refreshToken && $this->settings->clientId && $this->settings->clientSecret)
        {
            $url = 'https://api.mcs3.miele.com/thirdparty/token?client_id='.urlencode($this->settings->clientId).'&client_secret='.urlencode($this->settings->clientSecret).'&refresh_token='.urlencode($this->refreshToken).'&redirect_uri=%2Fv1%2Fdevices&grant_type=refresh_token&state=token';
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

        if($this->settings->userId && $this->settings->password && $this->settings->clientId && $this->settings->clientSecret && $this->settings->country)
        {
            $this->authorizationCode = '';

            $url = 'https://api.mcs3.miele.com/oauth/auth';
            $data = 'email='.urlencode($this->settings->userId).'&password='.urlencode($this->settings->password).'&redirect_uri=%2Fv1%2Fdevices&state=login&response_type=code&client_id='.urlencode($this->settings->clientId).'&vgInformationSelector='.urlencode($this->settings->country);
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
                $url = 'https://api.mcs3.miele.com/thirdparty/token?client_id='.urlencode($this->settings->clientId).'&client_secret='.urlencode($this->settings->clientSecret).'&code='.urlencode($this->authorizationCode).'&redirect_uri=%2Fv1%2Fdevices&grant_type=authorization_code&state=token';
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

    public function getDevices()
    {
        return $this->restRequest('https://api.mcs3.miele.com/v1/devices/'.($this->settings->language ? '?language='.urlencode($this->settings->language) : ''), 'GET');
    }

    public function processDevices($devices)
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

            \Homegear\Homegear::nodeOutput($this->settings->nodeId, 0, array('payload' => $deviceData));
        }
    }
}