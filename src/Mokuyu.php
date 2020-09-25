<?php
/**
 * Mokuyu database
 * https://www.zhaokeli.com
 * Version 1.0.0
 * Copyright 2020, Mokuyu
 * Released under the MIT license
 */
declare (strict_types = 1);

namespace mokuyu\database;

use Closure;
use Exception;
use InvalidArgumentException;
use PDO;
use PDOException;
use PDOStatement;
use Psr\SimpleCache\CacheInterface;

/**
 * @property PDO   pdoWrite
 * @property PDO   pdoRead
 * @property array dbConfig
 */
class Mokuyu
{
    /**
     * query中绑定的参数数组
     * @var array
     */
    protected $bindParam = [];

    /**
     * 缓存对象,要实现CacheInterface接口,保存表字段加快速度
     * @var CacheInterface
     */
    protected $cache = null;

    /**
     * 数据库数据集
     * @var [type]
     */
    protected $charset;

    /**
     * sqlite数据文件
     * @var [type]
     */
    protected $databaseFile;

    /**
     * 数据库名字
     * @var [type]
     */
    protected $databaseName;

    /**
     * 数据库连接相关信息
     * @var [type]
     */
    protected $databaseType;

    /**
     * 开启调式,关闭后如果有缓存缓存会缓存主键,表字段等信息
     * @var boolean
     */
    protected $debug = false;

    /**
     * 错误信息保存
     * @var array
     */
    protected $errors = [];

    /**
     * 缓存命中多少次
     * @var int
     */
    protected $cacheHits = 0;

    /**
     * 字段映射
     * 格式为 别名(查询)字段=>数据库真实字段
     * 场景：文章表中字段为create_time,但想使用add_time去查询,做映射后就可以使用add_time查询,不映射则会提示add_time不存在
     * @var [type]
     */
    protected $fieldMap
        = [
            //格式为 别名(查询)字段=>数据库真实字段
            // 'push_time' => 'create_time',
        ];

    /**
     * 设置当前数据表字段风格,传入的字段会转为此种风格后再去查询,fieldMap中设置的(别名/真实)字段同样会被转换
     * 0:原样不动，1:转换为下划线风格，2:转换为驼峰风格
     * @var null
     */
    protected $fieldMode = 0;

    /**
     * 数据库配置
     * @var array
     */
    protected $hostList = [];

    /**
     * 中断调试，会直接中断并输出当前sql语句
     * @var boolean
     */
    protected $isAbort = false;

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

    /**
     * pdo配置项
     * @var [type]
     */
    protected $options
        = [
            // 抛出 exceptions 异常。
            PDO::ATTR_ERRMODE           => PDO::ERRMODE_EXCEPTION,
            //将 NULL 转换成空字符串
            PDO::ATTR_ORACLE_NULLS      => PDO::NULL_TO_STRING,
            //禁用本地模拟prepare
            PDO::ATTR_EMULATE_PREPARES  => false,
            //长连接
            // PDO::ATTR_PERSISTENT        => true,
            //提取的时候将数值转换为字符串
            PDO::ATTR_STRINGIFY_FETCHES => false,
            //查询出来字段小写
            // PDO::ATTR_CASE              => PDO::CASE_NATURAL,
        ];

    /**
     * 数据库密码
     * @var [type]
     */
    protected $password;

    /**
     * 端口
     * @var [type]
     */
    protected $port;

    /**
     * 数据库前缀
     * @var [type]
     */
    protected $prefix;

    /**
     * 每次执行请求的SQL参数组合
     * @var array
     */
    protected $queryParams = [];

    /**
     * 数据库连接服务器
     * @var [type]
     */
    protected $server;

    // For SQLite
    protected $socket;

    /**
     * 数据表风格,把传入的表名转为下面
     * 前提:前缀还是要加的
     * 0:原样不动，1:转换为下划线风格，2:转换为驼峰风格
     * @var null
     */
    protected $tableMode = 1;

    /**
     * 用户名
     * @var [type]
     */
    protected $username;

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

    /**
     * 字段风格
     * @var integer
     */
    private $temFieldMode = 0;

    /**
     * 数据表风格
     * @var integer
     */
    private $temTableMode = 0;

    /**
     * 初始化连接
     * @DateTime 2019-11-05
     * @Author   mokuyu
     * @param array $config [description]
     */
    public function __construct(array $config)
    {
        foreach ($config as $option => $value) {
            $option        = $this->parseName($option, 2);
            $this->$option = $value;
        }
        // 解析配置
        $servers   = explode(',', $this->server);
        $ports     = explode(',', $this->port . '');
        $usernames = explode(',', $this->username);
        $passwords = explode(',', $this->password);
        foreach ($servers as $key => $value) {
            $arr                              = explode(':', trim($value));
            $this->hostList[$arr[1] ?? 'w'][] = [
                'server'   => $arr[0],
                'port'     => $ports[$key] ?? end($ports),
                'username' => $usernames[$key] ?? end($usernames),
                'password' => $password[$key] ?? end($passwords),
            ];
        }
        if (!$this->hostList['w']) {
            throw new PDOException('Write Db is not exist!', 1);
        }
        $this->temFieldMode = $this->fieldMode;
        $this->temTableMode = $this->tableMode;
        $this->dbConfig     = $config;
        $this->initQueryParams();
    }

    /**
     * 自动初始化连接
     * @authname [权限名字]     0
     * @DateTime 2019-12-11
     * @Author   mokuyu
     * @param    [type]   $name [description]
     * @return PDO [type]
     */
    public function __get($name)
    {
        if ($name === 'pdoWrite') {
            $this->pdoWrite = $this->buildPDO($this->hostList['w'][array_rand($this->hostList['w'])]);

            return $this->pdoWrite;
        }
        elseif ($name === 'pdoRead') {
            if (isset($this->hostList['r'])) {
                $this->pdoRead = $this->buildPDO($this->hostList['r'][array_rand($this->hostList['r'])]);
            }
            else {
                $this->pdoRead = $this->pdoWrite;
            }

            return $this->pdoRead;
        }

        return $this->$name;
        // throw new PDOException('Method is not exist: ' . $name, 1);
    }

    /**
     * 调试查询,程序会中断
     * @authname [name]       0
     * @DateTime 2019-12-31
     * @Author   mokuyu
     * @param bool $isAbort
     * @return Mokuyu [type]
     */
    public function abort(bool $isAbort = true)
    {
        $this->isAbort = $isAbort;

        return $this;
    }

    /**
     * 添加数据成功后返回添加成功的id
     * @authname [name]     0
     * @DateTime 2019-12-31
     * @Author   mokuyu
     * @param array $datas [description]
     * @return bool|false|int|mixed|string
     */
    public function add(array $datas)
    {

        return $this->insert($datas);
    }

    public function avg(...$field)
    {
        return $this->summary('AVG', $field);
    }

    /**
     * 开启事务
     * @DateTime 2019-04-13
     * @Author   mokuyu
     * @return void [type]
     */
    public function beginTransaction()
    {

        $this->pdoWrite->beginTransaction();
    }

    /**
     * 清理保存的缓存
     * @authname [权限名字]     0
     * @DateTime 2019-11-01
     * @Author   mokuyu
     * @return void [type]
     */
    public function clearCache()
    {
        $this->cache && $this->cache->deleteMultiple(array_keys($this->cacheKeys));
        $this->cacheKeys = [];
    }

    /**
     * 提交事务的操作
     * @DateTime 2019-04-13
     * @Author   mokuyu
     * @return void [type]
     */
    public function commit()
    {
        $this->pdoWrite->commit();
    }

    public function count(string $field = '*')
    {
        return $this->summary('COUNT', [$field]);
    }

    /**
     * 设置或获取调试状态
     * @authname [name]     0
     * @DateTime 2020-01-10
     * @Author   mokuyu
     * @param    [type]   $debug [description]
     * @return bool|null [type]
     */
    public function debug($debug = null)
    {
        if ($debug !== null) {
            $this->debug = $debug;
        }

        return $this->debug;

    }

    /**
     * 删除数据
     * @authname [name]      0
     * @DateTime 2020-02-17
     * @Author   mokuyu
     * @param int $id 可以为bool true删除所有数据,如果为int则为主键id
     * @return bool|false|int|string [type]
     */
    public function delete($id = 0)
    {

        if (empty($this->queryParams['table'])) {
            return 0;
        }
        if (!$this->queryParams['where'] && $id !== true) {
            if ($id === 0) {
                return 0;
            }
        }
        if ($id && is_numeric($id)) {
            $pk = $this->getPK();
            if (!$pk) {
                return 0;
            }
            $this->where([$pk => $id]);
        }
        $this->buildSqlConf();

        $table = $this->queryParams['table'];
        $where = $this->queryParams['where'];
        $join  = $this->queryParams['join'];
        if ($join) {
            return $this->exec('DELETE ' . $table . '.* FROM ' . $table . $join . $where);
        }
        else {
            return $this->exec('DELETE FROM ' . $table . $where);
        }

    }

    /**
     * 返回所有整个错误数组
     * @authname [name]     0
     * @DateTime 2019-12-31
     * @Author   mokuyu
     * @return array [type]
     */
    public function error()
    {
        return $this->errors;
    }

    /**
     * 执行更新添加操作
     * @DateTime 2019-10-04
     * @Author   mokuyu
     * @param string $sql
     * @param array  $param
     * @return bool|false|int|string [type]
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
            if ($this->isAbort) {
                die($this->greateSQL($sql, $this->bindParam));
            }
            $t1 = microtime(true);
            if ($hasParam) {
                $sth = $this->pdoWrite->prepare($sql);

                if (count($this->bindParam) == count($this->bindParam, 1)) {
                    $sth->execute($this->bindParam);
                }
                else {
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
            }
            else {
                $result = $this->pdoWrite->exec($sql);
            }
            $t2 = microtime(true);
            // $rtime = str_pad((round(($t2 - $t1), 6)) . '', 8, '0');
            $this->appendSqlLogs(($t2 - $t1), $sql, $this->bindParam);
            //因为exec执行的命令除了 select insert update外不一定会有影响的行数,下面判断执行的状态码
            if (!$result
                && stripos(trim($sql), 'select') !== 0
                && stripos(trim($sql), 'update') !== 0
                && stripos(trim($sql), 'insert') !== 0) {
                $err = $this->pdoWrite->errorInfo();
                if ($err[0] === '00000' || $err[0] === '01000') {
                    $result = true;
                }
                else {
                    $this->errors[] = $this->pdoWrite->errorInfo()[2];
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

        //        return 0;
    }

    /**
     * 此次查询只返回sql语句
     * @DateTime 2019-12-31
     * @Author   mokuyu
     * @param bool $bo [description]
     * @return Mokuyu [type]
     */
    public function fetchSql(bool $bo = true)
    {
        $this->isFetchSql = $bo;

        return $this;
    }

    /**
     * 设置查询字段
     * @param string|array $field
     * @return $this
     */
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
     * @param array $map [description]
     * @return Mokuyu [type]
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
     * @param integer $type [description]
     * @return Mokuyu [type]
     */
    public function fieldMode(int $type = 0)
    {
        if ($type > 2 || $type < 0) {
            throw new InvalidArgumentException('fieldMode must be numeric(0,1,2)!');
        }
        $this->temFieldMode = $type;

        return $this;
    }

    /**
     * 对指定字段进行运算更新
     * @DateTime 2019-11-01
     * @Author   mokuyu
     * @param string $field     [description]
     * @param int    $num       [description]
     * @param string $operation [description]
     * @return bool|false|int|string [type]
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

    /**
     * 强制使用指定的索引字段
     * @authname [name]     0
     * @DateTime 2019-12-31
     * @Author   mokuyu
     * @param string $field
     * @return Mokuyu [type]
     */
    public function forceIndex(string $field)
    {
        $this->queryParams['forceIndex'] = $field;

        return $this;
    }

    /**
     * 如果字段是一个的话直接返回这个字段的值，如果是一行记录的话就返回一个数组
     * @param int $id
     * @return bool|mixed|string
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
        $cacheData = $this->getQueryCache();
        $data      = null;
        if ($cacheData === null || $cacheData['data'] === null) {
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
                        }
                        elseif (preg_match('/^[a-zA-Z0-9_.' . $this->yinhao . ']+$/', $columns, $mat)) {
                            //判断是不是合法的字段项，如果有表名去掉表名
                            $columns = preg_replace(['/' . $this->yinhao . '/', '/^[\w]*\./i'], '', $columns);
                        }
                        // $columns=str_replace('')

                        $data = $data[0][$columns];
                    }
                    else {
                        $data = $data[0];
                    }
                }
                else {
                    $data = false;
                }
            }
            else {
                $data = false;
            }
            if ($cacheData !== null) {
                try {
                    $this->cache->set($cacheData['key'], $data, $cacheData['expire']);
                } catch (\Psr\SimpleCache\InvalidArgumentException $e) {
                }
            }

        }
        else {
            $data = $cacheData['data'];
        }
        return $data;
    }

    /**
     * 取当前表的所有字段
     * @DateTime 2018-04-27
     * @Author   mokuyu
     * @return array [type]
     */
    public function getFields(): array
    {
        try {
            if (empty($this->queryParams['table'])) {
                return [];
            }
            // $table_name = str_replace($this->yinhao, '', $this->queryParams['srcTable']);
            $table_name = $this->prefix . $this->parseTable($this->queryParams['srcTable']);
            $fieldArr   = [];
            $ckey       = $this->databaseName . '_' . $table_name . '_fields_';
            switch ($this->databaseType) {
                case 'mysql':
                    $sql      = 'DESC ' . $this->tablePrefix($this->queryParams['srcTable']);
                    $ckey     .= md5($sql);
                    $fieldArr = $this->cacheAction($ckey);
                    if ($fieldArr === null) {
                        $tm = $this->pdoRead->prepare($sql);
                        $tm->execute();
                        $fieldArr = $tm->fetchAll(PDO::FETCH_COLUMN);
                    }
                    break;
                case 'sqlite':
                    $sql = 'pragma table_info (\'' . $table_name . '\')';

                    $ckey     .= md5($sql);
                    $fieldArr = $this->cacheAction($ckey);
                    if ($fieldArr === null) {
                        $info = $this->pdoRead->query($sql);
                        if ($info) {
                            $info = $info->fetchAll(PDO::FETCH_ASSOC);
                            foreach ($info as $key => $value) {
                                $fieldArr[] = $value['name'];
                            }
                        }
                    }
                    break;
                case 'pgsql':
                    $sql      = 'select * from information_schema.columns where table_schema=\'public\' and table_name=\'' . $table_name . '\';';
                    $ckey     .= md5($sql);
                    $fieldArr = $this->cacheAction($ckey);
                    if ($fieldArr === null) {
                        $info = $this->pdoRead->query($sql);
                        if ($info) {
                            $info = $info->fetchAll(PDO::FETCH_ASSOC);
                            foreach ($info as $key => $value) {
                                $fieldArr[] = $value['column_name'];
                            }
                        }

                    }
                    break;
                case 'oracle':
                    $sql      = 'SELECT table_name, column_name, data_type FROM all_tab_cols WHERE table_name = \'' . $table_name . '\'';
                    $ckey     .= md5($sql);
                    $fieldArr = $this->cacheAction($ckey);
                    if ($fieldArr === null) {
                        $info = $this->pdoRead->query($sql);
                        if ($info) {
                            $info = $info->fetchAll(PDO::FETCH_ASSOC);
                            foreach ($info as $key => $value) {
                                $fieldArr[] = $value['COLUMN_NAME'];
                            }
                        }

                    }
                    break;
                case 'mssql':

                    $sql      = [
                        'SELECT',
                        '    ( CASE WHEN a.colorder= 1 THEN d.name ELSE \'\' END ) [table_name],',
                        '    a.name [column_name],',
                        '    b.name [column_type],',
                        '    g.[value] AS [column_note]',
                        'FROM',
                        '    syscolumns a',
                        '    LEFT JOIN systypes b ON a.xtype= b.xusertype',
                        '    INNER JOIN sysobjects d ON a.id= d.id',
                        '    AND d.xtype= \'U\'',
                        '    AND d.name<> \'dtproperties\'',
                        '    LEFT JOIN sys.extended_properties g ON a.id= g.major_id',
                        '    AND a.colid = g.minor_id',
                        'WHERE',
                        '    d.[name] = \'' . $table_name . '\' --数据表名称',
                        '',
                        'ORDER BY',
                        '    a.id,',
                        '    a.colorder',
                    ];
                    $sql      = implode("\r\n", $sql);
                    $ckey     .= md5($sql);
                    $fieldArr = $this->cacheAction($ckey);
                    if ($fieldArr === null) {
                        $info = $this->pdoRead->query($sql);
                        if ($info) {
                            $info = $info->fetchAll(PDO::FETCH_ASSOC);
                            foreach ($info as $key => $value) {
                                $fieldArr[] = $value['column_name'];
                            }
                        }

                    }
                    break;

            }
            $fieldArr = $fieldArr ?: [];
            $this->cacheAction($ckey, $fieldArr);

            return $fieldArr;
        } catch (PDOException $e) {
            throw $e;
        }
    }

    /**
     * 返回最后一次错误日志
     * @authname [name]     0
     * @DateTime 2019-12-31
     * @Author   mokuyu
     * @return mixed [type]
     */
    public function getLastError()
    {
        return end($this->errors);
    }

    /**
     * 返回最后一次执行过的sql
     * @authname [name]     0
     * @DateTime 2019-12-31
     * @Author   mokuyu
     * @return mixed [type]
     */
    public function getLastSql()
    {
        return end($this->logs);
    }

    /**
     * 返回pdo对象,
     * @authname [权限名字]       0
     * @DateTime 2019-12-11
     * @Author   mokuyu
     * @param bool $isWrite 返回的对象为读or写,默认为读连接
     * @return PDO [type]
     */
    public function getPDO(bool $isWrite = false): PDO
    {
        return $isWrite ? $this->pdoWrite : $this->pdoRead;
    }

    /**
     * 取当前数据库的主键
     * @authname [name]     0
     * @DateTime 2019-12-31
     * @Author   mokuyu
     * @return mixed|string|null [type]
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
            $ckey        = $this->databaseName . '_' . $table_name . '_primaryid_';
            switch ($this->databaseType) {
                case 'mysql':
                    $sql         = 'SELECT COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=\'' . $this->databaseName . '\' and TABLE_NAME=\'' . $table_name . '\'';
                    $ckey        .= md5($sql);
                    $primaryName = $this->cacheAction($ckey);
                    //已经查询过并且没有主键的情况直接返回
                    if ($primaryName === null) {
                        $info = $this->pdoRead->query($sql);
                        if ($info) {
                            $info        = $info->fetchAll(PDO::FETCH_ASSOC);
                            $primaryName = $info[0]['COLUMN_NAME'] ?? '';
                        }
                    }

                    break;
                case 'sqlite':
                    $sql         = 'pragma table_info (\'' . $table_name . '\')';
                    $ckey        .= md5($sql);
                    $primaryName = $this->cacheAction($ckey);
                    //已经查询过并且没有主键的情况直接返回
                    if ($primaryName === null) {
                        $info = $this->pdoRead->query($sql);
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
                    break;
                case 'pgsql':

                    $sql         = [
                        'select pg_constraint.conname as pk_name,pg_attribute.attname as colname,pg_type.typname as typename from',
                        'pg_constraint  inner join pg_class',
                        'on pg_constraint.conrelid = pg_class.oid',
                        'inner join pg_attribute on pg_attribute.attrelid = pg_class.oid',
                        'and  pg_attribute.attnum = pg_constraint.conkey[1]',
                        'inner join pg_type on pg_type.oid = pg_attribute.atttypid',
                        'where pg_class.relname = \'' . $table_name . '\'',
                        'and pg_constraint.contype=\'p\'',
                    ];
                    $sql         = implode("\r\n", $sql);
                    $ckey        .= md5($sql);
                    $primaryName = $this->cacheAction($ckey);
                    //已经查询过并且没有主键的情况直接返回
                    if ($primaryName === null) {
                        $info = $this->pdoRead->query($sql);
                        if ($info) {
                            $info = $info->fetchAll(PDO::FETCH_ASSOC);
                            foreach ($info as $key => $value) {
                                $primaryName = $value['colname'];

                                break;
                            }
                        }

                    }
                    break;
                case 'oracle':
                    $sql         = [
                        'SELECT',
                        '   C.CONSTRAINT_NAME,',
                        '   CC.COLUMN_NAME,',
                        '   CC.POSITION,',
                        '   C.OWNER,',
                        '   C.TABLE_NAME ',
                        'FROM',
                        '   ALL_CONSTRAINTS C,',
                        '   ALL_CONS_COLUMNS CC ',
                        'WHERE',
                        '   C.OWNER = CC.OWNER ',
                        '   AND C.CONSTRAINT_TYPE = \'P\' ',
                        '   AND C.CONSTRAINT_NAME = CC.CONSTRAINT_NAME ',
                        '   AND C.TABLE_NAME = CC.TABLE_NAME ',
                        '   AND C.TABLE_NAME = \'' . $table_name . '\' ',
                        'ORDER BY',
                        '   4,',
                        '   1,',
                        '   3',
                    ];
                    $sql         = implode("\r\n", $sql);
                    $ckey        .= md5($sql);
                    $primaryName = $this->cacheAction($ckey);
                    //已经查询过并且没有主键的情况直接返回
                    if ($primaryName === null) {
                        $info = $this->pdoRead->query($sql);
                        if ($info) {
                            $info = $info->fetchAll(PDO::FETCH_ASSOC);
                            foreach ($info as $key => $value) {
                                $primaryName = $value['COLUMN_NAME'];

                                break;
                            }
                        }

                    }
                    break;
                case 'mssql':
                    $sql         = [
                        'SELECT',
                        '    COL_NAME( object_id( \'' . $table_name . '\' ), c.colid )  [column_name]',
                        'FROM',
                        '    sysobjects a,',
                        '    sysindexes b,',
                        '    sysindexkeys c',
                        'WHERE',
                        '    a.name= b.name',
                        '    AND b.id= c.id',
                        '    AND b.indid= c.indid',
                        '    AND a.xtype= \'PK\'',
                        '    AND a.parent_obj= object_id( \'' . $table_name . '\' )',
                        '    AND c.id= object_id( \'' . $table_name . '\' )',
                    ];
                    $sql         = implode("\r\n", $sql);
                    $ckey        .= md5($sql);
                    $primaryName = $this->cacheAction($ckey);
                    //已经查询过并且没有主键的情况直接返回
                    if ($primaryName === null) {
                        $info = $this->pdoRead->query($sql);
                        if ($info) {
                            $info = $info->fetchAll(PDO::FETCH_ASSOC);
                            foreach ($info as $key => $value) {
                                $primaryName = $value['column_name'];

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

    /**
     * 返回请求的参数
     * @authname [name]     0
     * @DateTime 2019-12-31
     * @Author   mokuyu
     * @return array [type]
     */
    public function getQueryParams(): array
    {
        return $this->queryParams;
    }

    /**
     * 返回生成的条件语句
     * @DateTime 2019-10-04
     * @Author   mokuyu
     * @param array $data
     * @return array [type]
     */
    public function getWhere(array $data = []): array
    {
        if ($data) {
            $this->queryParams['where'] = $data;
        }
        $this->buildWhere();
        $redata = [$this->queryParams['where'], $this->bindParam];
        $this->initQueryParams();

        return $redata;
    }

    /**
     * 查询分组
     * @authname [name]     0
     * @DateTime 2019-12-31
     * @Author   mokuyu
     * @param string $data [description]
     * @return Mokuyu [type]
     */
    public function group(string $data)
    {
        $this->queryParams['group'] = $data;

        return $this;
    }

    /**
     * 是否有有记录
     * @authname [name]     0
     * @DateTime 2019-12-31
     * @Author   mokuyu
     * @return boolean
     */
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

    /**
     * 返回服务器信息
     * @authname [name]     0
     * @DateTime 2019-12-31
     * @Author   mokuyu
     * @return array|null [type]
     */
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
            try {
                $output[$key] = $this->pdoWrite->getAttribute(constant('PDO::ATTR_' . $value));
            } catch (PDOException $e) {

            }
        }

        $this->cacheAction('db_version_info', $output);

        return $output;
    }

    /**
     * add的别名
     * @authname [name]     0
     * @DateTime 2019-12-31
     * @Author   mokuyu
     * @param array $datas [description]
     * @return bool|false|int|mixed|string [type]
     */
    public function insert(array $datas)
    {
        $srcTable = $this->queryParams['table'];
        $this->buildSqlConf();
        $table = $this->queryParams['table'];
        if (empty($table)) {
            return 0;
        }
        $pk = $this->getPK();

        if (count($datas) == count($datas, 1)) {
            $datas = [$datas];
        }
        $isMulData = count($datas) > 1;
        //取表的所有字段
        $table_fields = $this->getFields();
        $index        = $isMulData ? 0 : null;
        $sql          = '';
        foreach ($datas as $data) {
            $values  = [];
            $columns = [];
            foreach ((array)$data as $key => $value) {
                $field = lcfirst($key);
                $field = strtolower(preg_replace('/([A-Z])/', '_$1', $field));
                if (($table_fields && !in_array($field, $table_fields)) || $field == $pk) {
                    //过滤掉数据库中没有的字段,和主键
                    continue;
                }
                $info      = $this->parseFormatField($key);
                $column    = $this->yinhao . $info['field'] . $this->yinhao;
                $columns[] = $column;
                $col       = ':' . $info['field'];

                if (is_null($value)) {
                    $this->appendBindParam($col, 'NULL', $index);
                }
                elseif (is_object($value) || is_array($value)) {
                    $this->appendBindParam($col, json_encode($value), $index);
                }
                elseif (is_bool($value)) {
                    $this->appendBindParam($col, ($value ? '1' : '0'), $index);
                }
                elseif (is_integer($value) || is_double($value) || is_string($value)) {
                    $this->appendBindParam($col, $value, $index);
                }
                else {
                    $this->appendBindParam($col, $value, $index);
                }
                $values[] = $col;
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
        if ($this->databaseType === 'oracle') {
            if ($pk && $result) {
                $result = $this->table($srcTable)->field($pk)->order($pk . ' desc')->limit(1)->get();
                $result = $result ?: 1;
            }

            return $result;
        }
        // $lastId = ;

        return $this->pdoWrite->lastInsertId() ?: $result;

    }

    /**
     * 解析join组合的布格尼查询语句
     * @param array $data
     * @return Mokuyu
     */
    public function join(array $data)
    {
        $this->queryParams['join'] = $data;

        return $this;
    }

    /**
     * 查询分页
     * @param string|array $start
     * @param null|int     $end
     * @return $this
     */
    public function limit($start, $end = null)
    {
        if (is_null($end)) {
            if (is_array($start) && count($start) >= 2) {
                [$start, $end] = $start;
            }
            elseif (strpos($start . '', ',') !== false) {
                [$start, $end] = explode(',', (string)$start);
            }
            else {
                $end   = $start;
                $start = 0;
            }
        }
        $start = intval($start);
        $end   = intval($end);
        $start = $start >= 0 ? $start : 0;
        $end   = $end >= 0 ? $end : 0;
        $data  = '';
        switch ($this->databaseType) {
            case 'mysql':
                // $data = ' LIMIT ' . $start . ' ,' . $end;
                // break;
            case 'sqlite':
            case 'pgsql':
                $data = ' LIMIT ' . $end . ' OFFSET ' . $start;
                break;
            case 'mssql':  //12c
            case 'oracle': //version >=12c 可用

                $info = $this->info();
                [$version] = explode('.', $info['version']);
                if (intval($version) >= 12) {
                    $data = ' OFFSET ' . $start . ' ROWS FETCH NEXT ' . $end . ' ROWS ONLY';
                }
                else {
                    $this->where([
                        'rownum[>=]' => $start + 1,
                        'rownum[<]'  => $end + 1,
                    ]);
                }
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

    /**
     * 排序字段
     * @param string|array $data
     * @return $this
     */
    public function order($data)
    {
        $this->queryParams['order'] = $data;

        return $this;
    }

    /**
     * 返回指定分页的记录
     * @authname [name]     0
     * @DateTime 2019-12-31
     * @Author   mokuyu
     * @param int     $page     当前页数
     * @param integer $pageSize 分页大小
     * @return Mokuyu [type]
     */
    public function page(int $page = 1, int $pageSize = 15)
    {
        return $this->limit(($page - 1) * $pageSize, $pageSize);
    }

    /**
     * 自动分页
     * @authname [name]      0
     * @DateTime 2019-12-31
     * @Author   mokuyu
     * @param int $page
     * @param int $pageSize 分页大小
     * @return array|bool [list=>[],count=>100]
     */
    public function paginate(int $page = 1, int $pageSize = 15)
    {
        $temBak = [
            'queryParams'  => $this->queryParams,
            'bindParam'    => $this->bindParam,
            'temFieldMode' => $this->temFieldMode,
            'temTableMode' => $this->temTableMode,
            'fieldMap'     => $this->fieldMap,
            'isFetchSql'   => $this->isFetchSql,
        ];
        //统计数量时只需要第一个有效字段做为统计字段
        $temField = $this->queryParams['field'];
        if (is_string($temField)) {
            $temField = explode(',', $temField);
        }
        if (count($temField) > 1) {
            $temField[0] === '*' && ($temField = [$temField[1]]);
        }

        $count = $this->field($temField[0])->count();

        //还原原有查询条件
        foreach ($temBak as $key => $value) {
            $this->$key = $value;
        }
        $this->page($page, $pageSize);
        if (empty($this->queryParams['table'])) {
            return false;
        }
        $this->buildSqlConf();
        $sql   = $this->buildSelect();
        $query = $this->query($sql);
        //调试时返回这些
        if (!($query instanceof PDOStatement)) {
            return $query ?: [];
        }

        return [
            'list'     => $query->fetchAll(PDO::FETCH_ASSOC),
            'count'    => $count,
            'page'     => $page,
            'pageSize' => $pageSize,
        ];
    }

    /**
     * [query description]
     * @DateTime 2019-05-02
     * @Author   mokuyu
     * @param string $sql
     * @param array  $param
     * @return array|bool|false|PDOStatement|string|null [type]
     */
    public function query(string $sql, array $param = [])
    {
        try {
            //如果直接传sql语句来执行,没有设置table的话此值为true,直接返回数据,否则返回一个PDOStatement请求对象
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
            if ($this->isAbort) {
                die($this->greateSQL($sql, $this->bindParam));
            }
            $pdo = null;
            if ($this->queryParams['useWriteConn'] === true) {
                $pdo = $this->pdoWrite;
            }
            else {
                $pdo = $this->pdoRead ?: $this->pdoWrite;
            }

            $t1    = microtime(true);
            $query = null;
            if ($hasParam) {
                $query = $pdo->prepare($sql);
                $query->execute($this->bindParam);

            }
            else {
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
                $da = [];
                if ($query) {
                    $da = $query->fetchAll(PDO::FETCH_ASSOC);
                }

                return $da ?: [];
            }
            else {
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
     * @return void [type]
     */
    public function rollback()
    {
        $this->pdoWrite->rollBack();
    }

    /**
     * 如果数据里有主键则执行更新操作,否则将插入数据
     * @authname [name]     0
     * @DateTime 2020-01-07
     * @Author   mokuyu
     * @param    [type]   $datas
     * @return bool|false|int|string [type]
     */
    public function save($datas)
    {
        if (empty($this->queryParams['table'])) {
            return 0;
        }
        //如果条件为空,则查找是不是含有主键,有主键则更新,没有则插入
        if (empty($this->queryParams['where'])) {
            $pk = $this->getPK();
            if ($pk && isset($datas[$pk])) {
                $map = [$pk => $datas[$pk]];
                unset($datas[$pk]);
                return $this->where($map)->update($datas);
            }
            else {
                return $this->insert($datas);
            }
        }
        else {
            return $this->update($datas);
        }
    }

    /**
     * 查询数据返回一个二维数组
     * @return array|bool
     */
    public function select()
    {
        if (empty($this->queryParams['table'])) {
            return false;
        }
        $cacheData = $this->getQueryCache();
        if ($cacheData === null || $cacheData['data'] === null) {
            $this->buildSqlConf();
            $sql   = $this->buildSelect();
            $query = $this->query($sql);
            //调试时返回这些
            if (!($query instanceof PDOStatement)) {
                $data = $query ?: [];
            }
            else {
                $data = $query->fetchAll(PDO::FETCH_ASSOC);
            }
            if ($cacheData !== null) {
                try {
                    $this->cache->set($cacheData['key'], $data, $cacheData['expire']);
                } catch (\Psr\SimpleCache\InvalidArgumentException $e) {
                }
            }
            $cacheData = $data;
        }
        else {
            $cacheData = $cacheData['data'];
        }
        return $cacheData;
    }

    /**
     * @param int         $nums     遍历块大小
     * @param Closure     $callback 回调处理函数
     * @param string|null $field    以哪列排序
     * @param string      $sort     asc|desc
     * @return bool
     */
    public function chunk(int $nums, Closure $callback, string $field = null, string $sort = 'asc')
    {
        $sort = strtolower($sort);
        if ($field === null) {
            $field = $this->getPK();
        }
        $this->limit($nums);
        $this->order($field . ' ' . $sort);
        $temBak = [
            'queryParams'  => $this->queryParams,
            'bindParam'    => $this->bindParam,
            'temFieldMode' => $this->temFieldMode,
            'temTableMode' => $this->temTableMode,
            'fieldMap'     => $this->fieldMap,
            'isFetchSql'   => $this->isFetchSql,
        ];
        while ($list = $this->select()) {
            if (false === call_user_func($callback, $list)) {
                return false;
            }
            $end    = end($list);
            $lastId = $end[$field];
            foreach ($temBak as $key => $value) {
                $this->$key = $value;
            }
            $this->where([$field . '[' . ($sort == 'asc' ? '>' : '<') . ']' => $lastId]);
        }
        return true;

    }

    /**
     * @param string      $field    返回的列,如果为*则返回数据,参数$key为null时跟select一样
     * @param string|null $key      索引key,做为数组的索引
     * @param bool        $isDelKey 如果有多列数据,是否从数组中删除索引列
     * @return array
     */
    public function column($field, string $key = null, bool $isDelKey = false)
    {
        if (is_string($field)) {
            $field = explode(',', $field);
        }
        $isSingle = count($field) === 1;

        // 传入字段的情况下
        if ($field[0] != '*') {
            if ($key === null) {
                $this->field($field);
            }
            else {
                $field[] = $key;
                $this->field($field);
            }
        }
        $list = $this->select();
        if ($key === null) {
            if ($field[0] === '*') {
                return $list;
            }
            if ($isSingle) {
                return array_column($list, $field[0]);
            }
            else {
                return $list;
            }
        }
        else {
            $data = [];
            foreach ($list as $value) {
                $data[$value[$key]] = ($field[0] === '*' || !$isSingle) ? $value : $value[$field[0]];
                //从数组中删除键
                if ($isDelKey && is_array($data[$value[$key]]))
                    unset($data[$value[$key]][$key]);
            }
            return $data;
        }


    }

    /**
     * 修改绑定的参数
     * @DateTime 2019-11-05
     * @Author   mokuyu
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
        if ($type > 3 || $type < 0) {
            throw new InvalidArgumentException('tableMode must be numeric(0,1,2,3)!');
        }
        $this->temTableMode = $type;

        return $this;
    }

    /**
     * 使用回调执行一个事务
     * @DateTime 2019-08-19
     * @Author   mokuyu
     * @param Closure $callback [description]
     * @return mixed [type]
     * @throws Exception
     */
    public function transaction(Closure $callback)
    {
        $this->pdoWrite->beginTransaction();
        // We'll simply execute the given callback within a try / catch block
        // and if we catch any exception we can rollback the transaction
        // so that none of the changes are persisted to the database.
        try {
            $result = $callback($this);

            $this->pdoWrite->commit();
        } catch (PDOException $e) {
            $this->pdoWrite->rollBack();
            throw $e;
        }

            // If we catch an exception, we will roll back so nothing gets messed
            // up in the database. Then we'll re-throw the exception so it can
            // be handled how the developer sees fit for their applications.
        catch (Exception $e) {
            $this->pdoWrite->rollBack();
            throw $e;
        }

        return $result;
    }


    /**
     * 更新数据
     * @authname [name]     0
     * @DateTime 2020-01-07
     * @Author   mokuyu
     * @param array $datas
     * @return bool|false|int|string [type]
     */
    public function update(array $datas)
    {

        if (empty($this->queryParams['table'])) {
            return 0;
        }
        if (count($datas) === count($datas, 1)) {
            $datas = [$datas];
        }
        $isMulData = count($datas) > 1;
        $index     = $isMulData ? 0 : null;

        $whereStr = '';
        $pk       = '';
        if (empty($this->queryParams['where'])) {
            $pk = $this->getPK();
            if ($pk) {
                if (isset($datas[0][$pk])) {
                    // $this->where([$pk => $datas[0][$pk]]);
                    // unset($datas[0][$pk]); //删除对主键的设置':bindparam_' . trim($key, ':');
                    $whereStr = ' WHERE ' . $this->yinhao . $pk . $this->yinhao . ' = :bindparam_' . $pk;
                }
                else {
                    return 0;
                }
            }
            else {
                return 0;
            }
        }
        elseif ($isMulData) {
            return 0;
        }
        $this->buildSqlConf();
        if (!$whereStr) {
            $whereStr = $this->queryParams['where'];
        }
        //取表的所有字段
        $table_fields = $this->getFields();
        $fields       = [];
        $sql          = '';
        foreach ($datas as $data) {
            foreach ($data as $key => $value) {
                $info = $this->parseFormatField($key);
                //如果是主键的话就加参数然后,跳过
                if ($pk && $info['field'] == $pk) {
                    if ($isMulData) {
                        $this->bindParam[$index][':bindparam_' . $pk] = $value;
                    }
                    else {
                        $this->bindParam[':bindparam_' . $pk] = $value;
                    }
                    continue;
                }
                if ($table_fields && !in_array($info['field'], $table_fields)) {
                    continue;
                }
                //字段+ - * / 本字段算术运算
                preg_match('/([\w]+)(\[(\+|\-|\*|\/)\])?/i', $info['field'], $match);
                if (isset($match[3])) {
                    if (is_numeric($value)) {
                        $fields[] = $this->joinField($match[1]) . ' = ' . $this->joinField($match[1]) . ' ' . $match[3] . ' ' . $value;
                    }
                }
                else {
                    //如果join不为空的话就把字段默认加上第一个表为前缀
                    if ($this->queryParams['join'] && !$info['table']) {
                        $info['table'] = trim($this->queryParams['table'], $this->yinhao);
                    }
                    $col    = ':' . $info['field'];
                    $column = $this->yinhao . $info['field'] . $this->yinhao;
                    if ($info['table']) {
                        $col    .= '_' . $info['table'];
                        $column = $this->yinhao . $info['table'] . $this->yinhao . '.' . $column;
                    }
                    if (is_null($value)) {
                        $fields[] = $column . ' = NULL';
                    }
                    elseif (is_object($value) || is_array($value)) {
                        preg_match("/\(JSON\)\s*([\w]+)/i", $key, $column_match);
                        $this->appendBindParam($col,
                            isset($column_match[0]) ? json_encode($value) : serialize($value)
                            , $index);

                        $fields[] = $column . ' = ' . $col;
                    }
                    elseif (is_bool($value)) {
                        $this->appendBindParam($col, ($value ? '1' : '0'), $index);
                        $fields[] = $column . ' = ' . $col;
                    }
                    elseif (is_integer($value) || is_double($value) || is_string($value)) {
                        $this->appendBindParam($col, $value, $index);
                        $fields[] = $column . ' = ' . $col;
                    }
                    else {
                        $this->appendBindParam($col, $value, $index);
                        $fields[] = $column . ' = ' . $col;
                    }
                }
            }
            if ($index === 0 || is_null($index)) {
                $sql = 'UPDATE ' . $this->queryParams['table'] . ' ' . $this->queryParams['join'] . ' SET ' . implode(', ', $fields) . $whereStr;
            }
            $isMulData && $index++;
        }

        return $this->exec($sql);
    }

    /**
     * 强制使用写pdo在一些强一至性的场景可以使用
     * @authname [name]     0
     * @DateTime 2020-01-06
     * @Author   mokuyu
     * @return Mokuyu [type]
     */
    public function useWriteConn()
    {
        $this->queryParams['useWriteConn'] = true;

        return $this;
    }

    /**
     * where第二个参数映射转换
     * @param $field
     * @param $operator
     * @param $value
     */
    protected function operatorMap(&$field, &$operator, &$value)
    {
        $operator = strtolower($operator);
        $map      = [
            '!'    => '[!]',
            '~'    => '[~]',
            '!~'   => '[!~]',
            '<>'   => '[<>]',
            '><'   => '[><]',
            '>'    => '[>]',
            '>='   => '[>=]',
            '<'    => '[<]',
            '<='   => '[<=]',
            'like' => '[~]',
            'in'   => '',
            'gt'   => '[>]',
            'egt'  => '[>=]',
            'lt'   => '[<]',
            'elt'  => '[<=]',
            'eq'   => '',
            'neq'  => '[!]',
        ];
        if ($operator === 'in' && !is_array($value)) {
            $value = explode(',', $value);
        }
        if (!isset($map[$operator])) {
            throw new InvalidArgumentException('Operation not exists');
        }
        $field = $field . $map[$operator];
    }

    /**
     * and查询条件，可多次调用
     * @param string|array $data   条件数组
     * @param null         $value  操作符
     * @param null         $value2 值
     * @return $this
     */
    public function where($data, $value = null, $value2 = null)
    {
        if ($value2 !== null) {
            $this->operatorMap($data, $value, $value2);
            $data = [$data => $value2];
        }
        elseif ($value !== null && is_string($data)) {
            $data = [$data => $value];
        }
        $_wh = [];
        if (is_Array($data)) {
            $_wh = $data;
        }
        else {
            $_wh['_sql'] = '(' . $data . ')';
        }
        if ($_wh) {
            if ($this->queryParams['where']) {
                $this->queryParams['where'] = array_merge($this->queryParams['where'], $_wh);
            }
            else {
                $this->queryParams['where'] = $_wh;
            }
        }

        return $this;

    }

    /**
     * or查询条件,可多次调用
     * @param      $data
     * @param null $value
     * @param null $value2
     * @return $this
     */
    public function whereOr($data, $value = null, $value2 = null)
    {
        if ($value2 !== null) {
            $this->operatorMap($data, $value, $value2);
            $data = [$data => $value2];
        }
        elseif ($value !== null && is_string($data)) {
            $data = [$data => $value];
        }
        $_wh = [];
        if (is_Array($data)) {
            $_wh = $data;
        }
        else {
            $_wh['_sql'] = '(' . $data . ')';
        }
        if ($_wh) {
            $this->queryParams['where']['or'] = $_wh;
        }

        return $this;
    }

    /**
     * 缓存当前查询
     * @param string|int $keyOrTime 缓存key或过期时间,为过期时间时key由系统自动生成
     * @param int        $expire
     * @return Mokuyu
     */
    public function cache($keyOrTime, int $expire = 10 * 60)
    {
        if (is_numeric($keyOrTime)) {
            $this->queryParams['queryCache'] = [
                'key'    => null,
                'expire' => $keyOrTime,
            ];
        }
        else {
            $this->queryParams['queryCache'] = [
                'key'    => $keyOrTime,
                'expire' => $expire,
            ];
        }
        return $this;
    }

    /**
     * 取查询缓存
     */
    protected function getQueryCache()
    {
        if ($this->cache === null || $this->queryParams['queryCache'] === null || $this->isFetchSql || $this->isAbort) {
            return null;
        }
        $key    = $this->queryParams['queryCache']['key'] ?? null;
        $expire = $this->queryParams['queryCache']['expire'] ?? 5 * 60;
        if ($key === null) {
            $key = $this->dbConfig['database_name'] . ':' . $this->queryParams['table'] . ':' . md5(json_encode([$this->queryParams, $this->bindParam]));
            $key = strtolower($key);
        }
        $data = [
            'data'   => null,
            'key'    => $key,
            'expire' => $expire,
        ];

        $data['data'] = $this->cache->get($key);
        $this->cacheHits++;
        return $data;
    }

    /**
     * 返回缓存命中次数
     * @return int
     */
    public function getCacheHits(): int
    {
        return $this->cacheHits;
    }

    /**
     * 这个地方传引用进来,防止key出现一样的情况导致冲突,如果一样的话在这个函数里会随机加上一个数字并修改这个key值
     * @DateTime 2019-03-10
     * @Author   mokuyu
     * @param      $key
     * @param      $value
     * @param null $index
     * @return void [type]
     */
    protected function appendBindParam(&$key, $value, $index = null): void
    {
        //防止冲突,加个前缀
        $key = ':bindparam_' . trim($key, ':');
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
        }
        else {
            $this->bindParam[$index][$key] = $value;
        }

    }

    protected function appendSqlLogs(float $rtime, string $sql, array $params): void
    {
        $class = $rtime > 1 ? 'style="color:#f00";' : '';
        $rtime = str_pad((round($rtime, 6)) . '', 8, '0');
        if (PHP_SAPI == 'cli') {
            $this->logs[] = '[' . $rtime . 's] ' . $sql;
        }
        else {
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
                preg_match('/(\[\s*?(<|>|><|<>)\s*?\])?([a-zA-Z0-9_\-]*)\s?(\(([a-zA-Z0-9_\-]*)\))?/', $sub_table, $match);
                //                preg_match('/(\[\s*?(\<|\>|\>\<|\<\>)\s*?\])?([a-zA-Z0-9_\-]*)\s?(\(([a-zA-Z0-9_\-]*)\))?/', $sub_table, $match);
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
                        }
                        else {
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
            }
            elseif ($type == 'msssql') {
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
                $data[$key] = $field . ' ' . strtoupper($arr[1] ?? '');
            }

        }
        $data = implode(',', $data);
        if ($data) {
            $this->queryParams['order'] = ' ORDER BY ' . $data;
        }
        return $this;
    }

    /**
     * 构建一个pdo对象,代码来自medoo
     * @DateTime 2019-11-05
     * @Author   mokuyu
     * @param array $options [description]
     * @return PDO [type]
     */
    protected function buildPDO(array $options): PDO
    {
        try {
            $commands = [];
            $dsn      = '';

            // if (is_array($options)) {
            //     foreach ($options as $option => $value) {
            //         $option        = $this->parseName($option, 2);
            //         $this->$option = $value;
            //     }
            // } else {
            //     return null;
            // }

            // if (isset($this->port) && is_int($this->port * 1)) {
            // $port = $this->port;
            // }
            $server   = $options['server'];
            $port     = $options['port'];
            $username = $options['username'];
            $password = $options['password'];

            $type    = strtolower($this->databaseType);
            $is_port = isset($port);

            // if (isset($options['prefix'])) {
            //     $this->prefix = $options['prefix'];
            // }

            switch ($type) {
                case 'mariadb':
                    $type = 'mysql';

                case 'mysql':
                    if ($this->socket) {
                        $dsn = $type . ':unix_socket=' . $this->socket . ';dbname=' . $this->databaseName;
                    }
                    else {
                        $dsn = $type . ':host=' . $server . ($is_port ? ';port=' . $port : '') . ';dbname=' . $this->databaseName;
                    }

                    // Make MySQL using standard quoted identifier
                    $commands[] = 'SET SQL_MODE=ANSI_QUOTES';
                    break;

                case 'pgsql':
                    $dsn = $type . ':host=' . $server . ($is_port ? ';port=' . $port : '') . ';dbname=' . $this->databaseName;
                    break;

                case 'sybase':
                    $dsn = 'dblib:host=' . $server . ($is_port ? ':' . $port : '') . ';dbname=' . $this->databaseName;
                    break;

                case 'oracle':
                    $dbname = $server
                        ?
                        '//' . $server . ($is_port ? ':' . $port : ':1521') . '/' . $this->databaseName
                        :
                        $this->databaseName;
                    // $conn_string = '(DESCRIPTION=(ADDRESS=(PROTOCOL=TCP)(HOST=localhost)(PORT=1521))(CONNECT_DATA=(SERVICE_NAME=XE)))';
                    $dsn = 'oci:dbname=' . $dbname . ($this->charset ? ';charset=' . $this->charset : '');
                    break;

                case 'mssql':
                    $dsn = strstr(PHP_OS, 'WIN')
                        ?
                        'sqlsrv:server=' . $server . ($is_port ? ',' . $port : '') . ';database=' . $this->databaseName
                        :
                        'dblib:host=' . $server . ($is_port ? ':' . $port : '') . ';dbname=' . $this->databaseName;

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
                $username,
                $password,
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
     * @return string [type] [description]
     */
    protected function buildSelect(): string
    {
        $map   = $this->queryParams;
        $table = $map['table'];
        $where = $map['where'];
        $order = $map['order'];
        $limit = $map['limit'];
        $join  = $map['join'];
        $field = $map['field'];
        $group = $map['group'];
        $index = $map['forceIndex'];
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
     * @return void [type] [description]
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
            if ($key == '_sql') {
                if (is_array($value)) {
                    $ts = '((' . implode(') AND (', $value) . '))';
                }
                else {
                    $ts = '(' . $value . ')';
                }

            }
            else {
                $ts = $this->parseOperator($key, $value);
            }
            $str1 .= empty($str1) ? $ts : (' AND ' . $ts);
        }
        $str2 = '';
        foreach ($ordata as $key => $value) {
            if ($key == '_sql') {
                if (is_array($value)) {
                    $ts = '((' . implode(') AND (', $value) . '))';
                }
                else {
                    $ts = '(' . $value . ')';
                }
            }
            else {
                $ts = $this->parseOperator(preg_replace('/__\d+/', '', $key), $value);
            }
            $str2 .= empty($str2) ? $ts : (' OR ' . $ts);
        }
        $str3 = '';
        foreach ($data as $key => $value) {
            // $ts = $this->parseOperator($key, $value);
            if ($key == '_sql') {
                if (is_array($value)) {
                    $ts = '((' . implode(') AND (', $value) . '))';
                }
                else {
                    $ts = '(' . $value . ')';
                }
            }
            else {
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

        if ($this->databaseType === 'oracle') {
            //oracle 分页关键字不能带引号
            $resql = str_replace('"rownum"', 'rownum', $resql);
        }

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
     * @param string $key
     * @param    [type]   $key   [description]
     * @return null [type]
     */
    protected function cacheAction(string $key, $value = null)
    {
        $key = 'mokuyu.' . $key;
        if (!isset($this->cacheKeys[$key])) {
            $this->cacheKeys[$key] = true;
        }
        if ($this->debug) {
            return null;
        }
        else {
            if (is_null($this->cache)) {
                return null;
            }
            else {
                if (is_null($value)) {
                    return $this->cache->get($key);
                }
                else {
                    return $this->cache->set($key, $value);
                }

            }
        }
    }

    /**
     * 把数组转换成两个值绑定 between  :create_time_1  and   :create_time_2
     * @DateTime 2019-11-05
     * @Author   mokuyu
     * @param string $column [description]
     * @param array  $arr    [description]
     * @return string [type]
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
     * @param string $sql   [description]
     * @param array  $param [description]
     * @return string [type]
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
     * @return void [type]
     */
    protected function initQueryParams(): void
    {
        //重置为全局风格
        $this->temFieldMode = $this->fieldMode;
        $this->temTableMode = $this->tableMode;
        $this->isAbort      = false;
        $this->fieldMap     = [];
        $this->queryParams  = [
            'table'        => '',
            'srcTable'     => '', //传入的原始表
            'join'         => [],
            'where'        => [],
            'order'        => '',
            'rand'         => false,
            'group'        => '',
            'limit'        => '',
            'field'        => '*',
            'data'         => '',
            //强制使用索引,mysql查询的时候用
            'forceIndex'   => '',
            //强制使用写库来读数据,在一些强一至性的场景下会使用
            'useWriteConn' => false,
            //当前查询缓存
            'queryCache'   => null,
        ];
        $this->bindParam    = [];
    }

    /**
     * 解析SELECT字段
     * 解析表是不是按指定的格式存储,并且把use.id这样的格式解析成`use`.`id`
     * @param  [type] $string         [description]
     * @param bool $isJoinAlias
     * @return string [type] [description]
     */
    protected function joinField($field, $isJoinAlias = true): string
    {
        if ($field == '*') {
            return $field;
        }
        if (is_array($field)) {
            $info = $field;
        }
        else {
            $info = $this->parseFormatField($field);
        }
        //* 不能加引号,不然会报错
        $field = $info['field'] === '*' ? $info['field'] : $this->yinhao . $info['field'] . $this->yinhao;
        if ($info['table']) {
            $field = $this->yinhao . $info['table'] . $this->yinhao . '.' . $field;
        }
        //这里可能出现函数嵌套的情况如：COUNT(DISTINCT
        if ($info['func']) {
            $funcs = explode('(', $info['func']);
            $funcs = array_reverse($funcs);
            foreach ($funcs as $funcname) {
                $field = $funcname . '(' . $field . ')';
            }
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
     * @param string $field
     * @return array [type]
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
        //             if (preg_match('/(#?)([\w\(\)\.\-]+)(\[\s*?(\>|\>\=|\<|\<\=|\!|\<\>|\>\<|\!?~)\s*?\])/i', $field, $match)) {
        if (preg_match('/(#?)([\w().\-]+)(\[\s*?(>|>=|<|<=|!|<>|><|!?~)\s*?\])/i', $field, $match)) {
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
        preg_match('/([^\s]+)\s*\(\s*([a-zA-Z0-9_\-.*]*?)\s*\)/', $field, $matfun);
        if (isset($matfun[1])) {
            $arr['field'] || ($arr['field'] = $matfun[2]); //字段
            $arr['func'] = $matfun[1];                     //函数
            //从原有字符串中把 (user) 换成占位符
            // $field_str = str_replace($matfun[0], '__ZHANWEIFU__', $field_str);
        }
        //如果匹配的话,填充的数组是一样的
        if (stripos($field, ' as ') !== false) {
            //正则出有as的这种情况
            preg_match('/([a-zA-Z0-9_\-.()]*)\s*as\s*([a-zA-Z0-9_\-.()]*)/i', $field, $match);
        }
        else {
            //正则出  User.name[nickname] 这种情况
            preg_match('/([a-zA-Z0-9_\-.()]*)\s*\[([a-zA-Z0-9_\-]*)\]/i', $field, $match);
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
        if ($this->temFieldMode === 1) {
            $arr['field'] = $this->parseName($arr['field']);
        }
        elseif ($this->temFieldMode === 2) {
            $arr['field'] = $this->parseName($arr['field'], 3);
        }

        return $arr;
    }

    /**
     * 驼峰和下划线命名互转
     * @DateTime 2019-10-06
     * @Author   mokuyu
     * @param string  $name
     * @param integer $type 1:user_group，2:userGroup，3:UserGroup
     * @return string [type]
     */
    protected function parseName(string $name, int $type = 1): string
    {
        if ($type === 1) {
            return strtolower(preg_replace('/([A-Z])/', '_$1', lcfirst($name)));
        }
        else {
            $name = str_replace('_', '', ucwords($name, '_'));

            return $type === 3 ? $name : lcfirst($name);
        }
    }

    /**
     * 解析每一个键值对的操作符和值
     * @param string $field
     * @param  [type] $field          [description]
     * @return string [type] [description]
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
            $col    .= '_' . $info['table'];
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
                }
                elseif (is_array($value)) {
                    $repeat = $this->getArrayParam($column, $value);
                    $tdata  = $column . ' NOT IN (' . $repeat . ')';
                }
                elseif (is_integer($value) || is_double($value)) {
                    $this->appendBindParam($col, $value);
                    $tdata = $column . ' != ' . $col;
                }
                elseif (is_bool($value)) {
                    $this->appendBindParam($col, ($value ? '1' : '0'));
                    $tdata = $column . ' != ' . $col;
                }
                elseif (is_string($value)) {
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

                    }
                    else {
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
                $val = $value;
                if (!is_array($val)) {
                    $val = [$val];
                }
                //like条件可以加多个,构成or组合
                $tdata = '';
                foreach ($val as $ke => $va) {
                    $va .= '';
                    if (strpos($va, '%') === false) {
                        $va = $va . '%';
                    }
                    $tem_col = $col . $ke;
                    $this->appendBindParam($tem_col, $va);
                    if ($tdata) {
                        $tdata .= ' OR ' . $column . ($operator === '!~' ? ' NOT' : '') . ' LIKE ' . $tem_col;
                    }
                    else {
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
                }
                elseif (strpos($field, '#') === 0) {
                    $this->appendBindParam($col, $value);
                }
                else {
                    $this->appendBindParam($col, $value);
                }
                $condition = $column . ' ' . $operator . ' ' . $col;
                $tdata     = $condition;
            }
        }
        else {
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
            if (is_array($value) && isset($value[0]) && isset($opArr[$value[0]])) {
                $tj  = $value[0];
                $val = $value[1];
                if ($tj == 'in') {
                    if (is_string($val)) {
                        $val = explode(',', $val);
                    }
                    $repeat = $this->getArrayParam($column, $val);
                    $tdata  = $column . ' IN (' . $repeat . ')';
                }
                else {
                    $this->appendBindParam($col, $val);
                    $tdata = $column . ' ' . $opArr[$tj] . ' ' . $col;
                }
            }
            else {
                if (is_null($value)) {
                    $tdata = $column . ' IS NULL';
                }
                elseif (is_array($value)) {
                    $repeat = $this->getArrayParam($col, $value);
                    $tdata  = $column . ' IN (' . $repeat . ')';
                }
                elseif (is_integer($value) || is_double($value)) {
                    $this->appendBindParam($col, $value);
                    $tdata = $column . ' = ' . $col;
                }
                elseif (is_bool($value)) {
                    $this->appendBindParam($col, ($value ? '1' : '0'));
                    $tdata = $column . ' = ' . $col;
                }
                elseif (is_string($value)) {
                    $this->appendBindParam($col, $value);
                    $tdata = $column . ' = ' . $col;
                }
                else {
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
     * @return string [type] [description]
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
            //如果有联合查询的情况下,视情况解析出数据表
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
     * @param string $table [description]
     * @return string [type]
     */
    protected function parseTable(string $table): string
    {
        if ($this->temTableMode === 1) {
            $table = $this->parseName($table, 1);
        }
        elseif ($this->temTableMode === 2) {
            $table = $this->parseName($table, 3);
        }
        elseif ($this->temTableMode === 3) {
            $table = strtoupper($this->parseName($table, 1));
        }

        return $table;
    }

    /**
     * 显示错误,sql执行出错错误时会调用这里
     * @DateTime 2018-04-24
     * @Author   mokuyu
     * @param string $str
     * @return void [type]
     */
    protected function showError(string $str): void
    {

    }

    /**
     * 汇总函数计算
     * @DateTime 2019-11-01
     * @Author   mokuyu
     * @param string $func
     * @param array  $field
     * @return int|mixed [type]
     */
    protected function summary(string $func, array $field)
    {
        //如果之前通过field方法传过字段就设置上去，*为默认,排除
        if ($this->queryParams['field'] && $this->queryParams['field'] !== '*') {
            if (is_string($this->queryParams['field'])) {
                $field = explode(',', $this->queryParams['field']);
            }
            else {
                $field = $this->queryParams['field'];
            }

        }
        $arr = array_flip(['COUNT', 'AVG', 'MAX', 'MIN', 'SUM']);
        if (!isset($arr[$func]) || !$this->queryParams['table']) {
            return 0;
        }
        //如果是count的话把排序去掉,否则字段中有别名时可能会出错
        if ($func === 'COUNT') {
            $this->queryParams['order'] = '';
        }

        //字段转成数组
        if (count($field) == 1) {
            if (is_array($field[0])) {
                $field = $field[0];
            }
            else {
                $field = explode(',', $field[0]);
            }
        }

        //给字段加上函数
        $obj = $this;
        array_walk($field, function (&$value) use ($func, $obj) {
            $info = $obj->parseFormatField($value);
            $fie  = $info['field'];
            if ($info['srcTable']) {
                $fie = $info['srcTable'] . '.' . $fie;
            }
            if ($info['func']) {
                $value = $func . '(' . $info['func'] . '(' . $fie . ')' . ')';
            }
            else {
                $value = $func . '(' . $fie . ')';
            }
            $value .= ' AS ' . ($info['field'] === '*' ? 'num' : $info['field']);
        });

        //重新设置查询字段
        $this->field($field);
        if ($this->queryParams['group']) {
            //如果有分组去复的话会有多条记录,需要放到子sql中统计
            // $map  = $this->getWhere();
            $this->buildSqlConf();
            $sql   = $this->buildSelect();
            $query = $this->query('SELECT ' . implode(',', $field) . ' FROM (' . $sql . ') tem');
            $data  = $query->fetchAll(PDO::FETCH_ASSOC);
        }
        else {
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

                return $data[0][$fie] ?: 0;
                // return array_sum(array_column($data, $fie));

            }
            else {
                //多个字段就返回数组
                return $data ?: 0;
            }
        }
        else {
            return 0;
        }
    }

    /**
     * 给表添加上前缀
     * @param string $table
     * @return string [type] [description]
     */
    protected function tablePrefix(string $table): string
    {
        $table = $this->yinhao . $this->prefix . $this->parseTable($table) . $this->yinhao;
        if ($this->databaseType === 'oracle') {
            return $this->yinhao . $this->databaseName . $this->yinhao . '.' . $table;
        }

        return $table;
    }
}
