<?php namespace King\Orm;

use PDO;
use Silo\Builder\AbstractBuilder;
use Silo\Builtin\PDODriver;

class MSlavePDODriver extends PDODriver
{
    /**
     * @var PDO
     */
    private $slavePdo;

    public function __construct(PDO $masterPdo, PDO $slavePdo)
    {
        parent::__construct($masterPdo);

        $this->slavePdo = $slavePdo;
    }

    public function select(AbstractBuilder $builder)
    {
        $data = $builder->getArrayCopy();
        if (isset($data['fields'])) {
            $sql = 'SELECT ' . $data['fields'];
        } else {
            $sql = 'SELECT *';
        }
        $sql .= sprintf(' FROM %s.%s ', $data['db'], $data['table']);

        $sql = $this->appendWhere($data, $sql);
        $sql = $this->appendOrder($data, $sql);
        $sql = $this->appendLimit($data, $sql);

        $statement = $this->executeOnSlave($sql, $data['params']);

        return $statement;
    }

    /**
     * @param $sql
     * @param $params
     * @return \PDOStatement
     * @throws \Exception
     */
    protected function executeOnSlave($sql, $params)
    {
        $statement = $this->slavePdo->prepare($sql);
        $statement->setFetchMode(PDO::FETCH_ASSOC);
        foreach ($params as $key => &$value) {
            if (substr($key, 0, 3) === ':o_') {
                $statement->bindParam($key, $value, PDO::PARAM_INPUT_OUTPUT, 255);
            } else {
                $statement->bindValue($key, $value);
            }
        }

        $success = $statement->execute();

        if (!$success) {
            throw new \Exception(json_encode([
                $statement->errorCode(),
                $statement->errorInfo()
            ]));
        }

        //$params的out参数有引用，这里通过copy一次的方式解除引用
        $this->param = [];
        foreach ($params as $k => $v) {
            $this->param[$k] = $v;
        }

        return $statement;
    }
}
