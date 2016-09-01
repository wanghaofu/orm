<?php namespace King\Orm;

use Silo\Builder\AbstractBuilder;
use Silo\Builtin\PDODriver;

class OraclePDODriver extends PDODriver
{
    const QUOTE = '"%s"';

    public function insert(AbstractBuilder $builder, array $pk)
    {
        if (count($pk) === 0) {
            return !!parent::_insert($builder, $pk);
        }

        $data = $builder->getArrayCopy();

        $params = [];
        $fields = [];

        foreach ($data['content'] as $field => $value) {
            $key = ':v_' . count($params) . '_';
            $params[$key] = (string)$value;
            $fields[$this->quote($field)] = $key;
        }

        $idKey = ':o_' . count($params) . '_';
        $params[$idKey] = '';

        $sql = sprintf(<<<'SQL'
BEGIN
  INSERT INTO %s %s VALUES %s RETURNING %s INTO %s;
END;

SQL
            ,
            $data['table'],
            $this->wrap($this->comma(array_keys($fields))),
            $this->wrap($this->comma(array_values($fields))),
            $this->comma(array_map([$this, 'quote'], $pk)),
            $idKey
        );

        $statement = $this->execute($sql, $params + $data['params']);

        return $this->param[$idKey];
    }

    public function select(AbstractBuilder $builder)
    {
        $data = $builder->getArrayCopy();
        if (isset($data['fields'])) {
            $sql = 'SELECT ' . $data['fields'];
        } else {
            $sql = 'SELECT *';
        }
        $sql .= ' FROM ' . $data['table'];

        $sql = $this->appendWhere($data, $sql);
        $sql = $this->appendOrder($data, $sql);

        if (isset($data['limit']) || isset($data['offset'])) {
            $lower = isset($data['offset']) ? $data['offset'] : 0;

            if (isset($data['limit'])) {
                $upper = $lower + $data['limit'];
                /**
                 * @ref http://stackoverflow.com/a/6536249/672713
                 *
                 * 按照mysql习惯，$lower从0起，而ROWNUM从1起，所以是 <= upper; > lower
                 */
                $sql = sprintf(<<<'SQLTPL'
SELECT * FROM
(
  SELECT a.*, ROWNUM rnum__ FROM
  ( %s ) a
  WHERE ROWNUM <= %s
)
WHERE rnum__  > %s
SQLTPL
                    , $sql, $upper, $lower);
            } else {
                //oracle好像不支持，好像也没用，不管了
                throw new \Exception(__METHOD__ . '/' . __LINE__);
            }
        }

        $statement = $this->execute($sql, $data['params']);

        return $statement;
    }
}
