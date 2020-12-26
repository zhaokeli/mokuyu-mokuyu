<?php
declare (strict_types = 1);

namespace mokuyu\database;

use Closure;
use Psr\SimpleCache\CacheInterface;
use Exception;
use mokuyu\database\exception\QueryParamException;
use PDOStatement;
use PDOException;

/**
 * 模型类,实例化一次进行一次查询
 */
abstract class Model extends Mokuyu
{
    /**
     * 定义写入的时间戳字段名
     * @var string
     */
    protected string $createTimeField = 'create_time';
    protected string $updateTimeField = 'update_time';

    /**
     * 是否写入时间戳
     * @var bool
     */
    protected bool $isWriteTimestamp = true;


    /**
     * 是否自动创建时间戳字段
     * @var bool
     */
    protected bool $autoCreateTimeFields = false;

    /**
     * 是否自动创建timestamp自动格式化时间字段,默认值为CURRENT_TIMESTAMP,更新时间段会自动更新,不需要额外维护
     * 需要开启自动创建时间戳字段此设置才生效
     * @var bool
     */
    protected bool $autoCreateTimestampFields = true;

    /**
     * 数据库连接配置
     */
    protected ?array $connect = null;
    /**
     * 插入操作
     */
    protected const ACTION_INSERT = 0;

    /**
     * 更新操作
     */
    protected const ACTION_UPDATE = 1;

    /**
     * 添加或更新时自动处理的字段，如果是键值对,则值就是这个字段的值，否则自动使用本类 set[Field]Attr 方法返回值来设置这个字段
     * 添加时不管数据中有没有此字段都会使用本类成员函数解析处理
     * 更新时如果更新数据中有此字段才会处理,没有的话会直接忽略
     * 下面插入和更新自动处理规则跟这里一样
     * @var array
     */
    protected array $auto = [];

    /**
     * 添加数据时自动处理指定字段,处理规则和auto一至
     * @var array
     */
    protected array $insert = [];

    /**
     * 更新数据时自动处理指定字段,处理规则和auto一至
     * @var array
     */
    protected array $update = [];

    /**
     * 在插入数据前拦截并删除指定字段
     * @var array
     */
    protected array $beforeInsertDelete = [];

    /**
     * 在更新前拦截并删除指定字段
     * @var array
     */
    protected array $beforeUpdateDelete = [];

    // /**
    //  * @var Mokuyu|null 数据库连接对象
    //  */
    // protected ?Mokuyu $db = null;

    /**
     * 查询字段,如果使用字段映射(fieldMap)的话,请使用字段的别名
     * @var mixed
     */
    protected $field = [];

    /**
     * 字段映射
     * 格式为 别名(查询)字段=>数据库真实字段
     * 场景：文章表中字段为create_time,但想使用add_time去查询,做映射后就可以使用add_time查询,不映射则会提示add_time不存在
     * @var array
     */
    protected array $fieldMap
                                 = [
            //格式为 别名(查询)字段=>数据库真实字段
            // 'push_time' => 'create_time',
        ];
    private array   $temFieldMap = [];
    /**
     * 设置当前数据表字段风格,传入的字段会转为此种风格后再去查询,fieldMap中设置的(别名/真实)字段同样会被转换
     * 0:原样不动，1:转换为下划线风格，2:转换为驼峰风格
     * @var int
     */
    protected int $fieldMode    = 0;
    private int   $temFieldMode = 0;


    /**
     * 模型默认的联表查询条件
     * @var array
     */
    protected array $join = [];

    /**
     * 模型默认的limit条件
     * @var string
     */
    protected string $limit = '';

    /**
     * 模型默认的排序
     * @var string
     */
    protected string $order = '';

    /**
     * 此模型绑定的表,如果为空则自动按当前类名处理
     * @var string|null
     */
    protected ?string $tableName = null;

    /**
     * 默认查询条件
     * @var array
     */
    protected array $where = [];

    /**
     * 数据表风格,把传入的表名转为下面
     * 0:原样不动，1:转换为下划线风格，2:转换为驼峰风格
     * @var int
     */
    protected int $tableMode    = 1;
    private int   $temTableMode = 1;

    /**
     * 追加字段/属性
     * @var array
     */
    protected array $append = [];

    /**
     * 初始化模型
     * Model constructor.
     * @param array $config
     * @param null  $tableName
     * @throws PDOException
     */
    public function __construct(array $config = [], $tableName = null)
    {
        if ($tableName !== null) {
            $this->tableName = $tableName;
        }
        $this->connect = $config ?: $this->connect;
        //先初始化数据库
        parent::__construct($this->connect);
        //处理表名字
        if ($this->tableName === null) {
            $this->tableName = basename(str_replace('\\', '/', static::class));

            //如果表名字不存在则置空,还没有初始化配置,所以此处不能判断表是否存在
            $tables = $this->getTables();
            if ($tables && !in_array($this->connect['prefix'] . $this->parseName($this->tableName, 1), $tables)) {
                $this->tableName = '';
            }
        }
        $this->addEventListener(Mokuyu::EVENT_TYPE_PRE_QUERYPARAM_BEFORE, [$this, 'handlerInitQuery']);
        $this->addEventListener(Mokuyu::EVENT_TYPE_RESET_QUERYPARAM, [$this, 'handlerResetQueryParam']);
        $this->addEventListener(Mokuyu::EVENT_TYPE_INSERT_BEFORE, [$this, 'insertBefore']);
        $this->addEventListener(Mokuyu::EVENT_TYPE_INSERT_AFTER, [$this, 'insertAfter']);
        $this->addEventListener(Mokuyu::EVENT_TYPE_UPDATE_BEFORE, [$this, 'updateBefore']);
        $this->addEventListener(Mokuyu::EVENT_TYPE_UPDATE_AFTER, [$this, 'updateAfter']);
        $this->handlerResetQueryParam();

    }

    /**
     * 插入之前
     */
    protected function insertBefore(Mokuyu $db, string $sql, array $bindParam)
    {

    }

    /**
     * 插入之后
     * @param Mokuyu $db
     * @param string $sql
     * @param array  $bindParam
     * @param mixed  $primaryId
     */
    protected function insertAfter(Mokuyu $db, string $sql, array $bindParam, $primaryId)
    {

    }

    /**
     * 更新之前
     * @param Mokuyu $db
     * @param string $sql
     * @param array  $bindParam
     */
    protected function updateBefore(Mokuyu $db, string $sql, array $bindParam)
    {

    }

    /**
     * 更新之后
     * @param Mokuyu $db
     * @param string $sql
     * @param array  $bindParam
     * @param int    $result
     */
    protected function updateAfter(Mokuyu $db, string $sql, array $bindParam, int $result)
    {

    }

    /**
     * 添加数据,支持批量添加
     * @param array $datas
     * @return bool|false|int|mixed|string
     * @throws Exception
     */
    public function add(array $datas)
    {
        if (!isset($datas[0])) {
            $datas = [$datas];
        }
        $this->autoCreateField();
        //取表的所有字段
        $table_fields = $this->getFields();
        foreach ($datas as $key => &$data) {
            //自动添加创建和更新字段的时间戳
            if (in_array($this->createTimeField, $table_fields) && !array_key_exists($this->createTimeField, $data)) {
                $data[$this->createTimeField] = time();
            }
            if (in_array($this->updateTimeField, $table_fields) && !array_key_exists($this->updateTimeField, $data)) {
                $data[$this->updateTimeField] = time();
            }
            $this->autoData($data, self::ACTION_INSERT);
            $this->preFieldOnInsert($data);
            $this->deleteFieldOnInsert($data);
            // $datas[$key] = $data;
        }

        return parent::add($datas);
    }

    /**
     * 更新数据,支持批量
     * @param array $datas
     * @return bool|false|int|string
     * @throws QueryParamException
     */
    public function update(array $datas)
    {
        if (!isset($datas[0])) {
            $datas = [$datas];
        }
        $this->autoCreateField();
        $table_name = trim($this->queryParams['table'], $this->yinhao);
        $table_name = $this->parseName(str_replace($this->prefix, '', $table_name), 3);
        //取表的所有字段
        $table_fields = $this->getFields();
        foreach ($datas as $key => &$data) {
            if (in_array($this->updateTimeField, $table_fields) && !array_key_exists($this->updateTimeField, $data)) {
                $data[$table_name . '.' . $this->updateTimeField] = time();
                // $datas[$key]                                      = $data;
            }
            $this->autoData($data, self::ACTION_UPDATE);
            $this->preFieldOnUpdate($data);
            $this->deleteFieldOnUpdate($data);
            // $datas[$key] = $data;
        }

        return parent::update($datas);
    }

    /**
     * 自动添加时间字段,只要调试模式下生效
     */
    protected function autoCreateField()
    {
        if ($this->isDebug()) {
            $fields     = $this->getFields();
            $table_name = $this->queryParams['table'];
            //这里一定要判断下$table_name是不是为空,因为有些是没有表名字的
            if ($table_name && $fields && $this->autoCreateTimeFields) {
                if (!in_array($this->createTimeField, $fields)) {
                    $sql = 'ALTER TABLE ' . $table_name . ' ADD ' . $this->yinhao . $this->createTimeField . $this->yinhao . ' int(11) unsigned NOT NULL DEFAULT \'0\' COMMENT \'记录创建时间\'';
                    $this->pdoWrite->exec($sql);
                }
                if (!in_array($this->updateTimeField, $fields)) {
                    $sql = 'ALTER TABLE ' . $table_name . ' ADD ' . $this->yinhao . $this->updateTimeField . $this->yinhao . ' int(11) unsigned NOT NULL DEFAULT \'0\' COMMENT \'记录更新时间\'';
                    $this->pdoWrite->exec($sql);
                }
                if ($this->autoCreateTimestampFields && !in_array('auto_' . $this->updateTimeField, $fields)) {
                    $sql = 'ALTER TABLE ' . $table_name . ' ADD ' . $this->yinhao . 'auto_' . $this->updateTimeField . $this->yinhao . ' timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT \'自动创建时间字符串\'';
                    $this->pdoWrite->exec($sql);
                }
                if ($this->autoCreateTimestampFields && !in_array('auto_' . $this->updateTimeField, $fields)) {
                    $sql = 'ALTER TABLE ' . $table_name . ' ADD ' . $this->yinhao . 'auto_' . $this->updateTimeField . $this->yinhao . ' timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT \'自动更新时间字符串\'';
                    $this->pdoWrite->exec($sql);
                }
            }

        }
    }

    /**
     * 重置单次请求的参数值
     */
    protected function handlerResetQueryParam()
    {
        $this->table($this->tableName);
    }

    /**
     * @return array|bool|PDOStatement|string
     * @throws QueryParamException
     */
    public function select()
    {
        $result = parent::select();
        $this->parseAppendField($result);
        return $result;
    }

    /**
     * @param int $id
     * @return bool|mixed|string
     * @throws QueryParamException
     */
    public function get(int $id = 0)
    {
        $result = parent::get($id);
        $this->parseAppendField($result);
        return $result;
    }

    public function paginate(int $page = 1, int $pageSize = 15)
    {
        $result = parent::paginate($page, $pageSize);
        $this->parseAppendField($result);
        return $result;
    }

    /**
     * 解析追加的字段
     * @param $datas
     */
    protected function parseAppendField(&$datas)
    {
        if (!is_array($datas)) {
            return;
        }
        $isMul = count($datas) !== count($datas, 1);
        if (!$isMul) {
            $datas = [$datas];
        }
        foreach ($datas as $key => $data) {
            foreach ($this->append as $field) {
                $funcName = 'get' . str_replace('_', '', $field) . 'Attr';
                if (is_callable([$this, $funcName])) {
                    $datas[$key][$field] = $this->$funcName($data);
                }
            }
        }
        if (!$isMul) {
            $datas = $datas[0];
        }


    }

    /**
     * 添加数据
     * @param array $datas
     * @return mixed
     */
    // public function add(array $datas)
    // {
    //     if (count($datas) === count($datas, 1)) {
    //         $datas = [$datas];
    //     }
    //     foreach ($datas as $key => $data) {
    //         $this->autoData($data, self::ACTION_INSERT);
    //         $this->preFieldOnInsert($data);
    //         $this->deleteFieldOnInsert($data);
    //         $datas[$key] = $data;
    //     }
    //     // $this->initQuery();
    //
    //     return parent::add($datas);
    // }

    /**
     * 更新数据
     * @param array $datas
     * @return mixed
     */
    // public function update(array $datas)
    // {
    //     if (count($datas) === count($datas, 1)) {
    //         $datas = [$datas];
    //     }
    //     foreach ($datas as $key => $data) {
    //         $this->autoData($data, self::ACTION_UPDATE);
    //         $this->preFieldOnUpdate($data);
    //         $this->deleteFieldOnUpdate($data);
    //         $datas[$key] = $data;
    //     }
    //     // $this->initQuery();
    //
    //     return parent::update($datas);
    // }

    /**
     * 字段数据填充
     * @param      $key
     * @param      $value
     * @param      $data
     * @param null $action
     */
    protected function parseAutoField($key, $value, &$data, $action = null)
    {
        //如果键是数字则使用模型方法解析出值
        if (is_numeric($key)) {
            //如果是更新操作,并且更新数据里没有此字段的话,忽略本次处理
            if ($action === self::ACTION_UPDATE && !isset($data[$value])) {
                return;
            }
            $funcName = 'set' . str_replace('_', '', $value) . 'Attr';
            if (is_callable([$this, $funcName])) {
                $data[$value] = $this->$funcName($data[$value] ?? null);
            }
        }
        else {
            //如果是更新操作,并且更新数据里没有此字段的话,忽略本次处理
            if ($action === self::ACTION_UPDATE && !isset($data[$key])) {
                return;
            }
            //如果键是字段名字,则直接使用值设置
            $data[$key] = $value;
        }
    }

    /**
     * 添加或更新数据时自动插入字段
     * @param      $data
     * @param null $action
     */
    protected function autoData(&$data, $action = null)
    {
        //因为php方法名字不区分大小写，所以这里可以直接把_替换掉使用
        foreach ($this->auto as $key => $value) {
            $this->parseAutoField($key, $value, $data, $action);
        }
    }

    /**
     * 在插入数据时删除指定字段
     * @param $data
     */
    protected function deleteFieldOnInsert(&$data)
    {
        foreach ($data as $key => $value) {
            if (in_array($key, $this->beforeInsertDelete)) {
                unset($data[$key]);
            }
        }
    }

    /**
     * 在更新数据时删除指定字段
     * @param $data
     */
    protected function deleteFieldOnUpdate(&$data)
    {
        foreach ($data as $key => $value) {
            if (in_array($key, $this->beforeUpdateDelete)) {
                unset($data[$key]);
            }
        }
    }

    /**
     * 在插入数据时插入指定字段
     * @param      $data
     */
    protected function preFieldOnInsert(&$data)
    {
        //因为php方法名字不区分大小写，所以这里可以直接把_替换掉使用
        foreach ($this->insert as $key => $value) {
            $this->parseAutoField($key, $value, $data, self::ACTION_INSERT);
        }
    }

    /**
     * 在更新数据时插入指定字段
     * @param $data
     */
    protected function preFieldOnUpdate(&$data)
    {
        //因为php方法名字不区分大小写，所以这里可以直接把_替换掉使用
        foreach ($this->update as $key => $value) {
            $this->parseAutoField($key, $value, $data, self::ACTION_UPDATE);
        }
    }

    public function tableMode(int $type = 0): Model
    {
        $this->temTableMode = $type;
        return $this;
    }

    public function fieldMode(int $type = 0): Model
    {
        $this->temFieldMode = $type;
        return $this;
    }

    public function fieldMap(array $map): Model
    {
        $this->temFieldMap = $map;
        return $this;
    }


    /**
     * 初始化请求参数
     */
    protected function handlerInitQuery()
    {
        parent::tableMode((int)$this->temTableMode);
        parent::fieldMode((int)$this->temFieldMode);
        parent::fieldMap($this->temFieldMap);

        //如果没有传查询参数就用模型中默认的参数
        $params = parent::getQueryParams();
        $this->field && !$params['field'] && (parent::field($this->field));
        $this->join && !$params['join'] && (parent::join($this->join));
        $this->where && !$params['where'] && (parent::where($this->where));
        $this->order && !$params['order'] && (parent::order($this->order));
        $this->limit && !$params['limit'] && (parent::limit($this->limit));
    }
}
