<?php


namespace mokuyu\database\tests;


use PHPUnit\Framework\TestCase;
use stdClass;
use PDOException;
use Exception;
use mokuyu\database\Mokuyu;

class SqliteTest extends Base
{
    protected ?Mokuyu $db = null;

    public function setUp(): void
    {
        $this->db = InitTestDb::getSqliteDb();
    }

    /**
     * @param
     */
    public function testAdd()
    {
        //测试非法字段过滤完后,没有可更新的数据
        $this->assertEquals(0, $this->db->table('article')->abort(false)->add([
            'title1' => 'this is php data!' . rand(100, 1000),
            'views1' => rand(100, 1000),
        ]), $this->db->getLastError());
        //添加单个
        $result = $this->db->abort(false)->table('article')->add([
            'title'        => 'this is php data!' . rand(100, 1000),
            //过滤掉数据库中没有的字段,和主键
            'noexistfield' => '0',
            'views'        => rand(100, 1000),
            //测试空值
            'value'        => null,
        ]);
        $this->assertGreaterThan(0, $result, $this->db->getLastError());

        //测试空表时异常
        $result = $this->db->abort(false)->add([
            'title' => 'this is php data!' . rand(100, 1000),
            'views' => rand(100, 1000),
        ]);
        $this->assertEquals(0, $result, $this->db->getLastError());

        //测试数组和对象和bool值
        $result = $this->db->abort(false)->table('article')->add([
            'title' => new stdClass(),
            'views' => false,
            'value' => [1, 2, 3],
        ]);
        $this->assertGreaterThan(0, $result, $this->db->getLastError());

        //测试添加空值
        $result = $this->db->table('article')->abort(false)->add([
            'title' => 'this is php data!' . rand(100, 1000),
            'views' => rand(100, 1000),
            'value' => null,
        ]);
        $this->assertGreaterThan(1, $result, $this->db->getLastError());

    }

    /**
     * @param
     */
    public function testSelect()
    {
        $this->assertIsString($this->db->table('Article')->fetchSql(true)->whereOr([
            'title[!]'        => null,
            'views[!]'        => [1, 2],
            'create_time[><]' => [100, 200],
            'update_time[~]'  => ['%10', '20%', '30'],
            'article_id[~]'   => 1,
        ])->select());
        $this->assertCount(0, $this->db->select());
        $this->assertCount(11, $this->db->table('article')->limit(11)->select());

        //下面会返回41这一行
        $this->assertCount(1, $this->db->table('article')
                                       ->where('article_id', 40)
                                       ->where('article_id', '<>', [40, 90])
                                       ->whereOr('article_id', 41)
                                       ->whereOr('article_id', '>=', 2)
                                       ->fetchSql(false)
                                       ->select());
        $this->assertCount(3, $this->db->table('article')->where('article_id', 'in', [31, 32, 33])->limit('0,3')->select());
        $this->assertCount(1, $this->db->table('article')->where(['article_id' => 91])->select());
        $this->assertCount(1, $this->db->table('article')->where('article_id>1')->where(['article_id' => 91, '_sql' => 'views>0'])->select());
        $this->assertCount(3, $this->db->table('article')->where('article_id', '<>', [45, 47])->limit([0, 3])->select());
        $this->assertCount(3, $this->db->table('article')->where('article_id', 'in', '31,32,33')->limit('0,3')->select());
    }

    /**
     * 测试事务功能
     */
    public function testTransation()
    {
        //测试闭包事务中的事务正常更新
        try {
            $this->db->transaction(function () {
                try {
                    $this->db->beginTransaction();
                    $this->db->table('article')->where('article_id', 91)->update(['views' => '2']);
                    $this->db->commit();
                } catch (Exception $e) {
                    $this->db->rollback();
                }
            });
        } catch (Exception $e) {
            $this->assertTrue(true, $e instanceof PDOException);
        }

        //测试闭包事务中的事务异常
        try {
            $this->db->transaction(function () {
                $this->db->beginTransaction();
                $this->db->table('article')->where('article_id', 91)->update(['views' => '4']);
                //$this->db->commit();
                // throw new Exception('error');
                throw new PDOException('error');
            });
        } catch (Exception $e) {
            $this->assertTrue(true, $e instanceof PDOException);
            $this->assertEquals(2, $this->getViews(91));
        }

        //测试事务中的事务报错
        try {
            $this->db->transaction(function () {
                try {
                    $this->db->beginTransaction();
                    $this->db->table('article')->where('article_id', 91)->update(['test' => '2']);
                    $this->db->commit();
                } catch (Exception $e) {
                    $this->assertTrue(true, $e instanceof PDOException);
                    $this->db->rollback();
                }
            });
        } catch (Exception $e) {
            $this->assertTrue(true, $e instanceof PDOException);
        }

        //测试事务嵌套,数据更新是否达到预期
        $this->assertEquals(1, $this->updateViews(91, 2288));
        $this->assertEquals(1, $this->updateViews(93, 2289));

        $this->db->beginTransaction();
        $this->updateViews(91, 21);
        $this->assertEquals(21, $this->getViews(91));

        //嵌套回滚事务,只回滚此处更改
        try {
            $this->db->beginTransaction();
            $this->updateViews(93, 22);
            $this->updateViews(91, 25);
            $this->assertEquals(22, $this->getViews(93));
            $this->assertEquals(25, $this->getViews(91));
            throw new Exception('test');
            //$this->db->commit();
        } catch (Exception $e) {
            $this->db->rollback();
        }
        //这个记录回滚成2289
        $this->assertEquals(2289, $this->getViews(93));
        //上个回滚不能影响这个记录
        $this->assertEquals(21, $this->getViews(91));


        //嵌套正常提交事务
        try {
            $this->db->beginTransaction();
            $this->updateViews(93, 23);
            $this->db->commit();
        } catch (Exception $e) {
            $this->db->rollback();
        }
        $this->assertEquals(23, $this->getViews(93));
        $this->assertEquals(21, $this->getViews(91));

        //最外层回滚
        $this->db->rollback();
        $this->assertEquals(2288, $this->getViews(91));
        $this->assertEquals(2289, $this->getViews(93));

    }

    /**
     * 测试遍历功能
     */
    public function testChunk()
    {
        $count = 0;
        $this->db->table('article')->chunk(5, function ($datas) use (&$count) {
            $count = array_sum(array_column($datas, 'article_id'));
            return false;
        }, null, 'asc');
        $this->assertEquals(15, $count);

        $count = 0;
        $this->db->table('article')->chunk(5, function ($datas) use (&$count) {
            $count    += count($datas);
            $lastInfo = end($datas);
            if ($lastInfo['article_id'] == 10) {
                return false;
            }
            return 0;
        }, null, 'asc');
        $this->assertEquals(10, $count);

        $lastId = 0;
        $count  = 0;
        $this->db->table('article')->where('article_id', '<', 200)->chunk(50, function ($datas) use (&$lastId, &$count) {
            $lastInfo = end($datas);
            $lastId   = $lastInfo['article_id'];
            $count++;
        }, null, 'asc');
        $this->assertEquals($this->db->where('article_id', '<', 200)->table('article')->max('article_id'), $lastId);
        $this->assertEquals(4, $count);

        $lastId = 1;
        $this->db->table('article')->chunk(20, function ($datas) use (&$lastId) {
            $lastInfo = end($datas);
            $lastId   = $lastInfo['article_id'];
        }, null, 'desc');
        $this->assertEquals(1, $lastId);


    }

    public function testServerInfo()
    {
        $this->assertIsArray($this->db->info());
    }

    /**
     * @param
     */
    public function testSummary()
    {
        $this->assertEquals(199, $this->db
            ->table('Article')
            ->where('article_id', '<>', [198, 200])
            ->avg('article_id'));

        $this->assertEquals(3, $this->db
            ->table('Article')
            ->where('article_id', '<>', [198, 200])
            ->count());

        $this->assertEquals(1, $this->db
            ->table('Article')
            ->min(['article_id']));

        $this->assertGreaterThan(10, $this->db
            ->field(['distinct(article.views)'])
            ->table('Article')
            ->count());

        $this->assertIsArray($this->db
            ->group('views')
            ->table('Article')
            ->count('views,title'));

        $this->assertEquals(60, $this->db
            ->table('Article')
            ->where('article_id', '<=', 60)
            ->max('article_id'));

        $this->assertEquals(399, $this->db
            ->table('Article')
            ->where('article_id', '<>', [199, 200])
            ->sum('article_id'));

        $this->db
            ->table('Article')
            ->order('article_id desc')
            ->rand()
            ->group('views')
            ->get();


    }

    /**
     * 测试字段运算符
     */
    public function testSummaryYunsuan()
    {
        $this->updateViews(30, 30);
        $this->updateViews(40, 40);
        $this->assertEquals(140, $this->db
            ->table('Article')
            ->where('article_id', 'in', [30, 40])
            ->sum('views+article_id'));

        $this->assertEquals(80, $this->db
            ->table('Article')
            ->where('article_id', 'in', [30, 40])
            ->max('views+article_id'));

        $this->assertEquals(60, $this->db
            ->table('Article')
            ->where('article_id', 'in', [30, 40])
            ->min('views+article_id'));

        $this->assertEquals(1600, $this->db
            ->table('Article')
            ->where('article_id', 'in', [30, 40])
            ->max('views*article_id'));
    }

    public function testGet()
    {
        $this->assertFalse($this->db->get());
        $this->assertGreaterThan(0, $this->db->table('article')->field('article.views as nums')->get());
        $this->assertCount(11, $this->db->table('article')->limit(11)->select());
        $this->assertCount(2, $this->db->table('article')->field('title as article_title,views[nums]')->get());
        $this->assertArrayHasKey('nums', $this->db->table('article')->field('article.title,views as nums')->get());
        $this->assertGreaterThan(0, $this->db->table('article')->field('article.views')->get(155));
        $this->assertIsString($this->db->table('article')->fetchSql(true)->field('article.views')->get());
    }

    public function testHas()
    {
        $this->assertFalse($this->db->fetchSql(false)->where('article_id', '>=', 200)->has());
        $this->assertIsString($this->db->table('Article')->fetchSql(true)->where('article_id', '>=', 200)->has());
        $this->assertTrue($this->db->table('article')->fetchSql(false)->where('article_id', '>=', 200)->has());
    }

    /**
     * 测试tp的查询语法
     */
    public function testOperatorTp()
    {
        $this->assertCount(2, $this->db
            ->table('article')
            ->where(['article_id' => ['in', [1, 2]]])
            ->select());
        $this->assertCount(3, $this
            ->db
            ->table('article')
            ->where(['article_id' => ['in', '1,2,3']])
            ->select());
        $this->assertCount(3, $this
            ->db
            ->table('article')
            ->where(['article_id' => ['elt', 3]])
            ->select());
    }

    public function testUpdateTypeValue()
    {
        //测试批量事务更新
        $this->assertEquals(1, $this->db
            ->table('article')
            ->update([
                [
                    'article_id'    => 2,
                    'article.views' => rand(10, 99) + 9999,
                    'value'         => null,
                ],
                [
                    'article_id'    => 3,
                    'article.views' => rand(10, 99) + 9999,
                    'value'         => [1, 2, 3],
                ],
                [
                    'article_id'    => 4,
                    'article.views' => rand(10, 99) + 9999,
                    'value'         => true,
                ],
            ]));
    }

    public function testUpdateOperator()
    {
        $this->updateViews(1, 200);
        //测试字段加减
        $this->assertGreaterThan(0, $this->db
            ->table('article')
            ->save(['article_id' => 1, 'views[+]' => 100]));
        $this->assertEquals(300, $this->getViews(1));

        $this->assertGreaterThan(0, $this->db
            ->table('article')
            ->save(['article_id' => 1, 'views[-]' => 50]));
        $this->assertEquals(250, $this->getViews(1));
        $this->assertGreaterThan(0, $this->db
            ->table('article')
            ->save(['article_id' => 1, 'views[*]' => 2]));
        $this->assertEquals(500, $this->getViews(1));
        $this->assertGreaterThan(0, $this->db
            ->table('article')
            ->save(['article_id' => 1, 'views[/]' => 5]));
        $this->assertEquals(100, $this->getViews(1));
    }

    /**
     * @param
     */
    public function testDelete()
    {
        $this->assertEquals(0, $this->db->delete());
        $this->assertEquals(0, $this->db->table('Nokey')->delete(1));
        $this->assertEquals(0, $this->db->delete(100));
        $this->assertEquals(0, $this->db->table('article')->delete());
        $this->assertGreaterThan(0, $this->db->table('article')->delete(100));
        $this->assertGreaterThan(0, $this->db->table('article')->where('article_id', 99)->delete());
        $this->assertGreaterThan(0, $this->db->table('article')->where('article_id', 'in', [55, 65])->delete());
        $this->assertGreaterThan(0, $this->db->table('article')->where(['article_id' => 98])->delete());
        $this->assertGreaterThan(0, $this->db->table('article')->where('article_id', '<>', [70, 79])->delete());
        // $this->assertGreaterThan(0, $this->db->table('article')
        //                                      ->where('article_id', '<>', [70, 79])
        //                                      ->join([
        //                                          '[>]category' => ['category_id'],
        //                                      ])
        //                                      ->delete());
    }

    public function testGetTables()
    {
        $tables = $this->db->getTables();
        $this->assertTrue(in_array('kl_article', $tables));
    }
}