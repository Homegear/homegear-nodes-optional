<?php
declare(strict_types=1);

//Class definition is fixed. Do not change it.
class HomegearNode extends HomegearNodeBase
{
    private $hg;
    private $nodeInfo;

    private $registeredNodes = [];
    private $db;
    private $dbConnected = false;

    function __construct()
    {
        //Create a new Homegear object to access Homegear methods
        $this->hg = new \Homegear\Homegear();
    }

    function __destruct()
    {
        //$this->stop($this->nodeInfo);
    }

    //Init is called when the node is created directly after the constructor1
    //Start is called when all nodes are initialized

    public function init(array $nodeInfo): bool
    {
        //Write log entry to flows log
        $this->nodeInfo = $nodeInfo;
        return true; //True means: "init" was successful. When "false" is returned, the node will be unloaded.
    }

    public function start(): bool
    {
        return true; //True means: "start" was successful
    }

    public function startUpComplete()
    {

        $config = $this->getConfigParameterIncoming();

        //var_dump($config);

        if (
            empty($config['server']) ||
            empty($config['database']) ||
            empty($config['username'])
        ) {

            HomegearNodeBase::log(2, 'invalid mysql config');

            foreach ($this->registeredNodes AS $node) {
                HomegearNodeBase::nodeEvent('statusBottom/' . $node['id'],
                    ['text' => 'check config', 'fill' => 'red', 'shape' => 'dot']);
            }

            return false;

        }


        if (empty($config['port'])) {
            $config['port'] = 3306;
        }

        try {

            $dsn = 'mysql:host=' . $config['server'] .
                ';port=' . $config['port'] .
                ';dbname=' . $config['database'] .
                ';charset=utf8'; //todo: charset in config

            $this->db = new PDO(
                $dsn,
                $config['username'],
                $config['password'],
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                ]
            );

            $this->dbConnected = true;

        } catch (\PDOException $e) {
            foreach ($this->registeredNodes AS $node) {
                HomegearNodeBase::nodeEvent('statusBottom/' . $node['id'],
                    ['text' => $e->getMessage(), 'fill' => 'red', 'shape' => 'dot']);
            }
            HomegearNodeBase::log(2, $e->getMessage() . ', DSN: ' . $dsn);
            return false;
        }

    }

    //Stop is called when node is unloaded directly before the destructor. You can still call RPC functions here, but you
    //shouldn't try to access other nodes anymore.

    public function getConfigParameterIncoming($data = false)
    {
        return [
            'server' => $this->nodeInfo['info']['server'],
            'port' => $this->nodeInfo['info']['port'],
            'database' => $this->nodeInfo['info']['database'],
            'username' => $this->nodeInfo['info']['user'],
            'password' => $this->getNodeData('password')
        ];
    }

    public function stop()
    {
    }


    //-----------------------------------------------

    public function waitForStop()
    {
    }

    public function query(string $query, array $data = [])
    {


        if (!$this->dbConnected) {
            HomegearNodeBase::log(2, 'database not connected');
            return false;
        }

        try {

            $stmt = $this->db->prepare($query);
            $stmt->execute($data);
            return $stmt->fetchAll();


        } catch (\PDOException $e) {
            HomegearNodeBase::log(2, 'PDO Error: ' . $e->getMessage());
            return false;
        }
    }


    public function registerNode(array $nodeInfo)
    {
        $this->registeredNodes[$nodeInfo['id']] = $nodeInfo;
    }

    public function unregisterNode(array $nodeInfo)
    {
        unset($this->registeredNodes[$nodeInfo['id']]);
    }


    public function isDbConnected(): bool
    {
        return $this->dbConnected;
    }

}
