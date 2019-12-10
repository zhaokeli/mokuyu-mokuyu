<?php
/**
 * Mokuyu database
 * https://www.zhaokeli.com
 * Version 1.0.0
 *
 * Copyright 2020, Mokuyu
 * Released under the MIT license
 *
 */
declare (strict_types = 1);
namespace mokuyu\database;

use Exception;
use InvalidArgumentException;
use PDO;
use PDOException;
use PDOStatement;
use Psr\SimpleCache\CacheInterface;

class Mokuyu
{
    /**
     * sql中绑定的参数数组
     * @var array
     */
    protected $bindParam = [];

    /**
     * 缓存对象,要实现CacheInterface接口,保存表字段加快速度
     * @var null
     */
    protected $cache = null;

    protected $charset;

    protected $databaseFile;

    protected $databaseName;

    /**
     * 数据库连接相关信息
     * @var [type]
     */
    protected $databaseType;

    /**
     * 调式开头
     * @var boolean
     */
    protected $debug = false;

    /**
     * 中断调试，会直接中断并输出当前sql语句
     * @var boolean
     */
    protected $debugMode = false;

    /**
     * 错误信息保存
     * @var array
     */
    protected $errors = [];

    /**
     * 字段映射
     * 格式为 别名(查询)字段=>数据库真实字段
     * @var [type]
     */
    protected $fieldMap = [
        //格式为 别名(查询)字段=>数据库真实字段
        // 'push_time' => 'create_time',
    ];

    /**
     * 字段风格,把传入的字段转为下面
     * 0:原样不动，1:转换为下划线风格，2:转换为驼峰风格
     * @var null
     */
    protected $fieldMode = 0;

    /**
     * 是否返回sql语句,true则返回语句,false返回对应的数据
     * @var boolean
     */
    protected $isFetchSql = false;

    /**
     * 所有执行过的sql语句
     * @var array
     */
    protected $logs = [];

    protected $options = [
        PDO::ATTR_ERRMODE           => PDO::ERRMODE_EXCEPTION, // 抛出 exceptions 异常。
        PDO::ATTR_ORACLE_NULLS      => PDO::NULL_TO_STRING,    //将 NULL 转换成空字符串
        PDO::ATTR_EMULATE_PREPARES  => false,                  //禁用本地模拟prepare
                                                               // PDO::ATTR_PERSISTENT        => true, //长连接
        PDO::ATTR_STRINGIFY_FETCHES => false,                  //提取的时候将数值转换为字符串
    ];

    protected $password;

    /**
     * pdo 读写对象和只读配置
     * @var null
     */
    protected $pdo = null;

    protected $pdoRead = null;

    // For MySQL or MariaDB with unix_socket
    protected $port;

    protected $prefix;

    //只读数据库配置

    /**
     * 每次执行请求的SQL参数组合
     * @var array
     */
    protected $queryParams = [];

    protected $read = [];

    protected $server;

    // For SQLite
    protected $socket;

    //单次查询临时字段风格

    /**
     * 数据表风格,把传入的表名转为下面
     * 0:原样不动，1:转换为下划线风格，2:转换为驼峰风格
     * @var null
     */
    protected $tableMode = 1;

    protected $username;

    //单次查询临时表风格

    /**
     * 关键字引号
     * @var string
     */
    protected $yinhao = '"';

    /**
     * 开启缓存后保存的缓存key列表
     * @var array
     */
    private $cacheKeys = [];

    //全局字段风格
    private $temFieldModeTem = 0;

    //全局数据表样式
    private $temTableMode = 0;

    /**
     * 初始化连接
     * @DateTime 2019-11-05
     * @Author   mokuyu
     *
     * @param array $config [description]
     */
    public function __construct(array $config)
    {
        foreach ($config as $option => $value) {
            $option        = $this->parseName($option, 2);
            $this->$option = $value;
        }
        $this->temFieldModeTem = $this->fieldMode;
        $this->temTableMode    = $this->tableMode;
        $this->dbConfig        = $config;
        $this->initQueryParams();
        $this->pdo = $this->buildPDO($config);
    }

    /**
     * 添加数据成功后返回添加成功的id
     * @param [type] $datas [description]
     */
    public function add(array $datas)
    {
        $this->buildSqlConf();
        $table = $this->queryParams['table'];
        if (empty($table)) {
            return 0;
        }
        $pk     = $this->getPK();
        $lastId = [];

        if (count($datas) == count($datas, 1)) {
            $datas = [$datas];
        }
        $isMulData = count($datas) > 1 ? true : false;
        //取表的所有字段
        $table_fields = (array) $this->getFields();
        $index        = $isMulData ? 0 : null;
        $sql          = '';
        foreach ($datas as $data) {
            $values  = [];
            $columns = [];
            foreach ((array) $data as $key => $value) {
                $field = lcfirst($key);
                $field = strtolower(preg_replace('/([A-Z])/', '_$1', $field));
                if (!in_array($field, $table_fields) || $field == $pk) {
                    //过滤掉数据库中没有的字段,和主键
                    continue;
                }
                $info      = $this->parseFormatField($key);
                $column    = $this->yinhao . $info['field'] . $this->yinhao;
                $columns[] = $column;
                $col       = ':' . $info['field'];
                $values[]  = $col;

                if (is_null($value)) {
                    $this->appendBindParam($col, 'NULL', $index);
                } else if (is_object($value) || is_array($value)) {
                    $this->appendBindParam($col, json_encode($value), $index);
                } else if (is_bool($value)) {
                    $this->appendBindParam($col, ($value ? '1' : '0'), $index);
                } else if (is_integer($value) || is_double($value) || is_string($value)) {
                    $this->appendBindParam($col, $value, $index);
                } else {
                    $this->appendBindParam($col, $value, $index);
                }
            }
            if ($index === 0 || is_null($index)) {
                $cols = implode(',', $columns);
                $vals = implode(',', $values);
                if (!$cols || !$vals) {
                    return 0;
                }
                $sql = 'INSERT INTO ' . $table . ' (' . $cols . ') VALUES (' . $vals . ')';
            }
            $isMulData && $index++;

        }
        $result = $this->exec($sql);
        if (is_string($result)) {
            return $result;
        }
        // $lastId = ;

        return $this->pdo->lastInsertId() ?: $result;
    }

    /**
     * 这个地方传引用进来,防止key出现一样的情况导致冲突,如果一样的话在这个函数里会随机加上一个数字并修改这个key值
     * @DateTime 2019-03-10
     * @Author   mokuyu
     *
     * @param  [type]   &$key  引用类型键
     * @param  [type]   $value 值
     * @param  [type]   $index 多维数据索引,默认为一维数据
     * @return [type]
     */
    public function appendBindParam(&$key, $value, $index = null): void
    {
        $key = ':' . trim($key, ':');
        $tem = $key;
        while (true) {
            //没有绑定过这个参数直接绑定并跳出
            if (!isset($this->bindParam[$tem])) {
                $key = $tem;
                break;
            }
            //绑定过的key，加上随机数，然后再循环一次判断绑定
            $tem = $key . '_' . rand(1000, 9999);
        }
        if (is_null($index)) {
            $this->bindParam[$key] = $value;
        } else {
            $this->bindParam[$index][$key] = $value;
        }

    }

    public function avg(...$field)
    {
        return $this->summary('AVG', $field);
    }

    /**
     * 开启事务
     * @DateTime 2019-04-13
     * @Author   mokuyu
     *
     * @return [type]
     */
    public function beginTransaction()
    {
        $this->pdo->beginTransaction();
    }

    /**
     * 清理保存的缓存
     * @authname [权限名字]     0
     * @DateTime 2019-11-01
     * @Author   mokuyu
     *
     * @return [type]
     */
    public function clearCache()
    {
        $this->cache->deleteMultiple(array_keys($this->cacheKeys));
        $this->cacheKeys = [];
    }

    /**
     * 提交事务的操作
     * @DateTime 2019-04-13
     * @Author   mokuyu
     *
     * @return [type]
     */
    public function commit()
    {
        $this->pdo->commit();
    }

    public function count($field = '*')
    {
        return $this->summary('COUNT', [$field]);
    }

    public function debug(bool $isdebug = true)
    {
        $this->debugMode = $isdebug;

        return $this;
    }

    public function delete(int $id = 0)
    {
        if (empty($this->queryParams['table'])) {
            return 0;
        }
        if (is_numeric($id) && $id) {
            $pk = $this->getPK();
            if (!$pk) {
                return 0;
            }
            $this->where([$pk => $id]);
        } else {
            if (!$this->queryParams['where'] && $id !== true) {
                return 0;
            }
        }
        $this->buildSqlConf();

        $table = $this->queryParams['table'];
        $where = $this->queryParams['where'];
        $join  = $this->queryParams['join'];
        if ($join) {
            return $this->exec('DELETE ' . $table . '.* FROM ' . $table . $join . $where);
        } else {
            return $this->exec('DELETE FROM ' . $table . $where);
        }

    }

    public function error()
    {
        return $this->errors;
    }

    /**
     * 执行更新添加操作
     * @DateTime 2019-10-04
     * @Author   mokuyu
     *
     * @param  [type]   $sql   [description]
     * @param  [type]   $param [description]
     * @return [type]
     */
    public function exec(string $sql, array $param = [])
    {
        //是否使用了事务
        $isTransaction = false;
        try {
            if ($param) {
                $this->setBindParam($param);
            }
            $hasParam = $this->bindParam ? true : false;

            if ($this->isFetchSql) {
                $this->isFetchSql = false;
                $redata           = $this->greateSQL($sql, $this->bindParam);
                $this->initQueryParams();

                return $redata;
            }
            if ($this->debugMode) {
                die($this->greateSQL($sql, $this->bindParam));
            }
            $t1     = microtime(true);
            $result = false;
            if ($hasParam) {
                $sth = $this->pdo->prepare($sql);

                if (count($this->bindParam) == count($this->bindParam, 1)) {
                    $sth->execute($this->bindParam);
                } else {
                    //批量执行操作
                    $this->beginTransaction();
                    $isTransaction = true;
                    foreach ($this->bindParam as $key => $value) {
                        $sth->execute($value);
                    }
                    $isTransaction = false;
                    $this->commit();
                }
                $result = $sth->rowCount();
            } else {
                $result = $this->pdo->exec($sql);
            }
            $t2 = microtime(true);
            // $rtime = str_pad((round(($t2 - $t1), 6)) . '', 8, '0');
            $this->appendSqlLogs(($t2 - $t1), $sql, $this->bindParam);
            //因为exec执行的命令不一定会有影响的行数,下面判断执行的状态码
            if (!$result) {
                $err = $this->pdo->errorInfo();
                if ($err[0] === '00000' || $err[0] === '01000') {
                    $result = true;
                } else {
                    $this->errors[] = $this->pdo->errorInfo()[2];
                    $this->showError(end($this->errors));
                    $result = false;
                }
            }
            $this->initQueryParams();

            return $result;
        } catch (PDOException $e) {
            $isTransaction && $this->rollback();
            throw $e;
        }

        return 0;
    }

    public function fetchSql(bool $bo = true)
    {
        $this->isFetchSql = $bo;

        return $this;
    }

    public function field($field)
    {
        $this->queryParams['field'] = $field;

        return $this;
    }

    /**
     * 设置字段映射
     * @authname [权限名字]     0
     * @DateTime 2019-10-16
     * @Author   mokuyu
     *
     * @param  array    $map [description]
     * @return [type]
     */
    public function fieldMap(array $map)
    {
        $this->fieldMap = $map;

        return $this;
    }

    /**
     * 设置字段风格
     * @authname [权限名字]     0
     * @DateTime 2019-10-16
     * @Author   mokuyu
     *
     * @param  integer  $type [description]
     * @return [type]
     */
    public function fieldMode(int $type = 0)
    {
        if ($type > 2 || $type < 0) {
            throw new InvalidArgumentException('fieldMode must be numeric(0,1,2)!');
        }
        $this->temFieldModeTem = $type;

        return $this;
    }

    /**
     * 对指定字段进行运算更新
     * @DateTime 2019-11-01
     * @Author   mokuyu
     *
     * @param  string      $field     [description]
     * @param  int|integer $num       [description]
     * @param  string      $operation [description]
     * @return [type]
     */
    public function fieldOperation(string $field, int $num = 0, string $operation = '+')
    {
        $oper = ['+', '-', '*', '/'];
        if (!in_array($operation, $oper)) {
            return false;
        }
        $this->buildSqlConf();
        $table = $this->queryParams['table'];
        $where = $this->queryParams['where'];
        if (empty($table) || empty($where)) {
            return false;
        }

        return $this->exec('UPDATE ' . $table . ' SET ' . $field . '=' . $field . $operation . $num . ' ' . $where);
    }

    public function forceIndex($field)
    {
        $this->queryParams['forceIndex'] = $field;

        return $this;
    }

    /**
     * 取数据如果字段是一个的话直接返回这个字段的值，如果是一行记录的话就返回一个数组
     * @return [type] [description]
     */
    public function get(int $id = 0)
    {
        $this->limit(1);
        //这个列要放这里取要不然请求过后配置就被清空啦
        if (empty($this->queryParams['table'])) {
            return false;
        }
        //下面使用主键来查询
        $pk = $this->getPK();
        if ($pk && $id) {
            $this->queryParams['where'] = [];
            $this->where([$pk => $id]);
        }
        $this->buildSqlConf();
        //处理好后把这个字段保存下来,不然下面执行过后数据会被重置
        $columns = $this->queryParams['field'];
        $sql     = $this->buildSelect();
        $query   = $this->query($sql);
        if (!($query instanceof PDOStatement)) {
            return $query ?: '';
        }
        //如果列为字符串类型并且不为*
        $is_single_column = (is_string($columns) && strpos($columns, ',') === false && $columns !== '*');

        if ($query) {
            $data = $query->fetchAll(PDO::FETCH_ASSOC);
            if (isset($data[0])) {
                if ($is_single_column) {
                    //这个地方要处理几种情况
                    if (strpos($columns, ' AS ') !== false) {
                        //替换掉字段中的引号和 *** as 等字符
                        $columns = preg_replace(['/' . $this->yinhao . '/', '/.*? AS /'], '', $columns);
                    } else if (preg_match('/^[a-zA-Z0-9_\.' . $this->yinhao . ']+$/', $columns, $mat)) {
                        //判断是不是合法的字段项，如果有表名去掉表名
                        $columns = preg_replace(['/' . $this->yinhao . '/', '/^[\w]*\./i'], '', $columns);
                    }
                    // $columns=str_replace('')

                    return $data[0][$columns];
                } else {
                    return $data[0];
                }
            } else {
                return false;
            }
        } else {
            return false;
        }
    }

    /**
     * 取当前表的所有字段
     * @DateTime 2018-04-27
     * @Author   mokuyu
     *
     * @return [type]
     */
    public function getFields()
    {
        try {
            if (empty($this->queryParams['table'])) {
                return [];
            }
            // $table_name = str_replace($this->yinhao, '', $this->queryParams['srcTable']);
            $table_name = $this->prefix . $this->parseTable($this->queryParams['srcTable']);
            $fieldArr   = [];
            $ckey       = $table_name . '_fields_';
            switch ($this->databaseType) {
                case 'mysql':
                    $sql = 'DESC ' . $this->tablePrefix($this->queryParams['srcTable']);
                    $ckey .= md5($sql);
                    $fieldArr = $this->cacheAction($ckey);
                    if ($fieldArr === null) {
                        $tm = $this->pdo->prepare($sql);
                        $tm->execute();
                        $fieldArr = $tm->fetchAll(PDO::FETCH_COLUMN);
                    }
                    break;
                case 'sqlite':
                    $sql = 'pragma table_info (\'' . $table_name . '\')';

                    $ckey .= md5($sql);
                    $fieldArr = $this->cacheAction($ckey);
                    if ($fieldArr === null) {
                        $info = $this->pdo->query($sql);
                        if ($info) {
                            $info = $info->fetchAll(PDO::FETCH_ASSOC);
                            foreach ($info as $key => $value) {
                                $fieldArr[] = $value['name'];
                            }
                        }
                    }
                case 'pgsql':
                    $sql = 'select * from information_schema.columns where table_schema=\'public\' and table_name=\'' . $table_name . '\';';
                    $ckey .= md5($sql);
                    $fieldArr = $this->cacheAction($ckey);
                    if ($fieldArr === null) {
                        $info = $this->pdo->query($sql);
                        if ($info) {
                            $info = $info->fetchAll(PDO::FETCH_ASSOC);
                            foreach ($info as $key => $value) {
                                $fieldArr[] = $value['column_name'];
                            }
                        }

                    }
            }
            $fieldArr = $fieldArr ?: [];
            $this->cacheAction($ckey, $fieldArr);

            return $fieldArr;
        } catch (PDOException $e) {
            throw $e;
        }
    }

    public function getLastError()
    {
        return end($this->errors);
    }

    public function getLastSql()
    {
        return end($this->logs);
    }

    public function getPDO(): PDO
    {
        return $this->pdo;
    }

    /**
     * 取当前数据库的主键
     */
    public function getPK()
    {
        try {
            if (empty($this->queryParams['table'])) {
                return '';
            }
            // $table_name  = str_replace($this->yinhao, '', $this->queryParams['table']);
            $table_name  = $this->prefix . $this->parseTable($this->queryParams['srcTable']);
            $primaryName = '';
            $ckey        = $table_name . '_primaryid_';
            switch ($this->databaseType) {
                case 'mysql':
                    $sql = 'select COLUMN_NAME from information_schema.KEY_COLUMN_USAGE where TABLE_SCHEMA=\'' . $this->databaseName . '\' and TABLE_NAME=\'' . $table_name . '\'';
                    $ckey .= md5($sql);
                    $primaryName = $this->cacheAction($ckey);
                    //已经查询过并且没有主键的情况直接返回
                    if ($primaryName === null) {
                        $info = $this->pdo->query($sql);
                        if ($info) {
                            $info        = $info->fetchAll(PDO::FETCH_ASSOC);
                            $primaryName = isset($info[0]) ? $info[0]['COLUMN_NAME'] : '';
                        }
                    }

                    break;
                case 'sqlite':
                    $sql = 'pragma table_info (\'' . $table_name . '\')';
                    $ckey .= md5($sql);
                    $primaryName = $this->cacheAction($ckey);
                    //已经查询过并且没有主键的情况直接返回
                    if ($primaryName === null) {
                        $info = $this->pdo->query($sql);
                        if ($info) {
                            $info = $info->fetchAll(PDO::FETCH_ASSOC);
                            foreach ($info as $key => $value) {
                                if ($value['pk'] == 1) {
                                    $primaryName = $value['name'];
                                    break;
                                }
                            }
                        }
                    }
                case 'pgsql':
                    $sql = <<<eot
select pg_constraint.conname as pk_name,pg_attribute.attname as colname,pg_type.typname as typename from
pg_constraint  inner join pg_class
on pg_constraint.conrelid = pg_class.oid
inner join pg_attribute on pg_attribute.attrelid = pg_class.oid
and  pg_attribute.attnum = pg_constraint.conkey[1]
inner join pg_type on pg_type.oid = pg_attribute.atttypid
where pg_class.relname = '{$table_name}'
and pg_constraint.contype='p'
eot;
                    $ckey .= md5($sql);
                    $primaryName = $this->cacheAction($ckey);
                    //已经查询过并且没有主键的情况直接返回
                    if ($primaryName === null) {
                        $info = $this->pdo->query($sql);
                        if ($info) {
                            $info = $info->fetchAll(PDO::FETCH_ASSOC);
                            foreach ($info as $key => $value) {
                                $primaryName = $value['colname'];

                                break;
                            }
                        }

                    }
                    break;
            }
            $primaryName = $primaryName ?: '';
            $this->cacheAction($ckey, $primaryName);

            return $primaryName;
        } catch (PDOException $e) {
            throw $e;
        }
    }

    public function getQueryParams(): array
    {
        return $this->queryParams;
    }

    /**
     * 返回生成的条件语句
     * @DateTime 2019-10-04
     * @Author   mokuyu
     *
     * @param  [type]   $data [description]
     * @return [type]
     */
    public function getWhere(array $data = [])
    {
        if ($data) {
            $this->queryParams['where'] = $data;
        }
        $this->buildWhere();
        $redata = [$this->queryParams['where'], $this->bindParam];
        $this->initQueryParams();

        return $redata;
    }

    public function group(string $data)
    {
        $this->queryParams['group'] = $data;

        return $this;
    }

    public function has()
    {
        $this->queryParams['LIMIT'] = ' LIMIT 1';
        if (empty($this->queryParams['table'])) {
            return false;
        }
        $this->buildSqlConf();
        $sql   = 'SELECT EXISTS(' . $this->buildSelect() . ')';
        $query = $this->query($sql);
        if (!($query instanceof PDOStatement)) {
            return $query ?: false;
        }
        //取下一行,0列的数据

        return $query->fetchColumn(0) == 1;
    }

    public function info()
    {
        $val = $this->cacheAction('db_version_info');
        if ($val) {
            return $val;
        }
        $output = [
            'server'     => 'SERVER_INFO',
            'driver'     => 'DRIVER_NAME',
            'client'     => 'CLIENT_VERSION',
            'version'    => 'SERVER_VERSION',
            'connection' => 'CONNECTION_STATUS',
        ];

        foreach ($output as $key => $value) {
            $output[$key] = $this->pdo->getAttribute(constant('PDO::ATTR_' . $value));
        }
        $this->cacheAction('db_version_info', $output);

        return $output;
    }

    /**
     * 解析join组合的布格尼查询语句
     * @param  [type] $data           [description]
     * @return [type] [description]
     */
    public function join(array $data)
    {
        $this->queryParams['join'] = $data;

        return $this;
    }

    public function limit($start, $end = null)
    {
        if (is_null($end)) {
            if (strpos($start . '', ',') !== false) {
                list($start, $end) = explode(',', $start);
            } else if (is_array($start) && count($start) >= 2) {
                list($start, $end) = $start;
            } else {
                $end   = $start;
                $start = 0;
            }
        }
        $data = '';
        switch ($this->databaseType) {
            case 'mysql':
            // $data = ' LIMIT ' . $start . ' ,' . $end;
            // break;
            case 'sqlite':
            case 'pgsql':
                $data = ' LIMIT ' . $end . ' OFFSET ' . $start;
                break;
            case 'mssql':  //12c
            case 'oracle': //2012 11g
                $data = ' OFFSET ' . $start . ' ROWS FETCH NEXT ' . $end . ' ROWS ONLY';
                break;
        }

        $this->queryParams['limit'] = $data;

        return $this;
    }

    public function log()
    {
        return $this->logs;
    }

    public function max(...$field)
    {
        return $this->summary('MAX', $field);
    }

    public function min(...$field)
    {
        return $this->summary('MIN', $field);
    }

    public function order($data)
    {
        $this->queryParams['order'] = $data;

        return $this;
    }

    /**
     * [query description]
     * @DateTime 2019-05-02
     * @Author   mokuyu
     *
     * @param  [type]   $sql           [description]
     * @param  boolean  $isReturnArray 是否直接返回数组,如果这个是数组的话默认设置成要绑定的参数并返回数组数据
     * @return [type]
     */
    public function query(string $sql, array $param = [])
    {
        try {
            $isReturnData = false;
            if ($param) {
                $this->setBindParam($param);
            }
            //这种情况是直接执行sql语句
            if (!$this->queryParams['table']) {
                $isReturnData = true;
            }
            $hasParam = $this->bindParam ? true : false;

            if ($this->isFetchSql) {
                $this->isFetchSql = false;
                $redata           = $this->greateSQL($sql, $this->bindParam);
                $this->initQueryParams();

                return $redata;
            }
            if ($this->debugMode) {
                die($this->greateSQL($sql, $this->bindParam));
            }
            $this->initReadPDO();
            $pdo = $this->pdo;
            if ($this->pdoRead !== null && $this->pdoRead !== false) {
                $pdo = $this->pdoRead;
            }
            $t1    = microtime(true);
            $query = null;
            if ($hasParam) {
                $query = $pdo->prepare($sql);
                $query->execute($this->bindParam);

            } else {
                $query = $pdo->query($sql);
            }
            $t2 = microtime(true);
            // $rtime = str_pad((round(($t2 - $t1), 6)) . '', 8, '0');
            $this->appendSqlLogs(($t2 - $t1), $sql, $this->bindParam);
            if ($pdo->errorCode() != '00000') {
                $this->errors[] = $pdo->errorInfo()[2];
                $this->showError(end($this->errors));
            }
            $this->initQueryParams();
            if ($isReturnData) {
                if ($query) {
                    $da = $query->fetchAll(PDO::FETCH_ASSOC);
                }

                return $da ?: [];
            } else {
                return $query;
            }
        } catch (PDOException $e) {
            throw $e;
        }
    }

    public function rand()
    {
        $this->queryParams['rand'] = true;

        return $this;
    }

    /**
     * 事务回滚
     * @DateTime 2019-04-13
     * @Author   mokuyu
     *
     * @return [type]
     */
    public function rollback()
    {
        $this->pdo->rollBack();
    }

    /**
     * 查询数据返回一个二维数组
     * @return [type] [description]
     */
    public function select()
    {
        $this->buildSqlConf();
        if (empty($this->queryParams['table'])) {
            return false;
        }
        $sql   = $this->buildSelect();
        $query = $this->query($sql);
        //调试时返回这些
        if (!($query instanceof PDOStatement)) {
            return $query ?: [];
        }

        return $query->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * 修改绑定的参数
     * @DateTime 2019-11-05
     * @Author   mokuyu
     *
     * @param array $value [description]
     */
    public function setBindParam(array $value): void
    {
        $this->bindParam = $value;
    }

    /**
     * 设置缓存对象
     * @DateTime 2019-11-05
     * @Author   mokuyu
     *
     * @param CacheInterface $obj [description]
     */
    public function setCache(CacheInterface $obj): void
    {
        $this->cache = $obj;
    }

    public function setDec(string $field, int $num = 1)
    {
        return $this->fieldOperation($field, $num, '-');
    }

    public function setInc(string $field, int $num = 1)
    {
        return $this->fieldOperation($field, $num, '+');
    }

    public function sum(...$field)
    {
        return $this->summary('SUM', $field);
    }

    public function table(string $name)
    {
        $this->queryParams['table']    = $name;
        $this->queryParams['srcTable'] = $name;

        return $this;
    }

    public function tableMode(int $type = 0)
    {
        if ($type > 2 || $type < 0) {
            throw new InvalidArgumentException('tableMode must be numeric(0,1,2)!');
        }
        $this->temTableMode = $type;

        return $this;
    }

    /**
     * 使用回调执行一个事务
     * @DateTime 2019-08-19
     * @Author   mokuyu
     *
     * @param  \Closure $callback [description]
     * @return [type]
     */
    public function transaction(\Closure $callback)
    {
        $this->pdo->beginTransaction();
        // We'll simply execute the given callback within a try / catch block
        // and if we catch any exception we can rollback the transaction
        // so that none of the changes are persisted to the database.
        try {
            $result = $callback($this);

            $this->pdo->commit();
        }

        // If we catch an exception, we will roll back so nothing gets messed
        // up in the database. Then we'll re-throw the exception so it can
        // be handled how the developer sees fit for their applications.
         catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }

        return $result;
    }

    public function update(array $data)
    {
        if (empty($this->queryParams['table'])) {
            return 0;
        }
        if (empty($this->queryParams['where'])) {
            $pk = $this->getPK();
            if ($pk) {
                if (isset($data[$pk])) {
                    $this->where([$pk => $data[$pk]]);
                    unset($data[$pk]); //删除对主键的设置
                } else {
                    return 0;
                }
            } else {
                return 0;
            }
        }
        $this->buildSqlConf();
        //取表的所有字段
        $table_fields = $this->getFields();
        $fields       = [];
        foreach ($data as $key => $value) {
            $info = $this->parseFormatField($key);
            if (!in_array($info['field'], $table_fields)) {
                continue;
            }
            //字段+ - * / 本字段算术运算
            preg_match('/([\w]+)(\[(\+|\-|\*|\/)\])?/i', $info['field'], $match);
            if (isset($match[3])) {
                if (is_numeric($value)) {
                    $fields[] = $this->joinField($match[1]) . ' = ' . $this->joinField($match[1]) . ' ' . $match[3] . ' ' . $value;
                }
            } else {
                //如果join不为空的话就把字段默认加上第一个表为前缀
                if ($this->queryParams['join'] && !$info['table']) {
                    $info['table'] = trim($this->queryParams['table'], $this->yinhao);
                }
                $col    = ':' . $info['field'];
                $column = $this->yinhao . $info['field'] . $this->yinhao;
                if ($info['table']) {
                    $col .= '_' . $info['table'];
                    $column = $this->yinhao . $info['table'] . $this->yinhao . '.' . $column;
                }
                if (is_null($value)) {
                    $fields[] = $column . ' = NULL';
                } else if (is_object($value) || is_array($value)) {
                    preg_match("/\(JSON\)\s*([\w]+)/i", $key, $column_match);
                    $this->appendBindParam($col,
                        isset($column_match[0]) ? json_encode($value) : serialize($value)
                    );

                    $fields[] = $column . ' = ' . $col;
                } else if (is_bool($value)) {
                    $this->appendBindParam($col, ($value ? '1' : '0'));
                    $fields[] = $column . ' = ' . $col;
                } else if (is_integer($value) || is_double($value) || is_string($value)) {
                    $this->appendBindParam($col, $value);
                    $fields[] = $column . ' = ' . $col;
                } else {
                    $this->appendBindParam($col, $value);
                    $fields[] = $column . ' = ' . $col;
                }
            }
        }

        return $this->exec('UPDATE ' . $this->queryParams['table'] . ' ' . $this->queryParams['join'] . ' SET ' . implode(', ', $fields) . $this->queryParams['where']);
    }

    public function where($data)
    {
        $_wh = [];
        if (is_Array($data)) {
            $_wh = $data;
        } else {
            $_wh['_sql'] = '(' . $data . ')';
        }
        if ($_wh) {
            if ($this->queryParams['where']) {
                $this->queryParams['where'] = array_merge($this->queryParams['where'], $_wh);
            } else {
                $this->queryParams['where'] = $_wh;
            }
        }

        return $this;

    }

    public function whereOr($data)
    {
        $_wh = [];
        if (is_Array($data)) {
            $_wh = $data;
        } else {
            $_wh['_sql'] = '(' . $data . ')';
        }
        if ($_wh) {
            $this->queryParams['where']['or'] = $_wh;
        }

        return $this;
    }

    protected function appendSqlLogs(float $rtime, string $sql, array $params): void
    {
        $class = $rtime > 1 ? 'style="color:#f00";' : '';
        $rtime = str_pad((round($rtime, 6)) . '', 8, '0');
        if (PHP_SAPI == 'cli') {
            $this->logs[] = '[' . $rtime . 's] ' . $sql;
        } else {
            $this->logs[] = '【<span ' . $class . '>' . $rtime . 's</span>】' . $sql;
        }
    }

    protected function buildField(): void
    {
        $field = $this->queryParams['field'];
        if (!$field) {
            $field = '*';
        }
        $this->queryParams['field'] = $this->parseSelectFields($field);
    }

    protected function buildForceIndex(): void
    {
        $index = $this->queryParams['forceIndex'];
        if ($index) {
            $info = $this->parseFormatField($index);
            switch ($this->databaseType) {
                case 'mysql':
                    $this->queryParams['forceIndex'] = ' FORCE INDEX (' . $info['field'] . ') ';
                    break;
                case 'sqlite':
                    $this->queryParams['forceIndex'] = ' INDEXED BY ' . $info['field'] . ' ';
                    break;
                case 'oracle':
                    $this->queryParams['forceIndex'] = ' /*+index(' . $info['table'] . ' ' . $info['field'] . ')*/ ';
                    break;
                default:
                    $this->queryParams['forceIndex'] = '';
                    break;
            }

        }
    }

    protected function buildGroup()
    {
        $data = $this->queryParams['group'];
        if ($data) {
            $this->queryParams['group'] = ' GROUP BY ' . $this->joinField($data, false);
        }
    }

    protected function buildJoin()
    {
        $data = $this->queryParams['join'];
        if (!$data) {
            $this->queryParams['join'] = '';

            return;
        }
        //这个地方不能排序,表的联接跟顺序有关
        $table = $this->queryParams['table'];
        if ($table == '') {
            throw new PDOException('table cannot empty when join is not empty.');
        }
        $join_key = is_array($data) ? array_keys($data) : null;

        if (
            isset($join_key[0]) &&
            strpos($join_key[0], '[') === 0
        ) {
            $table_join = [];

            $join_array = [
                '>'  => 'LEFT',
                '<'  => 'RIGHT',
                '<>' => 'FULL',
                '><' => 'INNER',
            ];

            foreach ($data as $sub_table => $relation) {
                preg_match('/(\[(\<|\>|\>\<|\<\>)\])?([a-zA-Z0-9_\-]*)\s?(\(([a-zA-Z0-9_\-]*)\))?/', $sub_table, $match);

                if ($match[2] != '' && $match[3] != '') {
                    $joinString    = $join_array[$match[2]];
                    $joinTableName = $this->prefix . $this->parseName($match[3]);
                    if (is_string($relation)) {
                        $info     = $this->parseFormatField($relation);
                        $relation = 'USING (' . $this->yinhao . $info['field'] . $this->yinhao . ')';
                    }

                    if (is_array($relation)) {
                        if (isset($relation[0])) {
                            //数字数组形式
                            $newarr = [];
                            foreach ($relation as $key => $value) {
                                $newarr[] = $this->joinField($value, false);
                            }
                            $relation = 'USING (' . implode(',', $newarr) . ')';
                        } else {
                            //关联数组形式
                            $joins = [];
                            foreach ($relation as $key => $value) {
                                $kinfo = $this->parseFormatField($key);
                                $vinfo = $this->parseFormatField($value);
                                //键 直接使用主表
                                if (!$kinfo['table']) {
                                    $kinfo['table'] = trim($table, $this->yinhao);
                                }
                                //值如果没有设置表名就默认为连接的表名
                                if (!$vinfo['table']) {
                                    $vinfo['table'] = $joinTableName;
                                }

                                $joins[] = $this->joinField($kinfo, false) . ' = ' . $this->joinField($vinfo, false);
                            }

                            $relation = 'ON ' . implode(' AND ', $joins);
                        }
                    }
                    $table_name = $this->tablePrefix($match[3]) . ' ';
                    if (isset($match[5])) {
                        $table_name .= 'AS ' . $this->tablePrefix($match[5]) . ' ';
                    }

                    $table_join[] = $joinString . ' JOIN ' . $table_name . $relation;
                }
            }
            $this->queryParams['join'] = ' ' . implode(' ', $table_join);
        }
    }

    protected function buildOrder()
    {
        if ($this->queryParams['rand'] === true) {
            $order = ' RANDOM() ';
            $type  = $this->databaseType;
            if ($type == 'mysql') {
                $order = ' RAND() ';
            } else if ($type == 'msssql') {
                $order = ' NEWID() ';
            }
            $this->queryParams['order'] = ' ORDER BY' . $order;

            return $this;
        }

        $data = $this->queryParams['order'];
        if (!$data) {
            return $this;
        }
        if (is_string($data)) {
            $data = explode(',', trim($data));
        }
        foreach ($data as $key => $value) {
            //如果在字段不符合字段格式,可能是使用啦汉字排序等函数,原样不动
            if (strpos($value, '(') === false) {
                $arr    = preg_split('/\s+/', $value);
                $info   = $this->parseFormatField($arr[0]);
                $field  = $this->joinField($info, false);
                $fields = $this->getFields();
                if ($this->queryParams['join']
                    && in_array($info['field'], $fields)
                    && !$info['table']) {
                    $field = $this->queryParams['table'] . '.' . $field;
                }
                $data[$key] = $field . ' ' . strtoupper((isset($arr[1]) ? $arr[1] : ''));
            }

        }
        $data = implode(',', $data);
        if ($data) {
            $this->queryParams['order'] = ' ORDER BY ' . $data;
        }

    }

    /**
     * 构建一个pdo对象,代码来自medoo
     * @DateTime 2019-11-05
     * @Author   mokuyu
     *
     * @param  array    $options [description]
     * @return [type]
     */
    protected function buildPDO(array $options): PDO
    {
        try {
            $commands = [];
            $dsn      = '';

            if (is_array($options)) {
                foreach ($options as $option => $value) {
                    $option        = $this->parseName($option, 2);
                    $this->$option = $value;
                }
            } else {
                return null;
            }
            if (isset($this->port) && is_int($this->port * 1)) {
                $port = $this->port;
            }

            $type    = strtolower($this->databaseType);
            $is_port = isset($port);

            if (isset($options['prefix'])) {
                $this->prefix = $options['prefix'];
            }

            switch ($type) {
                case 'mariadb':
                    $type = 'mysql';

                case 'mysql':
                    if ($this->socket) {
                        $dsn = $type . ':unix_socket=' . $this->socket . ';dbname=' . $this->databaseName;
                    } else {
                        $dsn = $type . ':host=' . $this->server . ($is_port ? ';port=' . $port : '') . ';dbname=' . $this->databaseName;
                    }

                    // Make MySQL using standard quoted identifier
                    $commands[] = 'SET SQL_MODE=ANSI_QUOTES';
                    break;

                case 'pgsql':
                    $dsn = $type . ':host=' . $this->server . ($is_port ? ';port=' . $port : '') . ';dbname=' . $this->databaseName;
                    break;

                case 'sybase':
                    $dsn = 'dblib:host=' . $this->server . ($is_port ? ':' . $port : '') . ';dbname=' . $this->databaseName;
                    break;

                case 'oracle':
                    $dbname = $this->server ?
                    '//' . $this->server . ($is_port ? ':' . $port : ':1521') . '/' . $this->databaseName :
                    $this->databaseName;

                    $dsn = 'oci:dbname=' . $dbname . ($this->charset ? ';charset=' . $this->charset : '');
                    break;

                case 'mssql':
                    $dsn = strstr(PHP_OS, 'WIN') ?
                    'sqlsrv:server=' . $this->server . ($is_port ? ',' . $port : '') . ';database=' . $this->databaseName :
                    'dblib:host=' . $this->server . ($is_port ? ':' . $port : '') . ';dbname=' . $this->databaseName;

                    // Keep MSSQL QUOTED_IDENTIFIER is ON for standard quoting
                    $commands[] = 'SET QUOTED_IDENTIFIER ON';
                    break;

                case 'sqlite':
                    $dsn            = $type . ':' . $this->databaseFile;
                    $this->username = null;
                    $this->password = null;
                    break;
            }

            if (
                in_array($type, ['mariadb', 'mysql', 'pgsql', 'sybase', 'mssql']) &&
                $this->charset
            ) {
                $commands[] = "SET NAMES '" . $this->charset . "'";
            }
            if (!$dsn) {
                throw new PDOException('database dsn is not Empty', 1);

            }
            $pdo = new PDO(
                $dsn,
                $this->username,
                $this->password,
                $this->options
            );

            foreach ($commands as $value) {
                $pdo->exec($value);
            }

            return $pdo;
        } catch (PDOException $e) {
            throw $e;
        }
    }

    /**
     * 根据当前配置生成一个select语句
     * @return [type] [description]
     */
    protected function buildSelect(): string
    {
        $map    = $this->queryParams;
        $table  = $map['table'];
        $where  = $map['where'];
        $order  = $map['order'];
        $limit  = $map['limit'];
        $join   = $map['join'];
        $field  = $map['field'];
        $group  = $map['group'];
        $index  = $map['forceIndex'];
        $temsql = '';
        if ((empty($table) && empty($join)) || (empty($field) && empty($join))) {
            return '';
        }
        $sql = '';
        switch ($this->databaseType) {
            case 'mysql':
            case 'sqlite':
                $sql = 'SELECT ' . $field . ' FROM ' . $table . $index . ' ' . $join . ' ' . $where . ' ' . $group . ' ' . $order . ' ' . $limit;
                break;
            case 'oracle':
                $sql = 'SELECT ' . $index . $field . ' FROM ' . $table . ' ' . $join . ' ' . $where . ' ' . $group . ' ' . $order . ' ' . $limit;
        }

        return $sql;

    }

    protected function buildSqlConf()
    {
        $this->buildTable();
        $this->buildField();
        $this->buildJoin();
        $this->buildWhere();
        $this->buildOrder();
        $this->buildGroup();
        $this->buildForceIndex();

    }

    protected function buildTable()
    {
        $this->queryParams['table'] = $this->tablePrefix($this->queryParams['table']);

        return $this;
    }

    /**
     * 解析where条件格式
     * @param  [type] $data           [description]
     * @return [type] [description]
     */
    protected function buildWhere(): void
    {
        $data = $this->queryParams['where'];
        if (!$data) {
            $this->queryParams['where'] = '';

            return;
        }
        //这里不能转为小写不然就没法加下划线啦
        // $data    = array_change_key_case($data);
        $anddata = [];
        $ordata  = [];
        $tdata   = '';
        if (isset($data['and'])) {
            $anddata = $data['and'];
            unset($data['and']);
        }
        if (isset($data['or'])) {
            $ordata = $data['or'];
            unset($data['or']);
        }
        $str1 = '';
        foreach ($anddata as $key => $value) {
            $ts = '';
            if ($key == '_sql') {
                $ts = '(' . $value . ')';
            } else {
                $ts = $this->parseOperator($key, $value);
            }
            $str1 .= empty($str1) ? $ts : (' AND ' . $ts);
        }
        $str2 = '';
        foreach ($ordata as $key => $value) {
            $ts = '';
            if ($key == '_sql') {
                $ts = '(' . $value . ')';
            } else {
                $ts = $this->parseOperator(preg_replace('/__\d+/', '', $key), $value);
            }
            $str2 .= empty($str2) ? $ts : (' OR ' . $ts);
        }
        $str3 = '';
        foreach ($data as $key => $value) {
            // $ts = $this->parseOperator($key, $value);
            $ts = '';
            if ($key == '_sql') {
                $ts = '(' . $value . ')';
            } else {
                $ts = $this->parseOperator($key, $value);
            }
            $str3 .= empty($str3) ? $ts : (' AND ' . $ts);
        }
        $arr = [];
        $str1 && ($arr[] = $str1);
        $str2 && ($arr[] = $str2);
        $str3 && ($arr[] = $str3);
        $resql = implode(') AND (', $arr);
        $resql = '(' . $resql . ')';
        $resql = $resql == '()' ? '' : $resql;
        if ($resql) {
            $this->queryParams['where'] = ' WHERE ' . $resql;

            return;
        }
        if (is_array($this->queryParams['where'])) {
            $this->queryParams['where'] = '';
        }

    }

    /**
     * 缓存数据库的一些信息,传数据库配置的时候可以把缓存对象传进来,如果没有传的话就默认不用缓存
     * @DateTime 2018-12-08
     * @Author   mokuyu
     *
     * @param  [type]   $key   [description]
     * @param  [type]   $value [description]
     * @return [type]
     */
    protected function cacheAction(string $key, $value = null)
    {
        $key = 'mokuyu.' . $key;
        if (!isset($this->cacheKey[$key])) {
            $this->cacheKey[$key] = true;
        }
        if ($this->debug) {
            return null;
        } else {
            if (is_null($this->cache)) {
                return null;
            } else {
                if (is_null($value)) {
                    return $this->cache->get($key);
                } else {
                    return $this->cache->set($key, $value);
                }

            }
        }
    }

    /**
     * 把数组转换成两个值绑定 between  :create_time_1  and   :create_time_2
     * @DateTime 2019-11-05
     * @Author   mokuyu
     *
     * @param  string   $column [description]
     * @param  array    $arr    [description]
     * @return [type]
     */
    protected function getArrayParam(string $column, array $arr): string
    {
        $column = trim($column, $this->yinhao);
        $repeat = '';
        foreach ($arr as $key => $val) {
            $col = $column . $key;
            $this->appendBindParam($col, $val);
            $repeat .= $col . ',';
        }

        return trim($repeat, ',');
    }

    /**
     * 组合sql和绑定参数为正常语句
     * @DateTime 2019-11-05
     * @Author   mokuyu
     *
     * @param  string   $sql   [description]
     * @param  array    $param [description]
     * @return [type]
     */
    protected function greateSQL(string $sql, array $param): string
    {
        if (count($param) !== count($param, 1)) {
            $param = $param[0];
        }
        foreach ($param as $key => $value) {
            $sql = preg_replace('/' . $key . '([^\w\d_])/', '\'' . $value . '\'$1', $sql);
        }

        return $sql;
    }

    /**
     * 重置查询参数
     * @authname [权限名字]     0
     * @DateTime 2019-10-05
     * @Author   mokuyu
     *
     * @return [type]
     */
    protected function initQueryParams(): void
    {
        //重置为全局风格
        $this->temFieldModeTem = $this->fieldMode;
        $this->temTableMode    = $this->tableMode;
        $this->fieldMap        = [];
        $this->queryParams     = [
            'table'      => '',
            'srcTable'   => '', //传入的原始表
            'join'       => [],
            'where'      => [],
            'order'      => '',
            'rand'       => false,
            'group'      => '',
            'limit'      => '',
            'field'      => '*',
            'data'       => '',
            //强制使用索引,mysql查询的时候用
            'forceIndex' => '',
        ];
        $this->bindParam = [];
    }

    /**
     * 初始化数据库读对象
     * @DateTime 2019-11-05
     * @Author   mokuyu
     *
     * @return [type]
     */
    protected function initReadPDO(): void
    {
        if ($this->pdoRead === false || !$this->read) {
            $this->pdoRead = false;

            return;
        }
        if ($this->pdoRead === null) {
            $this->pdoRead = $this->buildPDO($this->read);
        }
    }

    /**
     * 解析SELECT字段
     * 解析表是不是按指定的格式存储,并且把use.id这样的格式解析成`use`.`id`
     * @param  [type] $string         [description]
     * @return [type] [description]
     */
    protected function joinField($field, $isJoinAlias = true): string
    {
        if ($field == '*') {
            return $field;
        }
        $info = [];
        if (is_array($field)) {
            $info = $field;
        } else {
            $info = $this->parseFormatField($field);
        }
        //* 不能加引号,不然会报错
        $field = $info['field'] === '*' ? $info['field'] : $this->yinhao . $info['field'] . $this->yinhao;
        if ($info['table']) {
            $field = $this->yinhao . $info['table'] . $this->yinhao . '.' . $field;
        }
        if ($info['func']) {
            $field = $info['func'] . '(' . $field . ')';
        }
        if ($info['alias'] && $isJoinAlias) {
            $field .= ' AS ' . $this->yinhao . $info['alias'] . $this->yinhao;
        }

        return $field;
    }

    /**
     * 解析格式化字段为指定类型数组
     * @DateTime 2019-10-03
     * @Author   mokuyu
     *
     * @param  [type]   $field [description]
     * @return [type]
     */
    protected function parseFormatField(string $field): array
    {
        $field = str_replace($this->yinhao, '', $field);
        //字段信息数据
        $arr = [
            'table'         => '',
            //原始表(不带前缀的表名)
            'srcTable'      => '',
            'field'         => '',
            'func'          => '',
            'alias'         => '',
            'rightOperator' => '',
            // 'leftOperator'  => '',//这个暂时没用
            // 'isMap'         => false,
        ];
        //解析字段中 age[>]这一类的标识识,#使用数据库函数
        if (preg_match('/(#?)([\w\(\)\.\-]+)(\[(\>|\>\=|\<|\<\=|\!|\<\>|\>\<|\!?~)\])/i', $field, $match)) {
            $arr['field']         = $match[2];
            $arr['rightOperator'] = $match[4];
        }

        //查看是不是有数据表
        preg_match('/(\(JSON\)\s*|^#)?([a-zA-Z0-9_]*)\.([a-zA-Z0-9_]*)/', $field, $column_match);
        if (isset($column_match[2], $column_match[3])) {
            //有数据表的情况下
            $arr['srcTable'] = $this->parseName($column_match[2]);
            $arr['table']    = $this->prefix . $arr['srcTable'];
            $arr['field']    = $column_match[3];
        }
        //从函数中count(user)取出真正的字段字段值
        preg_match('/([^\s]+)\s*\(\s*([a-zA-Z0-9_\-\.\*]*?)\s*\)/', $field, $matfun);
        if (isset($matfun[1])) {
            $arr['field'] || ($arr['field'] = $matfun[2]); //字段
            $arr['func'] = $matfun[1];                     //函数
                                                           //从原有字符串中把 (user) 换成占位符
                                                           // $field_str = str_replace($matfun[0], '__ZHANWEIFU__', $field_str);
        }
        //如果匹配的话,填充的数组是一样的
        if (stripos($field, ' as ') !== false) {
            //正则出有as的这种情况
            preg_match('/([a-zA-Z0-9_\-\.\(\)]*)\s*as\s*([a-zA-Z0-9_\-\.\(\)]*)/i', $field, $match);
        } else {
            //正则出  User.name[nickname] 这种情况
            preg_match('/([a-zA-Z0-9_\-\.\(\)]*)\s*\[([a-zA-Z0-9_\-]*)\]/i', $field, $match);
        }
        if (isset($match[1], $match[2])) {
            // 符合User.name[nickname]/ as 这两种别名情况
            $arr['field'] || ($arr['field'] = $match[1]);
            $arr['alias'] = $match[2];
        }
        $arr['field'] || ($arr['field'] = $field);
        //转成真实字段
        if (isset($this->fieldMap[$arr['field']])) {
            $arr['alias'] || ($arr['alias'] = $arr['field']);
            $arr['field'] = $this->fieldMap[$arr['field']];
            // $arr['isMap'] = true;
        }
        //按字段模式转换
        if ($this->temFieldModeTem === 1) {
            $arr['field'] = $thsi->parseName($arr['field']);
        } else if ($this->temFieldModeTem === 2) {
            $arr['field'] = $thsi->parseName($arr['field'], 3);
        }

        return $arr;
    }

    /**
     * 驼峰和下划线命名互转
     * @DateTime 2019-10-06
     * @Author   mokuyu
     *
     * @param  [type]   $name [description]
     * @param  integer  $type 1:user_group，2:userGroup，3:UserGroup
     * @return [type]
     */
    protected function parseName(string $name, int $type = 1): string
    {
        if ($type === 1) {
            return strtolower(preg_replace('/([A-Z])/', '_$1', lcfirst($name)));
        } else {
            $name = str_replace('_', '', ucwords($name, '_'));

            return $type === 3 ? $name : lcfirst($name);
        }
    }

    /**
     * 解析每一个键值对的操作符和值
     * @param  [type] $field          [description]
     * @return [type] [description]
     */
    protected function parseOperator(string $field, $value): string
    {
        $tdata    = '';
        $info     = $this->parseFormatField($field);
        $operator = $info['rightOperator'];
        $column   = $this->yinhao . $info['field'] . $this->yinhao;
        $col      = ':' . $info['field'];
        if ($info['table']) {
            $column = $this->yinhao . $info['table'] . $this->yinhao . '.' . $column;
            $col .= '_' . $info['table'];
        }
        //如果有多表联合的情况,并且字段没有表前缀的话,判断第一个表里如果有这个字段就加上第一个表的前缀
        if ($this->queryParams['join'] && !$info['table']) {
            $fields = $this->getFields();
            if (in_array($info['field'], $fields)) {
                $column = $this->queryParams['table'] . '.' . $column;
            }
        }
        if ($operator) {
            //有快捷操作的标识符的情况下
            // $operator = $match[4];
            if ($operator == '!') {
                if (is_null($value)) {
                    $tdata = $column . ' IS NOT NULL';
                } else if (is_array($value)) {
                    $repeat = $this->getArrayParam($column, $value);
                    $tdata  = $column . ' NOT IN (' . $repeat . ')';
                } else if (is_integer($value) || is_double($value)) {
                    $this->appendBindParam($col, $value);
                    $tdata = $column . ' != ' . $col;
                } else if (is_bool($value)) {
                    $this->appendBindParam($col, ($value ? '1' : '0'));
                    $tdata = $column . ' != ' . $col;
                } else if (is_string($value)) {
                    $this->appendBindParam($col, $value);
                    $tdata = $column . ' != ' . $col;
                }
            }

            if ($operator == '<>' || $operator == '><') {
                if (is_array($value)) {
                    if ($operator == '><') {
                        $column .= ' NOT';
                    }
                    $col0 = $col . '0';
                    $col1 = $col . '1';

                    if (is_numeric($value[0]) && is_numeric($value[1])) {
                        $this->appendBindParam($col0, $value[0]);
                        $this->appendBindParam($col1, $value[1]);

                    } else {
                        $this->appendBindParam($col0, $value[0]);
                        $this->appendBindParam($col1, $value[1]);
                    }
                    $tdata = '(' . $column . ' BETWEEN ' . $col0 . ' AND ' . $col1 . ' )';
                }
            }

            if ($operator == '~' || $operator == '!~') {
                if (!$value) {
                    return '';
                }
                $val = [];
                if (is_string($value)) {
                    $val[] = $value;
                } else {
                    $val = $value;
                }
                $tdata = '';
                foreach ($val as $ke => $va) {
                    if (strpos($va, '%') === false) {
                        $va = $va . '%';
                    }
                    $tem_col = $col . $ke;
                    $this->appendBindParam($tem_col, $va);
                    if ($tdata) {
                        $tdata .= ' OR ' . $column . ($operator === '!~' ? ' NOT' : '') . ' LIKE ' . $tem_col;
                    } else {
                        $tdata = $column . ($operator === '!~' ? ' NOT' : '') . ' LIKE ' . $tem_col;
                    }
                }
                if (count($val) > 1) {
                    $tdata = ' ( ' . $tdata . ' ) ';
                }

            }

            if (in_array($operator, ['>', '>=', '<', '<='])) {

                if (is_numeric($value)) {
                    $this->appendBindParam($col, $value);
                } elseif (strpos($field, '#') === 0) {
                    $this->appendBindParam($col, $value);
                } else {
                    $this->appendBindParam($col, $value);
                }
                $condition = $column . ' ' . $operator . ' ' . $col;
                $tdata     = $condition;
            }
        } else {
            //字段中没有标识符直接使用
            $opArr = [
                'like' => 'LIKE',
                'in'   => 'in',
                'gt'   => '>',
                'lt'   => '<',
                'egt'  => '>=',
                'elt'  => '<=',
                'eq'   => '=',
                'neq'  => '!='];
            if (is_array($value) && isset($opArr[$value[0]])) {
                $tj  = $value[0];
                $val = $value[1];
                if ($tj == 'in') {
                    if (is_string($val)) {
                        $val = explode(',', $val);
                    }
                    $repeat = $this->getArrayParam($column, $val);
                    $tdata  = $column . ' IN (' . $repeat . ')';
                } else {
                    $this->appendBindParam($col, $val);
                    $tdata = $column . ' ' . $opArr[$tj] . ' ' . $col;
                }
            } else {
                if (is_null($value)) {
                    $tdata = $column . ' IS NULL';
                } else if (is_array($value)) {
                    $repeat = $this->getArrayParam($col, $value);
                    $tdata  = $column . ' IN (' . $repeat . ')';
                } else if (is_integer($value) || is_double($value)) {
                    $this->appendBindParam($col, $value);
                    $tdata = $column . ' = ' . $col;
                } else if (is_bool($value)) {
                    $this->appendBindParam($col, ($value ? '1' : '0'));
                    $tdata = $column . ' = ' . $col;
                } else if (is_string($value)) {
                    $this->appendBindParam($col, $value);
                    $tdata = $column . ' = ' . $col;
                } else {
                    $this->appendBindParam($col, $value);
                    $tdata = $column . ' = ' . $col;
                }
            }
        }

        return $tdata;
    }

    /**
     * 解析传进来的字段字符串或数组
     * @param  [type] &$columns       [description]
     * @return [type] [description]
     */
    protected function parseSelectFields($columns): string
    {
        if ($columns == '*') {
            return $columns;
        }

        if (is_string($columns)) {
            $columns = explode(',', $columns);
        }
        $stack = [];

        foreach ($columns as $key => $value) {
            $info = $this->parseFormatField($value);
            if ($this->queryParams['join']) {
                $fields = $this->getFields();
                if (in_array($info['field'], $fields) && !$info['table']) {
                    $info['table'] = trim($this->queryParams['table'], $this->yinhao);
                }
            }
            $field   = $this->joinField($info);
            $stack[] = $field;
        }

        return implode(',', $stack);
    }

    /**
     * 按指定风格解析出表名
     * @authname [权限名字]     0
     * @DateTime 2019-11-06
     * @Author   mokuyu
     *
     * @param  string   $table [description]
     * @return [type]
     */
    protected function parseTable(string $table): string
    {
        if ($this->temTableMode === 1) {
            $table = $this->parseName($table, 1);
        } elseif ($this->temTableMode === 2) {
            $table = $this->parseName($table, 3);
        }

        return $table;
    }

    /**
     * 显示错误,sql执行出错错误时会调用这里
     * @DateTime 2018-04-24
     * @Author   mokuyu
     *
     * @return [type]
     */
    protected function showError(string $str): void
    {

    }

    /**
     * 汇总函数计算
     * @DateTime 2019-11-01
     * @Author   mokuyu
     *
     * @return [type]
     */
    protected function summary(string $func, array $field)
    {
        if ($this->queryParams['field']) {
            $field = explode(',', $this->queryParams['field']);
        }
        $arr = ['COUNT', 'AVG', 'MAX', 'MIN', 'SUM'];
        if (!in_array($func, $arr) || !$this->queryParams['table']) {
            return 0;
        }
        if (count($field) == 1) {
            if (is_array($field[0])) {
                $field = $field[0];
            } else {
                $field = explode(',', $field[0]);
            }
        }
        $obj = $this;
        array_walk($field, function (&$value, $key) use ($func, $obj) {
            $info = $obj->parseFormatField($value);
            $fie  = $info['field'];
            if ($info['srcTable']) {
                $fie = $info['srcTable'] . '.' . $fie;
            }
            $value = $func . '(' . $fie . ') AS ' . ($info['field'] === '*' ? 'num' : $info['field']);
        });

        $this->field($field);
        if ($this->queryParams['group']) {
            //如果有分组去复的话会有多条记录,需要放到子sql中统计
            // $map  = $this->getWhere();
            $this->buildSqlConf();
            $sql   = $this->buildSelect();
            $query = $this->query('SELECT ' . implode(',', $field) . ' FROM (' . $sql . ') tem');
            $data  = $query->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $data = $this->select();
        }

        if (is_string($data) || is_numeric($data)) {
            return $data ?: 0;
        }
        if ($data) {
            if (count($field) === 1) {
                //单个字段的统计直接返回
                $info = $this->parseFormatField($field[0]);
                $fie  = $info['alias'] ?: $info['field'];

                return $data[0][$fie];
                // return array_sum(array_column($data, $fie));

            } else {
                //多个字段就返回数组
                return $data ?: 0;
            }
        } else {
            return 0;
        }
    }

    /**
     * 给表添加上前缀
     * @param  [type] $table          [description]
     * @return [type] [description]
     */
    protected function tablePrefix(string $table): string
    {
        return $this->yinhao . $this->prefix . $this->parseTable($table) . $this->yinhao;
    }
}
