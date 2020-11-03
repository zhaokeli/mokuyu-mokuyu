<?php

namespace mokuyu\database\tests;

use PHPUnit\Framework\TestCase;
use PDO;
use Exception;
use PDOException;

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
        $this->assertCount(3, $this->db->table('article')->where('article_id', '<>', [45, 47])->limit([0, 3])->select());
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
        $this->db->table('article')->cache(600)->where(['article_id' => 91])->select();
        $this->db->table('article')->cache(600)->where(['article_id' => 92])->get();
        $this->db->table('article')->cache('testarticle', 600)->where(['article_id' => 92])->get();
        $this->assertEquals(3, $this->db->getCacheHits());
    }

    public function testTransation()
    {
        try {
            $this->db->transaction(function () {
                $this->db->table('article')->where('article_id', 91)->update(['test' => '2']);
            });
        } catch (Exception $e) {
            $this->assertTrue(true, $e instanceof PDOException);
        }

    }

}