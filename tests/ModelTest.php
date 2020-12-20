<?php

namespace mokuyu\database\tests;

use PHPUnit\Framework\TestCase;
use mokuyu\database\tests\model\Article;

class ModelTest extends TestCase
{

    private array                      $config = [];
    protected ?\mokuyu\database\Mokuyu $db     = null;

    public function setUp(): void
    {
        $this->config = require __DIR__ . '/config.php';
        $this->db     = InitTestDb::getMysqlDb();
    }

    public function testAppend()
    {
        $mod  = new Article($this->config['mysql']);
        $data = $mod->get(1);
        $this->assertTrue(isset($data['view_text']));
    }

    public function testUpdate()
    {
        $mod    = new Article($this->config['mysql']);
        $data   = $mod->where('article_id', 10)->update(['views' => 10]);
        $result = $mod->where('article_id', 10)->field('views')->get();
        $this->assertEquals(10 * 1000, $result);
    }

    /**
     * @depends testUpdate
     */
    public function testFieldMap()
    {
        $mod   = new Article($this->config['mysql']);
        $views = $mod->fieldMap([
            'id' => 'article_id',
        ])->where('id', 10)->field('views')->get();
        $this->assertEquals(10000, $views);
        $views = $mod->fieldMap([
            'id' => 'article_id',
        ])->where('id', 10)->field('views')->get();
        $this->assertEquals(10000, $views);
        //两次查询后测试创建了几个链接
        $this->assertCount(1, $mod->getConnections());
    }
}