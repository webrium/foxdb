<?php
namespace webrium\foxql;


class Builder {

  private $CONFIG;
  private $PARAMS = [];

  public function __construct(Config $config){
    $this->CONFIG = $config;
  }

  public function execute($query, $params=[], $return=false){
    $this->CONFIG->connect();
    $this->PARAMS = $params;

    if ($this->PARAMS==null) {
      $stmt = $this->CONFIG->pdo()->query($query);
    }
    else {
      $stmt= $this->CONFIG->pdo()->prepare($query);
      $stmt->execute($this->PARAMS);
    }

    if($return){
      return $stmt->fetchAll();
    }
    else {
      return $stmt->rowCount();
    }
  }

}
