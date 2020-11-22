<?php

namespace mokuyu\database\tests;

use PHPUnit\Framework\TestCase;
use PDO;
use Exception;
use PDOException;
use PDOStatement;

class MysqlTest extends TestCase
{
    protected $db = null;

    public function setUp(): void
    {
        $this->db = InitTestDb::getDb();
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

    }

    /**
     * @param
     */
    public function testUpdate()
    {
        $this->assertEquals(0, $this->db->save([]));
        $this->assertEquals(0, $this->db->update([]));
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
        $this->assertEquals(0, $this->db->delete());
        $this->assertEquals(0, $this->db->table('Nokey')->delete(1));
        $this->assertEquals(0, $this->db->delete(100));
        $this->assertEquals(0, $this->db->table('article')->delete());
        $this->assertGreaterThan(0, $this->db->table('article')->delete(100));
        $this->assertGreaterThan(0, $this->db->table('article')->where('article_id', 99)->delete());
        $this->assertGreaterThan(0, $this->db->table('article')->where('article_id', 'in', [55, 65])->delete());
        $this->assertGreaterThan(0, $this->db->table('article')->where(['article_id' => 98])->delete());
        $this->assertGreaterThan(0, $this->db->table('article')->where('article_id', '<>', [70, 79])->delete());
        $this->assertGreaterThan(0, $this->db->table('article')
                                             ->where('article_id', '<>', [70, 79])
                                             ->join([
                                                 '[>]category' => ['category_id'],
                                             ])
                                             ->delete());
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
        $this->assertEquals(0, $this->db->select());
        $this->assertEquals(11, count($this->db->table('article')->limit(11)->select()));
        //下面会返回41这一行
        $this->assertEquals(1, count($this->db->table('article')
                                              ->where('article_id', 40)
                                              ->where('article_id', '<>', [40, 90])
                                              ->whereOr('article_id', 41)
                                              ->whereOr('article_id', '>=', 2)
                                              ->fetchSql(false)
                                              ->select()));
        $this->assertCount(3, $this->db->table('article')->where('article_id', 'in', [31, 32, 33])->limit('0,3')->select());
        $this->assertCount(1, $this->db->table('article')->where(['article_id' => 91])->select());
        $this->assertCount(1, $this->db->table('article')->where('article_id>1')->where(['article_id' => 91, '_sql' => 'views>0'])->select());
        $this->assertCount(3, $this->db->table('article')->where('article_id', '<>', [45, 47])->limit([0, 3])->select());
        $this->assertCount(3, $this->db->table('article')->where('article_id', 'in', '31,32,33')->limit('0,3')->select());
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
     * @param
     */
    public function testSummary()
    {
        $this->assertEquals(199, $this->db->table('Article')->where('article_id', '<>', [198, 200])->avg('article_id'));
        $this->assertEquals(3, $this->db->table('Article')->where('article_id', '<>', [198, 200])->count());
        $this->assertEquals(1, $this->db->table('Article')->min('article_id'));
        $this->assertEquals(201, $this->db->table('Article')->max('article_id'));
        $this->assertEquals(399, $this->db->table('Article')->where('article_id', '<>', [199, 200])->sum('article_id'));
        $this->db->table('Article')->order('article_id desc')->rand()->group('views')->get();
    }

    public function testDebug()
    {
        $this->db->debug(false);
        $this->assertFalse($this->db->debug());
    }

    public function testOther()
    {
        $this->db->clearCache();
        $this->db->getLastError();
        $this->db->getLastSql();
        $this->assertInstanceOf(PDO::class, $this->db->getPDO());
        $this->db->error();
        $this->db->log();
        $this->db->fieldMode();
        $this->db->tableMode();
        $this->assertEquals('article_id', $this->db->table('Article')->getPK());
        $this->assertGreaterThan(2, count($this->db->table('Article')->getFields()));
        $this->assertCount(2, $this->db->table('article')->field('title as article_title,views[nums]')->getWhere(['id' => 1]));


    }

    public function testFieldOperation()
    {
        $this->db->table('Article')->fieldOperation('views', 1, '&');
        $this->db->fieldOperation('views', 1, '*');
        $this->assertEquals(1, 1);
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
        $this->assertIsString($this->db->table('Article')->fetchSql(true)->paginate(3));
        $this->db->table('Article')->page(2);
        $this->db->table('Article')->paginate(3);
        $this->assertFalse($this->db->paginate(3));
        $this->db->table('Article')->field('views,title')->paginate(3);
        $this->assertTrue(true);
    }

    public function testQueryCache()
    {
        $this->db->table('article')->useWriteConn(true)->cache(600)->where(['article_id' => 91])->select();
        $this->db->table('article')->cache(600)->where(['article_id' => 92])->get();
        $this->db->table('article')->cache('testarticle', 600)->where(['article_id' => 92])->get();
        $this->db->table('article')->cache(600)->where(['article_id' => 92])->get();
        $this->assertEquals(1, $this->db->getCacheHits());
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
     * 返回文章浏览量
     * @param $articleId
     * @return array|bool|mixed|PDOStatement|string
     */
    private function getViews($articleId)
    {
        return $this->db->table('article')->where('article_id', $articleId)->field('views')->get();
    }

    /**
     * 更新文件浏览量
     * @param     $articleId
     * @param int $views
     * @return bool|int|string
     */
    private function updateViews($articleId, $views = 0)
    {
        return $this->db->table('article')->where('article_id', $articleId)->update(['views' => $views]);
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
        }, null, 'asc');
        $this->assertEquals(10, $count);

        $lastId = 0;
        $count  = 0;
        $this->db->table('article')->chunk(50, function ($datas) use (&$lastId, &$count) {
            $lastInfo = end($datas);
            $lastId   = $lastInfo['article_id'];
            $count++;
        }, null, 'asc');
        $this->assertEquals(201, $lastId);
        $this->assertEquals(4, $count);

        $lastId = 1;
        $this->db->table('article')->chunk(20, function ($datas) use (&$lastId) {
            $lastInfo = end($datas);
            $lastId   = $lastInfo['article_id'];
        }, null, 'desc');
        $this->assertEquals(1, $lastId);


    }

}