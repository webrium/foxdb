<?php

namespace webrium\foxql;

class Config
{

  public const FETCH_CLASS = \PDO::FETCH_CLASS;
  public const FETCH_ASSOC = \PDO::FETCH_ASSOC;

  public const UTF8 = 'utf8';
  public const UTF8_BIN = 'utf8_bin';
  public const UTF8_UNICODE_CI = 'utf8_unicode_ci';
  public const UTF8_GENERAL_CI = 'utf8_general_ci';

  public const UTF8MB4 = 'utf8mb4';
  public const UTF8MB4_BIN = 'utf8mb4_bin';
  public const UTF8MB4_UNICODE_CI = 'utf8mb4_unicode_ci';
  public const UTF8MB4_GENERAL_CI = 'utf8mb4_general_ci';

  private const DEFAULT_HOST = 'localhost';
  private const DEFAULT_PORT = 3306;
  private const DEFAULT_CHARSET = 'utf8mb4';
  private const DEFAULT_COLLATION = 'utf8_unicode_ci';
  private const DEFAULT_DRIVER = 'mysql';

  private $DRIVER = '';
  private $SERVER_HOST = '';
  private $SERVER_PORT = '';

  private $DATABASE_NAME = '';
  private $USERNAME = '';
  private $PASSWORD = '';
  private $FETCH = '';
  private $CHARSET = 'utf8mb4';
  private $COLLATION = '';

  private $PDO = false;


  public function __construct(array $params = [])
  {

    $this->setDriver($params['driver'] ?? self::DEFAULT_DRIVER);
    $this->setHost($params['host'] ?? self::DEFAULT_HOST);
    $this->setPort($params['port'] ?? self::DEFAULT_PORT);

    $this->setCharset($params['charset'] ?? self::DEFAULT_CHARSET);
    $this->setCollation($params['collation'] ?? self::DEFAULT_COLLATION);
    $this->setFetch($params['fetch'] ?? self::FETCH_CLASS);

    $this->setDatabaseName($params['database'] ?? false);
    $this->setUsername($params['username'] ?? false);
    $this->setPassword($params['password'] ?? false);
  }



  public function setCharset(string $charset)
  {
    $this->CHARSET = $charset;
  }

  public function setDatabaseName(string $database_name)
  {
    $this->DATABASE_NAME = $database_name;
  }

  public function setUsername(string $username)
  {
    $this->USERNAME = $username;
  }

  public function setPassword(string $password)
  {
    $this->PASSWORD = $password;
  }

  public function setFetch($fetch)
  {
    $this->FETCH = $fetch;
  }

  public function setDriver($driver)
  {
    $this->DRIVER = $driver;
  }

  public function setHost(string $host_address)
  {
    $this->SERVER_HOST = $host_address;
  }

  public function setPort(string $port)
  {
    $this->SERVER_PORT = $port;
  }

  public function setCollation(string $collation)
  {
    $this->COLLATION = $collation;
  }

  public function makeConnectionString()
  {

    // driver and host
    $dsn = "$this->DRIVER:host=$this->SERVER_HOST";

    // add server port to host string
    if ($this->SERVER_PORT != false && !empty($this->SERVER_PORT)) {
      $dsn .= ":$this->SERVER_PORT";
    }
    
    $dsn.= ';';

    $dsn.= "dbname=$this->DATABASE_NAME;";

    // add charset
    $dsn .= "charset=$this->CHARSET;";

    return $dsn;
  }


  public function connect()
  {
    $dsn = $this->makeConnectionString();

    $this->PDO = new \PDO($dsn, $this->USERNAME, $this->PASSWORD, [
      \PDO::ATTR_ERRMODE => \PDO::ERRMODE_WARNING,
      \PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES '$this->CHARSET' COLLATE '$this->COLLATION'"
    ]);
  }

  public function getFetch(){
    return $this->FETCH;
  }

  public function pdo(){
    if (!$this->PDO){
      throw new \Exception("The database settings were not made correctly and the connection was not established\n Please check 'https://github.com/webrium/foxql' Documents.");
    }

    return $this->PDO;
  }

  public function getAsArray()
  {
    return [
      'host' => $this->SERVER_HOST,
      'port' => $this->SERVER_PORT,
      'database' => $this->DATABASE_NAME,
      'username' => $this->USERNAME,
      'password' => $this->PASSWORD,
      'charset' => $this->CHARSET,
      'collation' => $this->COLLATION,
      'fetch' => $this->FETCH
    ];
  }
}
