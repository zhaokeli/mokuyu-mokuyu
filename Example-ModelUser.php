<?php
include __dir__ . '/vendor/autoload.php';
//mysql使用示例
$db = new \mokuyu\database\Mokuyu([
    // 必须配置项
    'database_type' => 'mysql',
    'database_name' => 'newtestdata',
    'server'        => 'localhost',
    'username'      => 'root',
    'password'      => 'adminrootkl',
    'charset'       => 'utf8',
    // 可选参数
    'port'          => 3306,
    'debug_mode'    => false,
    // 可选，定义表的前缀
    'prefix'        => 'kl_',
]);

/**
 * 测试类
 */
class User extends \mokuyu\database\Model
{
    protected $fieldMap
        = [
            //格式为 别名(查询)字段=>数据库真实字段
            'push_time' => 'create_time',
        ];

    protected $tableName = 'UserGroup';
}

$mod = new User($db);
var_dump($mod);
