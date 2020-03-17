<?php
declare (strict_types = 1);

namespace mokuyu\database;

/**
 * 模型类,实例化一次进行一次查询
 */
abstract class Model
{
    /**
     * 添加或更新时自动处理的字段，如果是键值对,则值就是这个字段的值，否则自动使用本类 set[Field]Attr 方法返回值来设置这个字段
     * @var array
     */
    protected $auto = [];

    /**
     * 添加数据时自动处理指定字段,处理规则和auto一至
     * @var array
     */
    protected $insert = [];

    /**
     * 更新数据时自动处理指定字段,处理规则和auto一至
     * @var array
     */
    protected $update = [];

    /**
     * 在插入数据前删除指定字段
     * @var array
     */
    protected $beforeInsertDelete = [];

    /**
     * 在更新前删除指定字段
     * @var array
     */
    protected $beforeUpdateDelete = [];

    /**
     * @var null 数据库连接对象
     */
    protected $db = null;

    /**
     * 查询字段,如果使用字段映射(fieldMap)的话,请使用字段的别名
     * @var array
     */
    protected $field = [];

    /**
     * 字段映射,可以隐藏数据库真实字段
     * @var array
     */
    protected $fieldMap
        = [
            //格式为 别名(查询)字段=>数据库真实字段
            // 'push_time' => 'create_time',
        ];

    /**
     * 字段风格,把传入的字段转为下面，此转换不受字段映射的影响,不管是真实字段还是映射字段都会转换
     * 0:默认字段，1:转换为下划线风格，2:转换为驼峰风格
     * @var null
     */
    protected $fieldMode = 0;


    /**
     * 模型默认的联表查询条件
     * @var array
     */
    protected $join = [];

    /**
     * 模型默认的limit条件
     * @var string
     */
    protected $limit = '';

    /**
     * 模型默认的排序
     * @var string
     */
    protected $order = '';

    /**
     * 此模型绑定的表,如果为空则自动按当前类名处理
     * @var string|string[]|null
     */
    protected $tableName = null;

    /**
     * 默认查询条件
     * @var array
     */
    protected $where = [];

    /**
     * 数据表风格,把传入的表名转为下面
     * 0:原样不动，1:转换为下划线风格，2:转换为驼峰风格
     * @var null
     */
    protected $tableMode = 1;

    public function __construct(Mokuyu $db, $tableName = null)
    {
        if ($tableName !== null) {
            $this->tableName = $tableName;
        }
        if ($this->tableName === null) {
            $this->tableName = basename(str_replace('\\', '/', static::class));
        }
        $this->db = $db;
    }

    public function __call(string $method, array $params)
    {
        if (in_array(strtolower($method), ['select', 'has', 'get', 'add', 'update', 'delete', 'fieldoperation', 'setInc', 'setDec', 'max', 'min', 'avg', 'count', 'sum'])) {
            $this->initQuery();
        }
        $result = call_user_func_array([$this->db, $method], $params);
        if ($result instanceof Mokuyu) {
            return $this;
        }

        return $result;
    }


    /**
     * 添加数据
     * @param array $data
     * @return mixed
     */
    public function add(array $datas)
    {
        if (count($datas) === count($datas, 1)) {
            $datas = [$datas];
        }
        foreach ($datas as $key => $data) {
            $this->autoData($data);
            $this->preFieldOnInsert($data);
            $this->deleteFieldOnInsert($data);
            $datas[$key] = $data;
        }
        $this->initQuery();

        return $this->db->add($datas);
    }

    /**
     * 更新数据
     * @param array $data
     * @return mixed
     */
    public function update(array $datas)
    {
        if (count($datas) === count($datas, 1)) {
            $datas = [$datas];
        }
        foreach ($datas as $key => $data) {
            $this->autoData($data);
            $this->preFieldOnUpdate($data);
            $this->deleteFieldOnUpdate($data);
            $datas[$key] = $data;
        }
        $this->initQuery();

        return $this->db->update($datas);
    }

    /**
     * 字段数据填充
     */
    protected function parseAutoField($key, $value, &$data)
    {
        if (is_numeric($key)) {
            $funcName = 'set' . str_replace('_', '', $value) . 'Attr';
            if (is_callable([$this, $funcName])) {
                $da = '';
                if (isset($data[$value])) {
                    $da = $data[$value];
                }
                $data[$value] = $this->$funcName($da);
            }
        }
        else {
            $data[$key] = $value;
        }
    }

    /**
     * 添加或更新数据时自动插入字段(字段不存在的话)
     * @param $data
     */
    protected function autoData(&$data)
    {
        //因为php方法名字不区分大小写，所以这里可以直接把_替换掉使用
        foreach ($this->auto as $key => $value) {
            $this->parseAutoField($key, $value, $data);
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
     * 在插入数据时插入指定字段(不存在的字段)
     * @param $data
     */
    protected function preFieldOnInsert(&$data)
    {
        //因为php方法名字不区分大小写，所以这里可以直接把_替换掉使用
        foreach ($this->insert as $key => $value) {
            $this->parseAutoField($key, $value, $data);
        }
    }

    /**
     * 在更新数据时插入指定字段(不存在的字段)
     * @param $data
     */
    protected function preFieldOnUpdate(&$data)
    {
        //因为php方法名字不区分大小写，所以这里可以直接把_替换掉使用
        foreach ($this->update as $key => $value) {
            $this->parseAutoField($key, $value, $data);
        }
    }

    protected function initQuery()
    {
        $this->db->table($this->tableName);
        $this->db->tableMode($this->tableMode);
        $this->db->fieldMode($this->fieldMode);
        $this->db->fieldMap($this->fieldMap);

        $params = $this->db->getQueryParams();
        //如果没有传查询参数就用模型中默认的参数
        $this->field && !$params['field'] && ($this->db->field($this->field));
        $this->join && !$params['join'] && ($this->db->join($this->join));
        $this->where && !$params['where'] && ($this->db->where($this->where));
        $this->order && !$params['order'] && ($this->db->order($this->order));
        $this->limit && !$params['limit'] && ($this->db->limit($this->limit));
    }
}
