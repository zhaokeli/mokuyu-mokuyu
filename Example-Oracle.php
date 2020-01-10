<?php
// $newdataname = 'newtestdata';
// $databaseSql = <<<eot
// CREATE DATABASE IF NOT EXISTS {$newdataname} DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci;
// eot;
// $datatableSql = <<<eot
// DROP TABLE IF EXISTS `kl_article`;
// CREATE TABLE `kl_article` (
//   `article_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
//   `title` varchar(255) NOT NULL DEFAULT '',
//   `views` int(11) NOT NULL DEFAULT '0' COMMENT '浏览次数',
//   `create_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
//   `update_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
//   PRIMARY KEY (`article_id`)
// ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
// eot;
// ////////使用pdo对象创建一个测试数据库//////////////
// try {
//     $pdo = new PDO(
//         'mysql:host=localhost;port=3306;',
//         'root',
//         'adminrootkl',
//         [
//             PDO::ATTR_CASE => PDO::CASE_NATURAL,
//         ]
//     );
// } catch (PDOException $e) {
//     echo '数据库连接失败' . $e->getMessage();
// }

// //查询数据库是否存在
// $result = $pdo->exec($databaseSql);
// if (!$result) {
//     die('create database error!');
// }

// $pdo->exec("use {$newdataname}");
// $result = $pdo->exec($datatableSql);
// echo 'database success!';
// die();
//////////////////////类库使用示例////////////////////////////////////////

include __dir__ . '/vendor/autoload.php';
//mysql使用示例
$query = new \mokuyu\database\Mokuyu([
    // 必须配置项
    'database_type' => 'oracle',
    'database_name' => 'mokuyu',
    'server'        => '192.168.201.138',
    'username'      => 'system',
    'password'      => 'adminrootkl',
    'charset'       => 'utf8',
    // 可选参数
    'port'          => 1521,
    'debug_mode'    => false,
    'table_mode'    => 1,
    // 可选，定义表的前缀
    'prefix'        => 'kl_',
]);
// $model = new \ank\database\Model();
// $query = new \ank\database\Query($conn);
// var_dump($query);
$result = $query->table('article')->add([
    'title'       => 'title',
    // 'descript'    => 'descr',
    'create_time' => time(),
    'update_time' => time(),
]);
// echo $result . PHP_EOL;
// $list = $query->table('article')->select();
// $list = $query->table('LOGSTDBY$SKIP_SUPPORT')->select();
// var_dump($list);
// die();
//添加数据
$datanum = 100;
$datas   = [];
while (--$datanum > 0) {

    $datas[] = [
        'title'       => 'thisisphpdata!' . rand(100, 1000),
        'views'       => rand(100, 1000),
        'create_time' => time(),
        'update_time' => time(),
    ];
    //添加单个
    // $result = $query->abort(false)->table('article')->add([
    //     'title' => 'thisisphpdata!' . rand(100, 1000),
    //     'views' => rand(100, 1000),
    // ]);

    // die();
    // echo $result . "\n";
}
$result = $query->abort(false)->table('article')->add($datas);
// echo $result;
// die();
//测试平均数
$value1 = $query->table('article')->avg(['views', 'article_id']);
$value2 = $query->table('article')->avg('article.views, article_id');
$value3 = $query->table('article')->count();
$value4 = $query->table('article')->count('views');
var_dump($value1);
// //更新数据
// $result = $query->table('article')->where(['article_id' => 50])->update(['title' => '这个数据被更新啦']);
// echo "update result article_id 50: {$result}\n";

// //删除数据
// $result = $query->table('article')->delete();
// echo "Delete result:{$result}\n";
// $result = $query->table('article')->where(['article_id[ > ]' => 51])->delete();
// echo "Delete article_id > 51 result:{$result}\n";
// $result = $query->table('article')->abort(false)->delete(51);
// echo "quick delete article_id=51:{$result}\n";
// //查询数据
$list = $query->table('event_log')
              ->abort(false)
              ->fetchSql()
              ->field([
                  'distinct(visitor . visitor_uuid)',
                  'event_name',
                  'title[ptitle]',
                  'push_time',
              ])
              ->where([
                  'and'    => [
                      'wuliu_name'     => 'sf',
                      'push_time[ < ]' => 10555555,

                  ],
                  'or'     => [
                      'routes'      => '',
                      'status[ > ]' => 11,
                  ],
                  'status' => ['in', [11, 2]],
              ])
              ->join([
                  //两个表同一个字段相同
                  '[ > ]visitor'  => ['visitor_uuid'],
                  //LEFT JOIN `kl_visitor` USING (`visitor_uuid`)

                  //两个表两个字段都相同
                  '[ > ]tongji'   => ['visitor_uuid', 'tongji_id'],
                  '[ > ]tongji2'  => ['push_time', 'tj . push_time'],
                  //LEFT JOIN `kl_tongji` USING (`visitor_uuid`, `tongji_id`)

                  //主表的uid和当前表的user_id相同
                  '[ > ]user'     => ['uid' => 'user_id'],
                  //LEFT JOIN `kl_user` ON `kl_event_log`.`uid` = `kl_user`.`user_id`

                  //多个条件相同
                  '[ > ]visitor2' => [
                      //主表的author_id等于visitor2.user_id
                      'author_id'        => 'user_id',
                      //user.user_id=visitor2.user_id
                      'user . user_id'   => 'user_id',
                      'test . push_time' => 'log . push_time',
                  ],
                  //LEFT JOIN `kl_visitor2` ON `kl_event_log`.`author_id` = `kl_visitor2`.`user_id` AND `kl_user`.`user_id` = `kl_visitor2`.`user_id`
              ])
              ->order([
                  'user_iddesc',
                  'usernamedesc',
                  'push_timedesc',
              ])
              ->limit(0, 5)
              ->select();
var_dump($list) . "\r\n";

//更新数据
$res = $query->table('visitor')
             ->abort(false)
             ->fetchSql()
             ->where(['visitor_id' => $result])
             ->update(['visitor_uuid' => 9999888889]);
var_dump($res);
$res = $query->table('event_log')
             ->abort(false)
             ->fetchSql()
             ->count();
var_dump($res);

$res = $query->table('event_log')
             ->abort(false)
             ->fetchSql()
             ->where([
                 'event_log_id' => 1000,
             ])
             ->has();
var_dump($res);

$res = $query->table('event_log')
             ->abort(false)
             ->fetchSql()
             ->where([
                 'event_log_id' => 1000,
             ])
             ->setInc('views', 1);

var_dump($res);

$res = $query->table('event_log')
             ->abort(false)
             ->fetchSql()
             ->where([
                 'event_log_id' => 1000,
             ])
             ->setDec('views', 1);
var_dump($res);
