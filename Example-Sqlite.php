<?php
//sqllite使用示例
include __dir__ . '/vendor/autoload.php';
//初始化后会自动创建数据库文件
$db = new \mokuyu\database\Mokuyu([
    // 必须配置项
    'database_type' => 'sqlite',
    'database_file' => 'test.db',
    'charset'       => 'utf8',
    // 可选，定义表的前缀
    'prefix'        => 'kl_',
]);
//创建一个表
$db->exec('
DROP TABLE IF EXISTS "main"."kl_article";
CREATE TABLE "kl_article" (
"article_id"  INTEGER PRIMARY KEY AUTOINCREMENT,
"title"  text,
"views"  INTEGER NOT NULL DEFAULT 0,
"create_time"  INTEGER NOT NULL DEFAULT 0,
"update_time"  INTEGER NOT NULL DEFAULT 0
);
');

$data   = [
    'title'       => 'testusername',
    'views'       => rand(100, 1000),
    'create_time' => time(),
    'update_time' => time(),
];
$result = $db->table('article')->abort(false)->add($data);
echo $result . "\r\n";
$result = $db->table('article')->abort(false)->add($data);
echo $result . "\r\n";
$result = $db->table('article')->abort(false)->add($data);
echo $result . "\r\n";
$list = $db->table('article')->fetchSql()->select();
var_dump($list);
$result = $db->table('article')->fetchSql()->where(['article_id' => 1])->update(['title' => 'updateusername']);
var_dump($result);
$result = $db->table('article')->fetchSql()->abort(false)->delete(2);
var_dump($result);
echo $db->table('article')->getPk();
