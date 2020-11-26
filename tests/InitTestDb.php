<?php

namespace mokuyu\database\tests;

use PDOException;
use mokuyu\database\Mokuyu;
use PDO;
use mokuyu\Cache;
use mokuyu\CacheException;

class InitTestDb
{
    protected static $mysqlInstance  = null;
    protected static $sqliteInstance = null;
    protected static $pdoInstance    = null;
    protected static $initialized    = false;

    public static function getSqliteDb()
    {
        if (self::$sqliteInstance === null) {
            //初始化后会自动创建数据库文件
            self::$sqliteInstance = new Mokuyu([
                // 必须配置项
                'database_type' => 'sqlite',
                'database_file' => __DIR__ . '/../test.db',
                'charset'       => 'utf8',
                // 可选，定义表的前缀
                'prefix'        => 'kl_',
            ]);
            //创建一个表
            self::$sqliteInstance->exec('
DROP TABLE IF EXISTS "main"."kl_article";
CREATE TABLE "kl_article" (
"article_id"  INTEGER PRIMARY KEY AUTOINCREMENT,
"title"  text,
"views"  INTEGER NOT NULL DEFAULT 0,
"create_time"  INTEGER NOT NULL DEFAULT 0,
"update_time"  INTEGER NOT NULL DEFAULT 0
);
DROP TABLE IF EXISTS "main"."kl_category";
CREATE TABLE "main"."kl_category" (
  "category_id" INTEGER NOT NULL DEFAULT 0,
  "title" TEXT NOT NULL DEFAULT \'\',
  PRIMARY KEY ("category_id")
);
');
            try {
                self::$sqliteInstance->setCache(new Cache([
                    // 缓存类型为File
                    'type'     => 'redis', //目前支持file和memcache redis
                    'memcache' => [
                        'host' => 'localhost',
                        'port' => 11211,
                    ],
                    'redis'    => [
                        'host'     => '127.0.0.1',
                        'port'     => 6379,
                        'index'    => 7,
                        'password' => 'adminrootkl',
                    ],
                    // 全局缓存有效期（0为永久有效）
                    'expire'   => 24 * 3600,
                    // 缓存前缀
                    'prefix'   => 'mokuyu_db',
                    // 缓存目录
                    'path'     => '/datacache',
                ]));
            } catch (CacheException $e) {
            }
            self::initSqliteDatabase();
        }
        return self::$sqliteInstance;
    }

    public static function getMysqlDb()
    {
        if (self::$mysqlInstance === null) {
            self::$mysqlInstance = new Mokuyu([
                // 必须配置项
                'database_type' => 'mysql',
                'database_name' => $_ENV['test_database_name'],
                'server'        => $_ENV['test_server'],
                'username'      => $_ENV['test_username'],
                'password'      => $_ENV['test_password'],
                'charset'       => 'utf8',
                // 可选参数
                'port'          => 3306,
                'debug_mode'    => false,
                'table_mode'    => 1,
                // 可选，定义表的前缀
                'prefix'        => $_ENV['test_prefix'],
            ]);
            try {
                self::$mysqlInstance->setCache(new Cache([
                    // 缓存类型为File
                    'type'     => 'redis', //目前支持file和memcache redis
                    'memcache' => [
                        'host' => 'localhost',
                        'port' => 11211,
                    ],
                    'redis'    => [
                        'host'     => '127.0.0.1',
                        'port'     => 6379,
                        'index'    => 7,
                        'password' => 'adminrootkl',
                    ],
                    // 全局缓存有效期（0为永久有效）
                    'expire'   => 24 * 3600,
                    // 缓存前缀
                    'prefix'   => 'mokuyu_db',
                    // 缓存目录
                    'path'     => '/datacache',
                ]));
            } catch (CacheException $e) {
            }
            self::initMysqlDatabase();
        }
        return self::$mysqlInstance;
    }

    public static function getPdo()
    {
        if (self::$pdoInstance === null) {
            try {
                self::$pdoInstance = new PDO(
                    'mysql:host=' . $_ENV['test_server'] . ';port=' . $_ENV['test_port'] . ';',
                    $_ENV['test_username'],
                    $_ENV['test_password'],
                    [
                        PDO::ATTR_CASE => PDO::CASE_NATURAL,
                    ]
                );
            } catch (PDOException $e) {
                throw $e;
            }
        }
        return self::$pdoInstance;
    }


    /**
     * 初始sqlite数据库
     */
    private static function initSqliteDatabase()
    {
        //批量添加数据
        $datanum = 200;
        $datas   = [];
        while (--$datanum >= 0) {
            $datas[] = [
                'title'       => 'this is php data!' . rand(100, 1000),
                'category_id' => rand(1, 3),
                'views'       => rand(100, 1000),
                'create_time' => time(),
                'update_time' => time(),
            ];
        }
        $result = self::$sqliteInstance->abort(false)->table('article')->add($datas);
    }

    private static function initMysqlDatabase()
    {
        if (self::$initialized) {
            return;
        }
        $pdo = self::getPdo();
        $sql = 'CREATE DATABASE IF NOT EXISTS ' . $_ENV['test_database_name'] . ' DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci;';
        $pdo->exec($sql);
        $datatableSql = <<<eot
DROP TABLE IF EXISTS `kl_article`;
CREATE TABLE `kl_article` (
  `article_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL DEFAULT '',
  `category_id` int(11) NOT NULL DEFAULT '0' COMMENT '分类id',
  `views` int(11) NOT NULL DEFAULT '0' COMMENT '浏览次数',
  `create_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `update_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `value` varchar(255) NULL DEFAULT '' COMMENT '测试可空值' ,
  PRIMARY KEY (`article_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
DROP TABLE IF EXISTS `kl_category`;
CREATE TABLE `kl_category` (
  `category_id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL DEFAULT '' COMMENT '标题',
  PRIMARY KEY (`category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
DROP TABLE IF EXISTS `kl_nokey`;
CREATE TABLE `kl_nokey` (
  `test_title` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
eot;
        $pdo->exec('use ' . $_ENV['test_database_name']);;
        $pdo->exec($datatableSql);
        //批量添加数据
        $datanum = 200;
        $datas   = [];
        while (--$datanum >= 0) {
            $datas[] = [
                'title'       => 'this is php data!' . rand(100, 1000),
                'category_id' => rand(1, 3),
                'views'       => rand(100, 1000),
            ];
        }
        $result            = self::$mysqlInstance->abort(false)->table('article')->add($datas);
        $result            = self::$mysqlInstance->abort(false)->table('Category')->add([
            [
                'title' => '软件下载',
            ],
            [
                'title' => '电影',
            ],
            [
                'title' => '小说',
            ],
        ]);
        self::$initialized = true;
    }
}