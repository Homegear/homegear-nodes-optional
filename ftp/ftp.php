<?php
declare(strict_types=1);
require_once('vendor/nicolab/php-ftp-client/FtpClient.php');
require_once('vendor/nicolab/php-ftp-client/FtpException.php');
require_once('vendor/nicolab/php-ftp-client/FtpWrapper.php');


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
        $config = $this->getConfigParameter($nodeInfoLocal['info']['server'],'port');
        $ftp = new \FtpClient\FtpClient();
        $ftp->connect($config['server']);
        $ftp->login($config['username'], $config['password']);
        $ftp->pasv(true);
        
        // check if directory exists
        if (!$ftp->isDir($config['path'])) {
            $ftp->mkdir($config['path'],true);
        }

        // change to directory
        $ftp->chdir($config['path']);

        // check if we have a path to a file
        if ($nodeInfoLocal['info']['data'] == "filepath") {
            // upload file
            $ftp->putFromPath($message['payload']);
        } elseif ($nodeInfoLocal['info']['data'] == "plaintext") {
            // upload plaintext to a new file
            $filename = time().".txt";
            $ftp->putFromString($filename,$message['payload']);
        }
    }
}
