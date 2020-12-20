<?php
declare (strict_types = 1);

namespace mokuyu\database;

use Closure;
use Psr\SimpleCache\CacheInterface;

/**
 * 模型类,实例化一次进行一次查询
 */
abstract class Model extends Mokuyu
{
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

    /**
     * @var Mokuyu|null 数据库连接对象
     */
    protected ?Mokuyu $db = null;

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
     */
    public function __construct(array $config = [], $tableName = null)
    {
        if ($tableName !== null) {
            $this->tableName = $tableName;
        }
        if ($this->tableName === null) {
            $this->tableName = basename(str_replace('\\', '/', static::class));
        }
        // $this = $db;
        $this->addEventListener(Mokuyu::EVENT_TYPE_PRE_QUERYPARAM_BEFORE, [$this, 'handlerInitQuery']);
        $this->addEventListener(Mokuyu::EVENT_TYPE_RESET_QUERYPARAM, [$this, 'handlerResetQueryParam']);
        $this->handlerResetQueryParam();
        parent::__construct($config ?: $this->connect);
    }

    /**
     * 重置单次请求的参数值
     */
    public function handlerResetQueryParam()
    {
        // $this->temFieldMode = $this->fieldMode;
        // $this->temTableMode = $this->tableMode;
        // $this->temFieldMap  = $this->fieldMap;
        $this->table($this->tableName);
        // if ($isRemoveListener || !isset($isRemoveListener)) {
        //     $this->removeEventListener(Mokuyu::EVENT_TYPE_PRE_QUERYPARAM_BEFORE, [$this, 'handlerInitQuery']);
        //     $this->removeEventListener(Mokuyu::EVENT_TYPE_RESET_QUERYPARAM, [$this, 'handlerResetQueryParam']);
        // }
    }

    // public function __call(string $method, array $params)
    // {
    //     $method = strtolower($method);
    //     // if (in_array($method, explode(',', strtolower('select,column,chunk,insert,update,delete,save,get,has,paginate,min,max,avg,sum,count,getPK,getFields,setInc,setDec,fieldOperation')))) {
    //     //     $this->initQuery();
    //     // }
    //     $result = call_user_func_array([$this, $method], $params);
    //     if ($result instanceof Mokuyu) {
    //         return $this;
    //     }
    //     //追加字段
    //     if (in_array($method, ['select', 'get', 'paginate'])) {
    //         $this->parseAppendField($result);
    //     }
    //     return $result;
    // }

    public function select()
    {
        $result = parent::select();
        $this->parseAppendField($result);
        return $result;
    }

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
    public function add(array $datas)
    {
        if (count($datas) === count($datas, 1)) {
            $datas = [$datas];
        }
        foreach ($datas as $key => $data) {
            $this->autoData($data, self::ACTION_INSERT);
            $this->preFieldOnInsert($data);
            $this->deleteFieldOnInsert($data);
            $datas[$key] = $data;
        }
        // $this->initQuery();

        return parent::add($datas);
    }

    /**
     * 更新数据
     * @param array $datas
     * @return mixed
     */
    public function update(array $datas)
    {
        if (count($datas) === count($datas, 1)) {
            $datas = [$datas];
        }
        foreach ($datas as $key => $data) {
            $this->autoData($data, self::ACTION_UPDATE);
            $this->preFieldOnUpdate($data);
            $this->deleteFieldOnUpdate($data);
            $datas[$key] = $data;
        }
        // $this->initQuery();

        return parent::update($datas);
    }

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


    // /**
    //  * 初始化请求参数
    //  */
    public function handlerInitQuery()
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
