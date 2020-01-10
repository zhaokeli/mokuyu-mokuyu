# Mokuyu数据库操作
<!-- TOC -->

- [Mokuyu数据库操作](#mokuyu%e6%95%b0%e6%8d%ae%e5%ba%93%e6%93%8d%e4%bd%9c)
  - [安装方法](#%e5%ae%89%e8%a3%85%e6%96%b9%e6%b3%95)
    - [composer](#composer)
    - [手动安装](#%e6%89%8b%e5%8a%a8%e5%ae%89%e8%a3%85)
  - [使用规则说明](#%e4%bd%bf%e7%94%a8%e8%a7%84%e5%88%99%e8%af%b4%e6%98%8e)
    - [数据库表/字段](#%e6%95%b0%e6%8d%ae%e5%ba%93%e8%a1%a8%e5%ad%97%e6%ae%b5)
    - [特别注意/解析规则](#%e7%89%b9%e5%88%ab%e6%b3%a8%e6%84%8f%e8%a7%a3%e6%9e%90%e8%a7%84%e5%88%99)
    - [功能亮点和要求](#%e5%8a%9f%e8%83%bd%e4%ba%ae%e7%82%b9%e5%92%8c%e8%a6%81%e6%b1%82)
  - [连接数据库](#%e8%bf%9e%e6%8e%a5%e6%95%b0%e6%8d%ae%e5%ba%93)
    - [连接mysql](#%e8%bf%9e%e6%8e%a5mysql)
    - [连接pgsql](#%e8%bf%9e%e6%8e%a5pgsql)
    - [连接sqlite](#%e8%bf%9e%e6%8e%a5sqlite)
  - [查询条件连贯操作](#%e6%9f%a5%e8%af%a2%e6%9d%a1%e4%bb%b6%e8%bf%9e%e8%b4%af%e6%93%8d%e4%bd%9c)
    - [fieldMap(array map)](#fieldmaparray-map)
    - [fieldMode(int type=0)](#fieldmodeint-type0)
    - [tableMode(int type=0)](#tablemodeint-type0)
    - [forceIndex(string field)](#forceindexstring-field)
    - [useWriteConn()](#usewriteconn)
    - [field(string/array fields)](#fieldstringarray-fields)
    - [where(string/array)](#wherestringarray)
    - [whereOr(string/array)](#whereorstringarray)
    - [limit(start,end=null)](#limitstartendnull)
    - [order(string/array)](#orderstringarray)
    - [rand()](#rand)
    - [group(string data)](#groupstring-data)
    - [page(int page=1,int pageSize=15)](#pageint-page1int-pagesize15)
    - [join(array data)](#joinarray-data)
  - [执行查询并返回结果](#%e6%89%a7%e8%a1%8c%e6%9f%a5%e8%af%a2%e5%b9%b6%e8%bf%94%e5%9b%9e%e7%bb%93%e6%9e%9c)
    - [select():array](#selectarray)
    - [insert(array datas):int](#insertarray-datasint)
    - [update(array datas):int](#updatearray-datasint)
    - [delete(int id=0):int](#deleteint-id0int)
    - [save(array datas):int](#savearray-datasint)
    - [get([int id = 0]):array/string](#getint-id--0arraystring)
    - [has():boolean](#hasboolean)
    - [paginate(int page=1,int pageSize=15):array](#paginateint-page1int-pagesize15array)
    - [min(...string field):int/array](#minstring-fieldintarray)
    - [max(...string field):int/array](#maxstring-fieldintarray)
    - [avg(...string field):int/array](#avgstring-fieldintarray)
    - [sum(...string field):int/array](#sumstring-fieldintarray)
    - [count(string field):int](#countstring-fieldint)
  - [其它信息获取](#%e5%85%b6%e5%ae%83%e4%bf%a1%e6%81%af%e8%8e%b7%e5%8f%96)
    - [getPK():string](#getpkstring)
    - [getPDO(bool isWrite = false): PDO](#getpdobool-iswrite--false-pdo)
    - [getQueryParams():array](#getqueryparamsarray)
    - [getWhere(array data = []):array](#getwherearray-data--array)
    - [getFields():array](#getfieldsarray)
  - [执行原生SQL](#%e6%89%a7%e8%a1%8c%e5%8e%9f%e7%94%9fsql)
    - [query(string sql,array params=[]):array](#querystring-sqlarray-paramsarray)
    - [exec(string sql,array params=[]):int](#execstring-sqlarray-paramsint)
  - [数据的增删改](#%e6%95%b0%e6%8d%ae%e7%9a%84%e5%a2%9e%e5%88%a0%e6%94%b9)
    - [添加数据](#%e6%b7%bb%e5%8a%a0%e6%95%b0%e6%8d%ae)
    - [更新数据](#%e6%9b%b4%e6%96%b0%e6%95%b0%e6%8d%ae)
    - [删除数据](#%e5%88%a0%e9%99%a4%e6%95%b0%e6%8d%ae)
  - [字段操作](#%e5%ad%97%e6%ae%b5%e6%93%8d%e4%bd%9c)
    - [setInc(string fiela,int num=1):int](#setincstring-fielaint-num1int)
    - [setDec(string field,int num=1):int](#setdecstring-fieldint-num1int)
    - [fieldOperation(string field,int num=0,string operation='+'):int](#fieldoperationstring-fieldint-num0string-operationint)
  - [事务处理](#%e4%ba%8b%e5%8a%a1%e5%a4%84%e7%90%86)
    - [beginTransaction()](#begintransaction)
    - [transaction(Closure callback)](#transactionclosure-callback)
  - [调试](#%e8%b0%83%e8%af%95)
    - [fetchSql(bool bo=true)](#fetchsqlbool-botrue)
    - [debug(bool isdebug=true)](#debugbool-isdebugtrue)
    - [getLastSql():string](#getlastsqlstring)
    - [getLastError():string](#getlasterrorstring)
    - [log():array](#logarray)
    - [info():array](#infoarray)
  - [SQLite示例](#sqlite%e7%a4%ba%e4%be%8b)

<!-- /TOC -->
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
   * 数据表风格,把传入的表名(不带表前缀)转为下面格式,再加上表前缀(程序不会处理前缀的大小写)
   * 0:原样不动，1:转换为下划线风格，2:转换为驼峰风格, 3:转换为下划线然后转为大写(Oracle)
   * @var null
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

### fieldMap(array map)

字段映射
设置查询字段和数据库真实字段的映射

``` php
[
  //格式为 别名(查询)字段=>数据库真实字段
  // 'push_time' => 'create_time',
]
```

### fieldMode(int type=0)

* 字段风格,把传入的字段转为下面类型，此方法为单次查询有效
* 0:原样不动，1:转换为下划线风格，2:转换为驼峰风格

### tableMode(int type=0)

* 数据表风格,把传入的表名(不带表前缀)转为下面格式,再加上表前缀,此方法为单次查询有效
* 0:原样不动，1:转换为下划线风格，2:转换为驼峰风格, 3:转换为下划线然后转为大写(Oracle)

### forceIndex(string field)

强制使用索引,目前Mysql sqlite oracle中可用，其它数据库没有测试,目前只支持主表，join的表不支持，如需要使用请使用原生sql查询

### useWriteConn()

强制使用写库连接来进行操作,在一些强一至性的读写分离项目中可以使用。

### field(string/array fields)

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

### where(string/array)

如果是字符串的话请使用原生sql,包括数据表请用带前缀的表名，数组时其中的and和or键可以任意嵌套，顶层默认为and连接

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

### whereOr(string/array)

where or连接的另一种添加方式

### limit(start,end=null)

参数为(1,10)或[10](0,10)

### order(string/array)

参数为字符串(user_id desc,username desc)也可以为数组
如果多表联合查询的时候有相同字段那么可以直接使用表名,如wechat.update_time最终解析为kl_wechat.update

### rand()

没有参数
如果这个函数被调用则会覆盖上面的排序,会使用随机排序

### group(string data)

参数为字符串(username)

### page(int page=1,int pageSize=15)

返回指定页码和分页大小的记录数

### join(array data)

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

### select():array

从数据库取回指定数据返回一个多维数据

### insert(array datas):int

别名 **add()** ,添加数据

### update(array datas):int

更新数据

### delete(int id=0):int

删除数据，必须有条件的情况下才能删除，没有条件的情况下请传入 1=1

### save(array datas):int

自动判断添加还是更新，首先判断如果有where条件，则会执行更新操作，没有where条件如果数据里有主键则执行更新操作,否则将插入数据，

### get([int id = 0]):array/string

从数据库中取回一条数据返回一维数组，如果只有一个字段就返回这个字段的值

### has():boolean

从数据库中查询数据存在不。返回true false

### paginate(int page=1,int pageSize=15):array

数据分页,第一个参数为当前页码，第二个为分页大小，返回结果为如下格式

``` php
return [
    'list'     => $query->fetchAll(PDO::FETCH_ASSOC),
    'count'    => $count,
    'page'     => $page,
    'pageSize' => $pageSize,
];
```

### min(...string field):int/array

### max(...string field):int/array

### avg(...string field):int/array

### sum(...string field):int/array

### count(string field):int

## 其它信息获取

### getPK():string

返回表中的主键,调用前一定要先调用table设置表

### getPDO(bool isWrite = false): PDO

返回pdo对象

### getQueryParams():array

返回组装sql请求的参数数组

### getWhere(array data = []):array

返回生成的where条件，返回结果为

### getFields():array

返回指定表中的字段

``` php
 [$this->queryParams['where'], $this->bindParam];
```

## 执行原生SQL

### query(string sql,array params=[]):array

查询记录集可以使用query,如果参数不为空则会覆盖where条件中绑定的参数

``` php
//执行sql后，会自动返回所有数据
$sql='select * from kl_event_log';
$db->query($sql);
```

### exec(string sql,array params=[]):int

如果是一些更新插入操作使用exec，如果参数不为空则会覆盖where条件中绑定的参数

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

### setInc(string fiela,int num=1):int

加法运算

### setDec(string field,int num=1):int

减法运算

### fieldOperation(string field,int num=0,string operation='+'):int

[字段],[数字],['+,-,*,/']

## 事务处理

### beginTransaction()

开启事务，这种方法需要手动提交和回滚

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

### transaction(Closure callback)

使用一个回调函数来执行事务,如果抛出异常则自动回滚操作，否则自动提交修改

``` php
$db->transaction(function()use($data){
  //执行一些操作
  //.......

  //出现错误跑出异常回滚
  throw new PDOException('add data error ', 1);
});
```

## 调试

### fetchSql(bool bo=true)

默认为true,结果集为当前执行的sql语句

### debug(bool isdebug=true)

默认为true，查询结果会直接中断，并输出sql语句

### getLastSql():string

取最后一次执行的sql语句

### getLastError():string

取最后一次执行的报错

### log():array

取所有执行的日志(sql语句)

### info():array

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
