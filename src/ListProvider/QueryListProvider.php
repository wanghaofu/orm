<?php namespace King\Orm\ListProvider;

use King\Orm\Base\BaseSchema;
use King\Orm\MapperAwareTrait;
use Silo\Builder\QueryBuilder;

class QueryListProvider extends AbstractListProvider
{
    use MapperAwareTrait;

    protected $queryClass;

    protected $count;
    /**
     * @var array
     */
    protected $queryData;
    /**
     * @var int
     */
    private $perPage;

    /**
     * @param BaseSchema $query
     * @param int $limit
     */
    public function __construct(BaseSchema $query, $limit)
    {
        $this->queryClass = get_class($query);
        $this->queryData = $query->getArrayCopy();
        $this->perPage = max(0, intval(filter_var($limit, FILTER_SANITIZE_NUMBER_INT)));
    }

    protected function countItem()
    {
        if (!isset($this->count)) {
            $row = (new QueryBuilder($this->queryData))
                ->select('COUNT(*)')
                ->runSelectFirst();
            $this->count = intval(reset($row));
        }

        return $this->count;
    }

    /**
     * @param int $start
     * @return array
     */
    public function fetch($start = 0)
    {
        $start = intval(filter_var($start, FILTER_SANITIZE_NUMBER_INT));
        /**
         * @var BaseSchema $q
         */
        if($start < 0) {
            $start = 0;
        }
        
        $q = new $this->queryClass($this->queryData);

        $models = $q
            ->limit($this->perPage, $start)
            ->find();

        return $this->applyMapper(iterator_to_array($models));
    }

    /**
     *
     * @return array {count, maxPage, perPage}
     */
    public function getPager()
    {
        $count = $this->countItem();
        $maxPage = $this->perPage ? ceil($count / $this->perPage) : null;
        return [
            'count' => $count,
            'perPage' => $this->perPage,
            'maxPage' => $maxPage,
        ];
    }
}
