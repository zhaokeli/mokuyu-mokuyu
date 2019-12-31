# Mokuyu数据库操作
<!-- MarkdownTOC -->

- [安装方法](#%E5%AE%89%E8%A3%85%E6%96%B9%E6%B3%95)
  - [composer](#composer)
  - [手动安装](#%E6%89%8B%E5%8A%A8%E5%AE%89%E8%A3%85)
- [使用规则说明](#%E4%BD%BF%E7%94%A8%E8%A7%84%E5%88%99%E8%AF%B4%E6%98%8E)
  - [数据库表/字段](#%E6%95%B0%E6%8D%AE%E5%BA%93%E8%A1%A8%E5%AD%97%E6%AE%B5)
  - [特别注意/解析规则](#%E7%89%B9%E5%88%AB%E6%B3%A8%E6%84%8F%E8%A7%A3%E6%9E%90%E8%A7%84%E5%88%99)
  - [功能亮点和要求](#%E5%8A%9F%E8%83%BD%E4%BA%AE%E7%82%B9%E5%92%8C%E8%A6%81%E6%B1%82)
- [连接数据库](#%E8%BF%9E%E6%8E%A5%E6%95%B0%E6%8D%AE%E5%BA%93)
  - [连接mysql](#%E8%BF%9E%E6%8E%A5mysql)
  - [连接pgsql](#%E8%BF%9E%E6%8E%A5pgsql)
  - [连接sqlite](#%E8%BF%9E%E6%8E%A5sqlite)
- [查询条件连贯操作](#%E6%9F%A5%E8%AF%A2%E6%9D%A1%E4%BB%B6%E8%BF%9E%E8%B4%AF%E6%93%8D%E4%BD%9C)
  - [fieldMap\(\)](#fieldmap)
  - [fieldMode\(\)](#fieldmode)
  - [tableMode\(\)](#tablemode)
  - [forceIndex\(\)](#forceindex)
  - [field\(\)](#field)
  - [where\(\)](#where)
  - [limit\(\)](#limit)
  - [order\(\)](#order)
  - [rand\(\)](#rand)
  - [group\(\)](#group)
  - [page\(page,pageSize\)](#pagepagepagesize)
  - [join\(\)](#join)
- [执行查询并返回结果](#%E6%89%A7%E8%A1%8C%E6%9F%A5%E8%AF%A2%E5%B9%B6%E8%BF%94%E5%9B%9E%E7%BB%93%E6%9E%9C)
  - [select\(\)](#select)
  - [get\(\)](#get)
  - [has\(\)](#has)
  - [paginate\(page,pageSize\)](#paginatepagepagesize)
  - [min\(\)](#min)
  - [max\(\)](#max)
  - [avg\(\)](#avg)
  - [count\(\)](#count)
  - [sum\(\)](#sum)
- [其它信息获取](#%E5%85%B6%E5%AE%83%E4%BF%A1%E6%81%AF%E8%8E%B7%E5%8F%96)
  - [getPK\(\)](#getpk)
  - [getPDO\(bool isWrite = false\): PDO](#getpdobool-iswrite--false-pdo)
  - [getQueryParams](#getqueryparams)
  - [getWhere\(array data = \[\]\)](#getwherearray-data--)
  - [getFields\(\)](#getfields)
- [执行原生SQL](#%E6%89%A7%E8%A1%8C%E5%8E%9F%E7%94%9Fsql)
- [数据的增删改](#%E6%95%B0%E6%8D%AE%E7%9A%84%E5%A2%9E%E5%88%A0%E6%94%B9)
  - [添加数据](#%E6%B7%BB%E5%8A%A0%E6%95%B0%E6%8D%AE)
  - [更新数据](#%E6%9B%B4%E6%96%B0%E6%95%B0%E6%8D%AE)
  - [删除数据](#%E5%88%A0%E9%99%A4%E6%95%B0%E6%8D%AE)
- [字段操作](#%E5%AD%97%E6%AE%B5%E6%93%8D%E4%BD%9C)
  - [setInc\(fiela,num\)](#setincfielanum)
  - [setDec\(field,num\)](#setdecfieldnum)
  - [fieldOperation\(par,par,par\)](#fieldoperationparparpar)
- [事务处理](#%E4%BA%8B%E5%8A%A1%E5%A4%84%E7%90%86)
- [调试](#%E8%B0%83%E8%AF%95)
  - [fetchSql\(bool\)](#fetchsqlbool)
  - [debug\(bool\)](#debugbool)
  - [getLastSql\(\)](#getlastsql)
  - [getLastError\(\)](#getlasterror)
  - [log\(\)](#log)
  - [info\(\)](#info)
- [SQLite示例](#sqlite%E7%A4%BA%E4%BE%8B)

<!-- /MarkdownTOC -->
## 安装方法
### composer
``` bash
# install mokuyu
composer require mokuyu/mokuyu
```
### 手动安装
本库可以缓存字段等信息，使用psr标准缓存接口 Psr\SimpleCache\CacheInterface; 请自己引入缓存标准接口文件
[Psr\SimpleCache\CacheInterface 下载地址](https://github.com/php-fig/simple-cache/tree/master/src)
``` php
include __dir__.'/CacheInterface.php';
```

本数据库类的开发吸取medoo(符号查询)和thinkphp(连贯操作)的特点，集各家之所长,感谢以上两个开源库
[toc]

## 使用规则说明
### 数据库表/字段
* 表和字段名字全部小写,为方便阅读，尽量使用全名不要使用字母简写(名字长点其它无所谓)
* 字段名单词间用下划线分隔，或使用驼峰命名,不能混写
* 每个表都要有主键，主键名字格式为: 表名(不带前缀)_id,

>如果字段没有按下面的规范写,可以使用字段映射来一一对应，以上规范为强烈建议，非强制使用
### 特别注意/解析规则
* 数据表解析时会把非首位的大写字母解析成下划线加小写字母，如userGroup/UserGroup 都会被解析为 db_user_group
* 字段风格默认为不转换,0:默认字段，1:转换为下划线风格，2:转换为驼峰风格,查询时会按这些规则转换成真实的数据库字段进行查询


### 功能亮点和要求
* 进行添加或更新操作时可以自动过滤数据库不存在的字段
* 查询时可进行字段映射,前端字段映射到数据库真实字段
* 如果要加快查询速度，则可以设置缓存对象,数据库会把数据表字段等信息保存下来，加快查询速度，设置的缓存对象要实现 DbCache 接口

## 连接数据库
### 连接mysql
``` php
$db = new \mokuyu\database\Mokuyu([
  // 必须配置项
  'database_type' => 'mysql',
  'database_name' => '******',
  //服务器 :w写数据 :r读数据，如果不设置标识则视为读写
  'server'        => 'localhost,192.168.0.2:r',
  'username'      => '*****,****',//如果长度和server不够以最后一个为准
  'password'      => '******,****',//如果长度和server不够以最后一个为准
  'port'          => '3306,3306',//如果长度和server不够以最后一个为准
  'charset'       => 'utf8',
  /**
   *  可选参数
   * 字段风格,把传入的字段转为下面,也可以在查询时使用fieldMode()设置临时风格
   * 0:默认字段，1:转换为下划线风格，2:转换为驼峰风格
   * @var null
   **/
  'field_mode'  =>0,
  /**
   + 数据表风格,把传入的表名转为下面
   + 0:原样不动，1:转换为下划线风格，2:转换为驼峰风格
   + @var null
   */
  'table_mode'  =>1,
  // 可选参数，定义表的前缀
  'prefix'        => 'kl_',
]);
```
### 连接pgsql
``` php
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
``` php
$db = new \mokuyu\database\Mokuyu([
  // 必须配置项
  'database_type' => 'sqlite',
  'database_file' => 'stat.db',
  'charset'       => 'utf8',
  // 可选，定义表的前缀
  'prefix'        => 'kl_',
]);
```

## 查询条件连贯操作
>
``` php
$db->table('user')
    ->field('user_id,username')
    ->where(['user_id'=>1])
    ->limit(1,10)
    ->order('user_id desc')
    ->select()
```

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
### forceIndex()
强制使用索引,目前Mysql sqlite oracle中可用，其它数据库没有测试,目前只支持主表，join的表不支持，如需要使用请使用原生sql查询
### field()
字段可以直接使用原生格式，不用加反引号,会自动识别并添加上

``` php
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
``` php
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
``` php
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
### page(page,pageSize)
返回指定页码和分页大小的记录数
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
## 执行查询并返回结果

### select()
从数据库取回指定数据返回一个多维数据

### get()
从数据库中取回一条数据返回一维数组，如果只有一个字段就返回这个字段的值

### has()
从数据库中查询数据存在不。返回true false

### paginate(page,pageSize)
数据分页,第一个参数为当前页码，第二个为分页大小，返回结果为如下格式
``` php
return [
    'list'     => $query->fetchAll(PDO::FETCH_ASSOC),
    'count'    => $count,
    'page'     => $page,
    'pageSize' => $pageSize,
];
```
### min(...field)
### max(...field)
### avg(...field)
### count(field)
### sum(...field)

## 其它信息获取

### getPK()
返回表中的主键

### getPDO(bool isWrite = false): PDO
返回pdo对象

### getQueryParams
返回组装sql请求的参数数组

### getWhere(array data = [])
返回生成的where条件，返回结果为

### getFields()
返回指定表中的字段
``` php
 [$this->queryParams['where'], $this->bindParam];
```

## 执行原生SQL
有返回值的使用query,
``` php
//执行sql后，会自动返回所有数据
$sql='select * from kl_event_log';
$db->query($sql);
```
如果是一些更新插入操作,没有返回值使用exec
``` php
$db->exec("CREATE TABLE table (
  c1 INT STORAGE DISK,
  c2 INT STORAGE MEMORY
) ENGINE NDB;");
// 注意:有些语句是没有影响行数的,如上所示,这个时候会自动判断有没有错误,如果有错误会返回false,
// 可以使用 getLastError() 获取最后一次执行后的错误提示

```
## 数据的增删改
### 添加数据
成功返回自增id值,添加时要使用sql中的函数可在字段前面加个‘#’就可以使用,批量添加可以用多维数组，会自动使用事务进行操作，(表引擎要使用InnoDB),批量添加返回值不会返回自增id这点要注意
``` php
$db->table('log')->add([
'content'=>'neirong',
'#addtime'=>'NOW()'
  ]);
```

### 更新数据
成功返回影响行数
``` php
$db->table('log')
    ->where(['id'=>1])
    ->update($data);
```
条件为空时不更新任何数据,确实是想更新全表请传 1=1
另外如果想在原有字段上执行算术运算可以用下面方法
``` php
$db->table('log')
->where(['id'=>1])
->update([
  'views[+]'=>2,
  'views[-]'=>2,
  'views[*]'=>2,
  'views[/]'=>2,
]);
```
### 删除数据
成功返回影响行数,条件为空时不删除任何数据,确实想删除全表请传 1=1
``` php
$db->table('log')
    ->where(['id'=>1])
    ->delete();
//使用主键删除
$db->table('log')->delete(1);
```
## 字段操作
对指定字段进行运算更新

### setInc(fiela,num)
加法运算
### setDec(field,num) 
减法运算
### fieldOperation(par,par,par)
[字段],[数字],['+,-,*,/']

## 事务处理
``` php
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
### fetchSql(bool) 
默认为true,返回值 为当前执行的sql语句
### debug(bool)
默认为true，会直接中断
### getLastSql() 
取最后一次执行的sql语句
### getLastError() 
取最后一次执行的报错
### log() 
取所有执行的日志(sql语句)
### info()
取服务器信息
## SQLite示例
``` php
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