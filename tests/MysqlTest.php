<?php

use PHPUnit\Framework\TestCase;
use mokuyu\database\Mokuyu;

class MysqlTest extends TestCase
{
    protected $db = null;

    public function setUp(): void
    {
        $this->db = new Mokuyu([
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
    }

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
     * @param
     */
    public function testAdd()
    {
        //添加单个
        $result = $this->db->abort(false)->table('article')->add([
            'title' => 'this is php data!' . rand(100, 1000),
            'views' => rand(100, 1000),
        ]);
        $this->assertGreaterThan(0, $result);
        //批量添加数据
        $datanum = 200;
        $datas   = [];
        while (--$datanum > 0) {
            $datas[] = [
                'title' => 'this is php data!' . rand(100, 1000),
                'views' => rand(100, 1000),
            ];
        }
        $result = $this->db->abort(false)->table('article')->add($datas);
        $this->assertGreaterThan(0, $result);
    }

    /**
     * @param
     */
    public function testUpdate()
    {
        //更新单条数据
        $this->assertGreaterThan(0, $this->db->table('article')->update(['article_id' => 1, 'views' => 999999]));
        $this->assertGreaterThan(0, $this->db->table('article')->save(['article_id' => 1, 'views' => 99999]));
        //测试批量事务更新
        $this->assertGreaterThan(0, $this->db->table('article')->update([
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
     * @param
     */
    public function testDelete()
    {
        $this->assertGreaterThan(0, $this->db->table('article')->delete(100));
        $this->assertGreaterThan(0, $this->db->table('article')->where('article_id', 99)->delete());
        $this->assertGreaterThan(0, $this->db->table('article')->where('article_id', 'in', [55, 65])->delete());
        $this->assertGreaterThan(0, $this->db->table('article')->where(['article_id' => 98])->delete());
        $this->assertGreaterThan(0, $this->db->table('article')->where('article_id', '<>', [70, 79])->delete());
    }

    /**
     * @param
     */
    public function testSelect()
    {
        $this->assertEquals(11, count($this->db->table('article')->limit(11)->select()));
        $this->assertEquals(1, count($this->db->table('article')->where('article_id', 40)->select()));
        $this->assertEquals(3, count($this->db->table('article')->where('article_id', 'in', [31, 32, 33])->select()));
        $this->assertEquals(1, count($this->db->table('article')->where(['article_id' => 91])->select()));
        $this->assertEquals(3, count($this->db->table('article')->where('article_id', '<>', [45, 47])->select()));
    }

    public function testHas()
    {
        $this->assertTrue($this->db->table('article')->fetchSql(false)->where('article_id', 200)->has());
    }

    /**
     * @param
     */
    public function testSummary()
    {
        $this->assertEquals(199, $this->db->table('Article')->where('article_id', '<>', [198, 200])->avg('article_id'));
        $this->assertEquals(3, $this->db->table('Article')->where('article_id', '<>', [198, 200])->count());
        $this->assertEquals(1, $this->db->table('Article')->min('article_id'));
        $this->assertEquals(200, $this->db->table('Article')->max('article_id'));
        $this->assertEquals(399, $this->db->table('Article')->where('article_id', '<>', [199, 200])->sum('article_id'));
        $this->db->table('Article')->order('article_id desc')->rand()->group('views')->get();
    }


    public function testOther()
    {
        $this->db->clearCache();
        $this->assertEquals('article_id', $this->db->table('Article')->getPK());
        $this->assertGreaterThan(2, count($this->db->table('Article')->getFields()));


    }

    public function testTransaction()
    {
        try {
            $this->db->transaction(function () {
                $this->assertEquals(1, $this->db->table('Article')->where('article_id', 200)->setDec('views'));
                $this->assertEquals(1, $this->db->table('Article')->where('article_id', 199)->setInc('views'));
            });
        } catch (Exception $e) {
        }
    }

    public function testColumn()
    {
        $this->db->table('Article')->where('article_id', 199)->column('views,title');
        $this->db->table('Article')->where('article_id', '<>', [199, 200])->column('*');
        $this->db->table('Article')->where('article_id', '<>', [199, 200])->column('*', 'article_id');
        $this->db->table('Article')->where('article_id', '<>', [199, 200])->column('article_id');
        $this->db->table('Article')->where('article_id', '<>', [199, 200])->column('views', 'article_id');
        $this->db->table('Article')->where('article_id', '<>', [199, 200])->column('*', 'article_id', true);
        $this->assertTrue(true);
    }

    public function testPage()
    {
        $this->db->table('Article')->page(2);
        $this->db->table('Article')->paginate(3);
        $this->db->table('Article')->field('views,title')->paginate(3);
        $this->assertTrue(true);
    }
}