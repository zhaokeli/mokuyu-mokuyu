<?php


namespace mokuyu\database\exception;


use Throwable;

/**
 * 查询结果异常
 * Class QueryResultException
 * @package mokuyu\database\exception
 */
class QueryResultException extends \Exception
{
    /**
     * 异常数据
     * @var null
     */
    protected $queryResult = null;

    function __construct($message = "", $code = 0, Throwable $previous = null, $data = [])
    {
        $this->queryResult = $data;
        parent::__construct($message, $code, $previous);
    }

    public function getQueryResult()
    {
        return $this->queryResult;
    }
}