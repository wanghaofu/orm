<?php namespace King\Orm\ListProvider;

use King\Orm\Base\BaseModel;
use King\Orm\Base\BaseSchema;
use King\Orm\MapperAwareTrait;


class JoinTarget
{
    use MapperAwareTrait;

    protected $joinedData;
    /**
     * @var callable
     */
    protected $keyMapper;
    /**
     * @var string
     */
    protected $alias;
    /**
     * @var string
     */
    protected $queryClass;
    /**
     * @var array
     */
    protected $queryData;
    /**
     * @var string
     */
    private $keyField;
    /**
     * @var bool
     */
    private $multipleMode = false;

    public function __construct(BaseSchema $query, $keyField, $keyMapper)
    {
        if (is_string($keyMapper)) {
            $keyName = $keyMapper;
            $keyMapper = function ($model) use ($keyName) {
                return $model->{$keyName};
            };
        }

        $this->keyMapper = $keyMapper;
        $this->queryClass = get_class($query);
        $this->queryData = $query->getArrayCopy();
        $this->keyField = $keyField;
    }

    /**
     * @return string
     */
    public function getAlias()
    {
        return $this->alias;
    }

    /**
     *
     * @param string $alias
     * @return $this
     */
    public function setAlias($alias)
    {
        $this->alias = $alias;

        return $this;
    }

    public function acceptParentData(array $parentData)
    {
        if (isset($this->joinedData)) {
            throw new \Exception(__METHOD__ . '/' . __LINE__);
        }

        if (count($parentData) === 0) {
            $this->joinedData = [];
            return;
        }

        $keys = array_filter(array_map($this->keyMapper, $parentData), function($k) {
            return $k !== false;
        });

        /**
         * @var BaseSchema $q
         */
        $q = new $this->queryClass($this->queryData);

//        不支持groupBy
//        if (!$this->multipleMode) {
//            $q->groupBy(
//        }

        $result = $q->in($this->keyField, $keys)
            ->find();

        $this->joinedData = iterator_to_array($result);
    }

    public function getJoinedRow($parentRow)
    {
        $key = call_user_func($this->keyMapper, $parentRow);
        $rows = array_filter($this->joinedData, function (BaseModel $model) use ($key) {
            $p = $model->getPropertyName($this->keyField);
            return (string)$key === $model->{$p};
        });
        $rows = $this->applyMapper($rows);

        if ($this->multipleMode) {
            return $rows;
        } else {
            return reset($rows);
        }
    }

    /**
     *
     * @param boolean $multipleMode
     * @return $this
     */
    public function setMultipleMode($multipleMode)
    {
        $this->multipleMode = $multipleMode;

        return $this;
    }
}
