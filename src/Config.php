<?php
namespace Foxdb;

class Config extends DB
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

  private $IS_CONNECT = false;

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
  private $THROW_EXCEPTIONS = true;


  /**
   * Constructor to initialize configuration parameters.
   *
   * @param array $params An associative array of configuration parameters.
   *
   * @return void
   */
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
    
    $this->setThrowExceptions($params['throw_exceptions'] ?? true);
  }


  /**
   * Sets the character set for the database connection.
   *
   * @param string $charset The charset to set.
   *
   * @return void
   */
  public function setCharset(string $charset)
  {
    $this->CHARSET = $charset;
  }


  /**
   * Sets the name of the database to connect to.
   *
   * @param string $database_name The name of the database to connect to.
   *
   * @return void
   */
  public function setDatabaseName(string $database_name)
  {
    $this->DATABASE_NAME = $database_name;
  }


  /**
   * Sets the username for the database connection.
   *
   * @param string $username The username to use for the database connection.
   *
   * @return void
   */
  public function setUsername(string $username)
  {
    $this->USERNAME = $username;
  }


  /**
   * Sets the password for the database connection.
   *
   * @param string $password The password to use for the database connection.
   *
   * @return void
   */
  public function setPassword(string $password)
  {
    $this->PASSWORD = $password;
  }


  /**
   * Sets the PDO Fetch mode for the database connection.
   *
   * @param mixed $fetch The fetch mode to use.
   *
   * @return void
   */
  public function setFetch($fetch)
  {
    $this->FETCH = $fetch;
  }

  /**
   * Sets the driver for the database connection.
   *
   * @param string $driver The driver to use for the database connection.
   *
   * @return void
   */
  public function setDriver($driver)
  {
    $this->DRIVER = $driver;
  }

  /**
   * Sets the hostname or IP address of the server to connect to.
   *
   * @param string $host_address A hostname or IP address of the server to connect to.
   *
   * @return void
   */
  public function setHost(string $host_address)
  {
    $this->SERVER_HOST = $host_address;
  }


  /**
   * Sets the port number to use for the database connection.
   *
   * @param int $port The port number to use for the database connection.
   *
   * @return void
   */
  public function setPort(string $port)
  {
    $this->SERVER_PORT = $port;
  }


  /**
   * Sets the collation for the database connection.
   *
   * @param string $collation The collation to use for the database connection.
   *
   * @return void
   */
  public function setCollation(string $collation)
  {
    $this->COLLATION = $collation;
  }

  /**
   * Sets whether to throw exceptions on database errors.
   *
   * @param bool $value Whether to throw exceptions (true) or return false on errors (false).
   *
   * @return void
   */
  public function setThrowExceptions(bool $value)
  {
    $this->THROW_EXCEPTIONS = $value;
  }

  /**
   * Gets whether exceptions are thrown on database errors.
   *
   * @return bool True if exceptions are thrown, false otherwise.
   */
  public function getThrowExceptions()
  {
    return $this->THROW_EXCEPTIONS;
  }

  /**
   * Builds a PDO-compatible DNS string from the configuration parameters.
   *
   * @return string A PDO-compatible DNS string.
   */
  public function makeConnectionString()
  {

    // driver and host
    $dsn = "$this->DRIVER:host=$this->SERVER_HOST";

    // add server port to host string
    if ($this->SERVER_PORT != false && !empty($this->SERVER_PORT)) {
      $dsn .= ":$this->SERVER_PORT";
    }

    $dsn .= ';';

    $dsn .= "dbname=$this->DATABASE_NAME;";

    // add charset
    $dsn .= "charset=$this->CHARSET;";

    return $dsn;
  }


  /**
   * Establishes a connection to the database specified by the configuration parameters.
   *
   * @return void
   *
   * @throws Exception if database settings were not made correctly and the connection was not established.
   */
  public function connect()
  {
    if (!$this->IS_CONNECT) {
      $dsn = $this->makeConnectionString();

      $pdoOptions = [
        \PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES '$this->CHARSET' COLLATE '$this->COLLATION'"
      ];
      
      if ($this->THROW_EXCEPTIONS) {
        $pdoOptions[\PDO::ATTR_ERRMODE] = \PDO::ERRMODE_EXCEPTION;
      } else {
        $pdoOptions[\PDO::ATTR_ERRMODE] = \PDO::ERRMODE_WARNING;
      }
      
      $this->PDO = new \PDO($dsn, $this->USERNAME, $this->PASSWORD, $pdoOptions);

      if (DB::$CHANGE_ONCE) {
        DB::$CHANGE_ONCE = false;
        DB::$USE_DATABASE = 'main';
      }

      $this->IS_CONNECT = true;
    }
  }

  /**
   * Returns the PDO Fetch mode for the database connection.
   *
   * @return mixed The fetch mode for the database connection.
   */
  public function getFetch()
  {
    return $this->FETCH;
  }

  /**
   * Returns the PDO object for the database connection.
   *
   * @return \PDO The PDO object for the database connection.
   *
   * @throws Exception if database settings were not made correctly and the connection was not established.
   */
  public function pdo()
  {
    if (!$this->PDO) {
      throw new \Exception("Could not establish a connection to the database. Please check your database configuration settings in config file or ensure that your database server is running");
    }

    return $this->PDO;
  }


  /**
   * Returns the configuration parameters as an associative array.
   *
   * @return array An associative array of configuration parameters.
   */
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