<?php
namespace King\Orm\ListProvider;


interface IListProvider
{
    /**
     * @param int $start
     * @return array
     */
    public function fetch($start = 0);

    /**
     *
     * @return array {count, maxPage, perPage}
     */
    public function getPager();
}

