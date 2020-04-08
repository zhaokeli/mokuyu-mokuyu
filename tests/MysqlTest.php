<?php

use PHPUnit\Framework\TestCase;
use mokuyu\database\Mokuyu;

class MysqlTest extends TestCase
{
    public function testGetPdo()
    {
        $pdo = null;
        try {
            $pdo = new PDO(
                'mysql:host=' . $_ENV['server'] . ';port=' . $_ENV['port'] . ';',
                $_ENV['username'],
                $_ENV['password'],
                [
                    PDO::ATTR_CASE => PDO::CASE_NATURAL,
                ]
            );
        } catch (PDOException $e) {
            $this->assertTrue($pdo instanceof PDO);
            return null;
        }
        $this->assertTrue($pdo instanceof PDO);
        return $pdo;
    }

    /**
     * @depends testGetPdo
     * @param \PDO $pdo
     */
    public function testCreateDatabase(PDO $pdo)
    {
        $sql = 'CREATE DATABASE IF NOT EXISTS ' . $_ENV['database_name'] . ' DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci;';
        $pdo->exec($sql);
        $datatableSql = <<<eot
DROP TABLE IF EXISTS `kl_article`;
CREATE TABLE `kl_article` (
  `article_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL DEFAULT '',
  `views` int(11) NOT NULL DEFAULT '0' COMMENT '浏览次数',
  `create_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `update_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`article_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
eot;
        $pdo->exec('use ' . $_ENV['database_name']);;
        $this->assertEquals(0, $pdo->exec($datatableSql));
    }

    /**
     * @return Mokuyu
     */
    public function testGetConnect()
    {
        $db = new Mokuyu([
            // 必须配置项
            'database_type' => 'mysql',
            'database_name' => $_ENV['database_name'],
            'server'        => $_ENV['server'],
            'username'      => $_ENV['username'],
            'password'      => $_ENV['password'],
            'charset'       => 'utf8',
            // 可选参数
            'port'          => 3306,
            'debug_mode'    => false,
            'table_mode'    => 1,
            // 可选，定义表的前缀
            'prefix'        => $_ENV['prefix'],
        ]);
        $this->assertTrue($db instanceof Mokuyu);
        return $db;
    }


    /**
     * @depends testGetConnect
     * @param Mokuyu $db
     */
    public function testAdd(Mokuyu $db)
    {
        //添加单个
        $result = $db->abort(false)->table('article')->add([
            'title' => 'this is php data!' . rand(100, 1000),
            'views' => rand(100, 1000),
        ]);
        $this->assertGreaterThan(0, $result);
        //批量添加数据
        $datanum = 100;
        $datas   = [];
        while (--$datanum > 0) {
            $datas[] = [
                'title' => 'this is php data!' . rand(100, 1000),
                'views' => rand(100, 1000),
            ];
        }
        $result = $db->abort(false)->table('article')->add($datas);
        $this->assertGreaterThan(0, $result);
    }

    /**
     * @depends testGetConnect
     * @param Mokuyu $db
     */
    public function testUpdate(Mokuyu $db)
    {
        //更新单条数据
        $this->assertGreaterThan(0, $db->table('article')->update(['article_id' => 1, 'views' => 999999]));
        //测试批量事务更新
        $this->assertGreaterThan(0, $db->table('article')->update([
            [
                'article_id' => 2,
                'views'      => rand(10, 99) + 9999,
            ],
            [
                'article_id' => 3,
                'views'      => rand(10, 99) + 9999,
            ],
            [
                'article_id' => 4,
                'views'      => rand(10, 99) + 9999,
            ],
        ]));
    }

    /**
     * @depends testGetConnect
     * @param Mokuyu $db
     */
    public function testDelete(Mokuyu $db)
    {
        $this->assertGreaterThan(0, $db->table('article')->delete(100));
        $this->assertGreaterThan(0, $db->table('article')->where('article_id', 99)->delete());
        $this->assertGreaterThan(0, $db->table('article')->where('article_id', 'in', [55, 65])->delete());
        $this->assertGreaterThan(0, $db->table('article')->where(['article_id' => 98])->delete());
        $this->assertGreaterThan(0, $db->table('article')->where('article_id', '<>', [70, 79])->delete());
    }

    /**
     * @depends testGetConnect
     * @param Mokuyu $db
     */
    public function testSelect(Mokuyu $db)
    {
        $this->assertEquals(11, count($db->table('article')->limit(11)->select()));
        $this->assertEquals(1, count($db->table('article')->where('article_id', 40)->select()));
        $this->assertEquals(3, count($db->table('article')->where('article_id', 'in', [31, 32, 33])->select()));
        $this->assertEquals(1, count($db->table('article')->where(['article_id' => 91])->select()));
        $this->assertEquals(3, count($db->table('article')->where('article_id', '<>', [45, 47])->select()));
    }
}