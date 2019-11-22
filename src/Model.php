<?php
declare (strict_types = 1);
namespace mokuyu\database;

/**
 * 模型类,实例化一次进行一次查询
 */
abstract class Model
{
    protected $db = null;

    /**
     * 字段别名,查询过程中会自动替换为真实的字段
     * @var array
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
     * 数据表风格,把传入的表名转为下面
     * 0:原样不动，1:转换为下划线风格，2:转换为驼峰风格
     * @var null
     */
    protected $tableMode = 1;

    /**
     * 模型表名字如果为空则自动以类名为表名
     * @var string
     */
    protected $tableName = '';

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

    public function __construct(Mokuyu $db)
    {
        if ($this->tableName === '') {
            // $tname = get_class($this);
            // //如果直接传来一个表名字的话就直接使用,如果是带命令空间的话要把 \ 去掉
            // $wz = strrpos($tname, '\\');
            // if ($wz !== false) {
            //     $tname = substr($tname, $wz + 1);
            // }
            // $this->tableName = $tname;
            $this->tableName = basename(str_replace('\\', '/', static::class));

        }
        $this->db = $db;
    }

    protected function initQuery()
    {
        $this->db->table($this->tableName);
        $this->db->tableMode($this->tableMode);
        $this->db->fieldMode($this->fieldMode);
        $this->db->fieldMap($this->fieldMap);
    }
}
