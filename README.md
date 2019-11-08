# Mokuyu数据库操作
## composer 安装方法
```
composer require mokuyu/mokuyu
```
## 手动安装
本库可以缓存字段等信息，使用psr标准缓存接口 Psr\SimpleCache\CacheInterface; 请自己引入缓存标准接口文件  
[Psr\SimpleCache\CacheInterface 下载地址](https://github.com/php-fig/simple-cache/tree/master/src)
```
include __dir__.'/CacheInterface.php';
```

本数据库类的开发吸取medoo(符号查询)和thinkphp(连贯操作)的特点，感谢以上两个开源库
[toc]

## 数据库规范
* 表和字段名字全部小写,为方便阅读，尽量使用全名不要使用字母简写(名字长点其它无所谓)
* 字段名单词间用下划线分隔，或使用驼峰命名,不能混写
* 每个表都要有主键，主键名字格式为: 表名(不带前缀)_id,

>如果字段没有按下面的规范写,可以使用字段映射来一一对应，以上规范为强烈建议，非强制使用
## 特别注意
* 数据表解析时会把非首位的大写字母解析成下划线加小写字母，如userGroup/UserGroup 都会被解析为 db_user_group 
* 字段风格默认为不转换,0:默认字段，1:转换为下划线风格，2:转换为驼峰风格,查询时会按这些规则转换成真实的数据库字段进行查询


## 功能亮点和要求
* 进行添加或更新操作时可以自动过滤数据库不存在的字段
* 查询时可进行字段映射,前端字段映射到数据库真实字段
## 数据库缓存
如果要加快查询速度，则可以设置缓存对象,数据库会把数据表字段等信息保存下来，加快查询速度，设置的缓存对象要实现 DbCache 接口
## 连接数据库
### 连接mysql
```
$db = new \mokuyu\database\Mokuyu([
  // 必须配置项
  'database_type' => 'mysql',
  'database_name' => '******',
  'server'        => 'localhost',
  'username'      => '*****',
  'password'      => '******',
  'charset'       => 'utf8',
  // 可选参数
  'port'          => 3306,
  /**
   *  可选参数
   * 字段风格,把传入的字段转为下面,也可以在查询时使用fieldMode()设置临时风格
   * 0:默认字段，1:转换为下划线风格，2:转换为驼峰风格
   * @var null
   **/
  'field_mode'  =>0,

  // 可选参数，定义表的前缀
  'prefix'        => 'kl_',
  // 可选参数 设置只读服务器配置
  'read'=>[
    'server'        => 'localhost',
    'username'      => '*****',
    'password'      => '******',
    'port'          => 3306,
  ]
]);
```
### 连接pgsql
```
$db = new \mokuyu\database\Mokuyu([
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
```
### 连接sqlite
```
$db = new \mokuyu\database\Mokuyu([
  // 必须配置项
  'database_type' => 'sqlite',
  'database_file' => 'stat.db',
  'charset'       => 'utf8',
  // 可选，定义表的前缀
  'prefix'        => 'kl_',
]);
```

## 连贯操作
>
```
$db->table('user')
    ->field('user_id,username')
    ->where(['user_id'=>1])
    ->limit(1,10)
    ->order('user_id desc')
    ->select()
```
### getPK()
取当前表主键
### fieldMap()
字段映射
设置查询字段和数据库真实字段的映射
```
[
  //格式为 别名(查询)字段=>数据库真实字段
  // 'push_time' => 'create_time',
]
```
### fieldMode()
 * 字段风格,把传入的字段转为下面,也可以在查询时使用fieldMode()设置临时风格
 * 0:原样不动，1:转换为下划线风格，2:转换为驼峰风格
 
### tableMode()
 * 字段风格,把传入的字段转为下面,也可以在查询时使用fieldMode()设置临时风格
 * 0:原样不动，1:转换为下划线风格，2:转换为驼峰风格

### field()
字段可以直接使用原生格式，不用加反引号,会自动识别并添加上

```
$field=[
'distinct(username)',
'distinct(visitor.uuid)',
'password',
'nickname[nname]',//使用别名nname
'rcontact.alias[rc_alias]',给指定表中的字段设置别名
];
//sql: distinct(`username`),distinct(`visitor`.`uuid`) ,`password`,`nickname` as `nname`,`kl_rcontact`.`alias` AS `rc_alias`
```
也可以直接传字符串
```
$field='distinct(username),password,nickname[name]';
```
### where()
其中的and和or键可以任意嵌套，顶层默认为and连接
```
$map=[
  'and'=>[
    'user_id'=>1,
    'user_name'=>'use'
  ],
  'or'=>[
    'nickname'=>'joke',
    //同一个字符多个or的情况后面加 __1234  累加数字就可以
    'nickname__1'=>'keli',
    'nickname__2'=>'keli2',
    'logintimes[<]'=>10,
  ],
  'create_time[>]'=>1502365987
];
//sql: (user_id=1 and user_name='use') and (nickname='joke' or nickname='keli' or nickname='keli2' or logintimes<10)  and create_time>1502365987
```
查询条件有两种方式添加
第一种在字段上添加标识符如下
>
* field=>100
* field[>]=>100 ====  field>100
* field[>=]=>100 ====  field>=100
* field[!]=>100 ====  field!=100
* field[<>]=>[100,200] ====  field BETWEEN 100 AND 200
* field[><]=>[100,200] ====  field NOT BETWEEN 100 AND 200
* field[~]=>'%stss' ====  field like '%stss'
* field[>]=>100 ====  field>100
* field[>]=>100 ====  field>100

第二种，使用数组中传标识符
```
$map=[
  'field'=>'1',
  'field'=>['in',['1','2','3']],
  'field'=>['gt',100],
  'field'=>['lt',100],
  'field'=>['egt',100],
  'field'=>['elt',100],
  'field'=>['eq',100],
  'field'=>['neq',100],
];
```

### limit()
参数为(1,10)或(10)[0,10]
### order()
参数为字符串(user_id desc,username desc)也可以为数组
如果多表联合查询的时候有相同字段那么可以直接使用表名,如wechat.update_time最终解析为kl_wechat.update
### rand()
没有参数
如果这个函数被调用则会覆盖上面的排序,会使用随机排序
### group()
参数为字符串(username)
### join()
```
主表就是使用table设置的表
// [>] == LEFT JOIN
// [<] == RIGH JOIN
// [<>] == FULL JOIN
// [><] == INNER JOIN
    //两个表同一个字段相同
    '[>]visitor'  => ['visitor_uuid'],
    //LEFT JOIN `kl_visitor` USING (`visitor_uuid`)

    //两个表两个字段都相同
    '[>]tongji'   => ['visitor_uuid', 'tongji_id'],
    //LEFT JOIN `kl_tongji` USING (`visitor_uuid`, `tongji_id`)

    //主表的uid和当前表的user_id相同,请注意:主表字段做为key,后面的值不需要加表名，会自动加上
    '[>]user'     => ['uid' => 'user_id'],
    //LEFT JOIN `kl_user` ON `kl_event_log`.`uid` = `kl_user`.`user_id`

    //多个条件相同
    '[>]visitor2' => [
      //主表的author_id等于visitor2.user_id
      "author_id"    => "user_id",
      //user.user_id=visitor2.user_id
      "user.user_id" => "user_id",
    ],
    //LEFT JOIN `kl_visitor2` ON `kl_event_log`.`author_id` = `kl_visitor2`.`user_id` AND `kl_user`.`user_id` = `kl_visitor2`.`user_id`
```
### select()

从数据库取回指定数据返回一个多维数据
### get()

从数据库中取回一条数据返回一维数组，如果只有一个字段就返回这个字段的值
### has()

从数据库中查询数据存在不。返回true false

## 原生SQL
有返回值的使用query,
```
//执行sql后，会自动返回所有数据
$sql='select * from kl_event_log';
$db->query($sql);
```
如果是一些更新插入操作,没有返回值使用exec
```
$db->exec("CREATE TABLE table (
  c1 INT STORAGE DISK,
  c2 INT STORAGE MEMORY
) ENGINE NDB;");
// 注意:有些语句是没有影响行数的,如上所示,这个时候会自动判断有没有错误,如果有错误会返回false,
// 可以使用 getLastError() 获取最后一次执行后的错误提示

```
## 添加数据
成功返回自增id值,添加时要使用sql中的函数可在字段前面加个‘#’就可以使用,批量添加可以用多维数组，会自动使用事务进行操作，(表引擎要使用InnoDB),批量添加返回值不会返回自增id这点要注意
```
$db->table('log')->add([
'content'=>'neirong',
'#addtime'=>'NOW()'
  ]);
```

## 更新数据
成功返回影响行数
```
$db->table('log')
    ->where(['id'=>1])
    ->update($data);
```
条件为空时不更新任何数据,确实是想更新全表请传 1=1
另外如果想在原有字段上执行算术运算可以用下面方法
```
$db->table('log')
->where(['id'=>1])
->update([
  'views[+]'=>2,
  'views[-]'=>2,
  'views[*]'=>2,
  'views[/]'=>2,
]);
```
## 删除数据
成功返回影响行数,条件为空时不删除任何数据,确实想删除全表请传 1=1
```
$db->table('log')
    ->where(['id'=>1])
    ->delete();
//使用主键删除
$db->table('log')->delete(1);
```
## 字段操作
对指定字段进行运算更新

* setInc('字段',数字) 加法运算
* setDec('字段',数字) 减法运算
* fieldOperation('字段',数字,'+,-,*,/')
## 汇总数据

* min()
* max()
* avg()
* count()
* sum()
## 事务处理
```
try {
  $db->beginTransaction(); // 开启一个事务
  $row = null;
  $row = $db->query("xxx"); // 执行第一个 SQL
  if (!$row)
    throw new PDOException('提示信息或执行动作'); // 如出现异常提示信息或执行动作
  $row = $db->table("xxx")->add(); // 执行第二个 SQL
  if (!$row)
    throw new PDOException('提示信息或执行动作');
  //这里的代码也可以随时调用 $pdo->rollback();回滚操作
  $db->commit();
} catch (PDOException $e) {
  $db->rollback(); // 执行失败，事务回滚
  exit($e->getMessage());
}
```
## 调试
* fetchSql(bool) 默认为true,返回值 为当前执行的sql语句
* debug(bool)默认为true，会直接中断
* getLastSql() 取最后一次执行的sql语句
* getLastError() 取最后一次执行的报错
* log() 取所有执行的日志(sql语句)
* info()取服务器信息
## SQLite示例
```
//创建一个表
$db->exec('
CREATE TABLE "kl_content" (
"id"  INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
"username"  INTEGER NOT NULL DEFAULT \'\',
"pwd"  INTEGER NOT NULL DEFAULT \'\',
"create_time"  INTEGER NOT NULL DEFAULT 0
);
');
$result = $db->table('content')->debug(false)->add([
  'username'    => 'testusername',
  'pwd'         => 'adminpwd',
  'create_time' => time(),
]);
echo $result;
$list = $db->table('content')->select();
var_dump($list);
$result = $db->table('content')->where(['id' => 1])->update(['username' => 'updateusername']);
echo $result;
$result = $db->table('content')->where(['id' => 2])->delete();
echo $result;
```