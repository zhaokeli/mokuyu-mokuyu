<?php

namespace mokuyu\tests;

use PHPUnit\Framework\TestCase;
use mokuyu\tests\model\Article;

class ModelTest extends TestCase
{
    protected $db = null;

    public function setUp(): void
    {
        $this->db = InitTestDb::getDb();
    }

    public function testAppend()
    {
        $mod  = new Article($this->db);
        $data = $mod->get(1);
        $this->assertTrue(isset($data['view_text']));
    }
}