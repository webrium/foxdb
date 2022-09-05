<?php

namespace webrium\foxql;

class UpdateExtraMethods {

  protected $first_result;
  private $method_invoked = false;

  function __construct($result)
  {
    $this->first_result = $result;
  }

  function __destruct()
  {
      if(!$this->method_invoked)
      {
          return $this->first_result;
      }
  }

  public function setTimestamp(){
    $this->method_invoked = true;
    echo 'set time stamp';
  }
}
