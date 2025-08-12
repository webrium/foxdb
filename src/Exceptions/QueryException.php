<?php

namespace Foxdb\Exceptions;

class QueryException extends DatabaseException
{
    public function __construct($message = "", $code = 0, $previous = null, $sql = null, $params = [], $errorCode = null, $errorInfo = null)
    {
        if (empty($message)) {
            $message = "SQL query execution failed";
        }
        
        parent::__construct($message, $code, $previous, $sql, $params, $errorCode, $errorInfo);
    }
} 