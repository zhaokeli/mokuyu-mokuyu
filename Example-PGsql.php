<?php
$newdataname = 'newtestdata';
$databaseSql = <<<eot
CREATE DATABASE "{$newdataname}"
eot;
$datatableSql = <<<eot
CREATE TABLE public.kl_article
(
  article_id SERIAL primary key ,
  title character varying,
  views int default 0
)
eot;
////////使用pdo对象创建一个测试数据库//////////////
try {
    $pdo = new PDO(
        'pgsql:host=localhost;port=5432;',
        'postgres',
        'adminrootkl',
        [
            PDO::ATTR_CASE => PDO::CASE_NATURAL,
        ]
    );
} catch (PDOException $e) {
    echo '数据库连接失败' . $e->getMessage();
}

//查询数据库是否存在
$result = $pdo->exec($databaseSql);

// if (!$result) {
//     var_dump($result);
//     if ($pdo->errorCode() != '00000') {
//         var_dump($pdo->errorInfo());
//         // $this->showError(end($this->_errors));
//         die('create database error!');
//     }
// }

$pdo->exec('\\c ' . $newdataname);
$err    = $pdo->errorInfo();
$result = $pdo->exec($datatableSql);
$err    = $pdo->errorInfo();
// echo 'database success!';
// die();
//////////////////////类库使用示例////////////////////////////////////////

include __dir__ . '/vendor/autoload.php';
//mysql使用示例
$query = new \mokuyu\database\Mokuyu([
    // 必须配置项
    'database_type' => 'pgsql',
    'database_name' => $newdataname,
    'server'        => 'localhost',
    'username'      => 'postgres',
    'password'      => 'adminrootkl',
    'charset'       => 'utf8',
    // 可选参数
    'port'          => 5432,
    'debug_mode'    => false,
    // 可选，定义表的前缀
    'prefix'        => 'kl_',
]);
// $model = new \mokuyu\database\Model();
// $query = new \mokuyu\database\Query($conn);
//添加数据
$datanum = 100;
while (--$datanum > 0) {

    $result = $query->debug(false)->table('article')->add([
        'title' => 'this is php data!' . rand(100, 1000),
        'views' => rand(100, 1000),
    ]);
    // die();
    echo $result . "\n";
}
// //更新数据
$result = $query->table('article')->where(['article_id' => 50])->update(['title' => '这个数据被更新啦']);
echo "update result article_id 50: {$result}\n";

//删除数据
$result = $query->table('article')->delete();
echo "Delete result:{$result}\n";
$result = $query->table('article')->where(['article_id[>]' => 51])->delete();
echo "Delete article_id > 51 result:{$result}\n";
$result = $query->table('article')->debug(false)->delete(51);
echo "quick delete article_id=51:{$result}\n";
// //查询数据
$list = $query->table('event_log')
              ->debug(false)
              ->fetchSql()
              ->field([
                  'distinct(visitor.visitor_uuid)',
                  'event_name',
                  'title[ptitle]',
                  'push_time',
              ])
              ->where([
                  'and'    => [
                      'wuliu_name'   => 'sf',
                      'push_time[<]' => 10555555,

                  ],
                  'or'     => [
                      'routes'    => '',
                      'status[>]' => 11,
                  ],
                  'status' => ['in', [11, 2]],
              ])
              ->join([
                  //两个表同一个字段相同
                  '[>]visitor'  => ['visitor_uuid'],
                  //LEFT JOIN `kl_visitor` USING (`visitor_uuid`)

                  //两个表两个字段都相同
                  '[>]tongji'   => ['visitor_uuid', 'tongji_id'],
                  '[>]tongji2'  => ['push_time', 'tj.push_time'],
                  //LEFT JOIN `kl_tongji` USING (`visitor_uuid`, `tongji_id`)

                  //主表的uid和当前表的user_id相同
                  '[>]user'     => ['uid' => 'user_id'],
                  //LEFT JOIN `kl_user` ON `kl_event_log`.`uid` = `kl_user`.`user_id`

                  //多个条件相同
                  '[>]visitor2' => [
                      //主表的author_id等于visitor2.user_id
                      'author_id'      => 'user_id',
                      //user.user_id=visitor2.user_id
                      'user.user_id'   => 'user_id',
                      'test.push_time' => 'log.push_time',
                  ],
                  //LEFT JOIN `kl_visitor2` ON `kl_event_log`.`author_id` = `kl_visitor2`.`user_id` AND `kl_user`.`user_id` = `kl_visitor2`.`user_id`
              ])
              ->order([
                  'user_id desc',
                  'username desc',
                  'push_time desc',
              ])
              ->limit(0, 5)
              ->select();
var_dump($list) . "\r\n";

//更新数据
$res = $query->table('visitor')
             ->debug(false)
             ->fetchSql()
             ->where(['visitor_id' => $result])
             ->update(['visitor_uuid' => 9999888889]);
var_dump($res);
$res = $query->table('event_log')
             ->debug(false)
             ->fetchSql()
             ->count();
var_dump($res);

$res = $query->table('event_log')
             ->debug(false)
             ->fetchSql()
             ->where([
                 'event_log_id' => 1000,
             ])
             ->has();
var_dump($res);

$res = $query->table('event_log')
             ->debug(false)
             ->fetchSql()
             ->where([
                 'event_log_id' => 1000,
             ])
             ->setInc('views', 1);

var_dump($res);

$res = $query->table('event_log')
             ->debug(false)
             ->fetchSql()
             ->where([
                 'event_log_id' => 1000,
             ])
             ->setDec('views', 1);
var_dump($res);
