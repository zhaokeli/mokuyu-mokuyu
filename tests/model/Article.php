<?php


namespace mokuyu\database\tests\model;


use mokuyu\database\Model;

class Article extends Model
{
    protected array $fieldMap
        = [
            //格式为 别名(查询)字段=>数据库真实字段
            'push_time' => 'create_time',
        ];

    protected ?string $tableName = 'Article';
    protected array   $append
                                 = [
            'view_text',
        ];

    public function getViewTextAttr($data): int
    {
        if ($data['views'] > 10) {
            return 10000;
        }
        else {
            return -1;
        }
    }
}