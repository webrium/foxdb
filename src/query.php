<?php
namespace webrium\foxql;

class query extends db{

  private $config,$pdo,$connected=false;

  private function setConfig($config)
  {
    $this->config = $config;
  }

  private function connect()
  {
    $this->pdo = new \PDO($this->config['driver'].":host=".$this->config['db_host'].':'.$this->config['db_host_port'].";dbname=".$this->config['db_name'].";charset=".$this->config['charset'],$this->config['username'],$this->config['password']);
    $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_WARNING);
    $this->$connected = true;
  }


}
