<?php


namespace mokuyu\tests\model;


use mokuyu\database\Model;

class Article extends Model
{
    protected $fieldMap
        = [
            //格式为 别名(查询)字段=>数据库真实字段
            'push_time' => 'create_time',
        ];

    protected $tableName = 'Article';
    protected $append
                         = [
            'view_text',
        ];

    public function getViewTextAttr($data)
    {
        if ($data['views'] > 10) {
            return 10000;
        }
        else {
            return -1;
        }
    }
}