<?php namespace King\Orm\ListProvider;

use King\Orm\MapperAwareTrait;

class JoinListProvider extends AbstractListProvider
{
    use MapperAwareTrait;

    /**
     * @var IListProvider
     */
    protected $parent;

    /**
     * @var JoinTarget[]
     */
    protected $targets = [];
    /**
     * @var string
     */
    protected $parentName;

    public function __construct(IListProvider $parent, $parentName)
    {

        $this->parent = $parent;
        $this->parentName = $parentName;
    }

    /**
     * @param int $start
     * @return array
     */
    public function fetch($start = 0)
    {
        $parentData = $this->parent->fetch($start);

        foreach($this->targets as $name => $target) {
            $target->acceptParentData($parentData);
        }

        $joinedData = array_map(function ($row) {
            $resultRow = [
                    $this->parentName => $row,
                ] + array_map(function (JoinTarget $target) use ($row) {
                    return $target->getJoinedRow($row);
                }, $this->targets);

            return $resultRow;
        }, $parentData);

        return $this->applyMapper($joinedData);
    }


    /**
     * @param JoinTarget $target
     * @return $this
     */
    public function join(JoinTarget $target)
    {
        $name = $target->getAlias();
        if (isset($this->targets[$name])) {
            throw new \InvalidArgumentException('target exist');
        }

        $this->targets[$name] = $target;

        return $this;
    }

    /**
     *
     * @return array {count, maxPage, perPage}
     */
    public function getPager()
    {
        return $this->parent->getPager();
    }
}
