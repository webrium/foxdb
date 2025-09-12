<?php

namespace Foxdb\Exceptions;

use Exception;

class DatabaseException extends Exception
{
    protected $sql;
    protected $params;
    protected $errorCode;
    protected $errorInfo;

    public function __construct($message = "", $code = 0, ?Exception $previous = null, $sql = null, $params = [], $errorCode = null, $errorInfo = null)
    {
        // Ensure code is always an integer
        $code = is_numeric($code) ? (int)$code : 0;
        
        parent::__construct($message, $code, $previous);
        $this->sql = $sql;
        $this->params = $params;
        $this->errorCode = $errorCode;
        $this->errorInfo = $errorInfo;
    }

    public function getSql()
    {
        return $this->sql;
    }

    public function getParams()
    {
        return $this->params;
    }

    public function getErrorCode()
    {
        return $this->errorCode;
    }

    public function getErrorInfo()
    {
        return $this->errorInfo;
    }

    public function getFormattedMessage()
    {
        $message = $this->getMessage();
        
        if ($this->sql) {
            $message .= "\nSQL: " . $this->sql;
        }
        
        if ($this->params) {
            $message .= "\nParameters: " . json_encode($this->params);
        }
        
        if ($this->errorCode) {
            $message .= "\nError Code: " . $this->errorCode;
        }
        
        return $message;
    }
} 