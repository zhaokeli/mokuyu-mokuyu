<?php


namespace mokuyu\database\tests;


use PHPUnit\Framework\TestCase;
use PDOStatement;

class Base extends TestCase
{
    protected $db = null;

    /**
     * 返回文章浏览量
     * @param $articleId
     * @return array|bool|mixed|PDOStatement|string
     */
    protected function getViews($articleId)
    {
        return $this->db->table('article')->where('article_id', $articleId)->field('views')->get();
    }

    /**
     * 更新文件浏览量
     * @param     $articleId
     * @param int $views
     * @return bool|int|string
     */
    protected function updateViews($articleId, $views = 0)
    {
        return $this->db->table('article')->where('article_id', $articleId)->update(['views' => $views]);
    }
}