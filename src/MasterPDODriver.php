<?php namespace King\Orm;

use Silo\Builtin\PDODriver;

class MasterPDODriver extends PDODriver
{
    /**
     * @return array
     */
    public function getExecutedSqls()
    {
        return $this->executedSqls;
    }

    protected function execute($sql, $params)
    {
        $statement = [
            'sql' => $sql,
            'params' => $params,
        ];
        $start = microtime(true);

        $s = parent::execute($sql, $params);

        $this->executedSqls[] = $statement + [
            'time' => microtime(true) - $start,
        ];

        return $s;
    }

    protected $executedSqls = [];
}
