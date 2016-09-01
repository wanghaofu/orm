<?php namespace King\Orm\Base;

use King\Core\Aes\Cipher;
use King\Orm\ListProvider\JoinTarget;

/**
 *
 */
class BaseSchema
{
    protected static $fields = [];

    /**
     * @param $field
     * @param $value
     * @return $this
     */
    const AESKEY = Cipher::KEY_O2O_SYSTEM;
    const DB = '`passport`';

    /**
     * @param $field
     * @param $value
     * @return $this
     */
    public function eq($field, $value)
    {
        return $this->whereFieldOp($field, '=', $value);
    }

    /**
     * @param $field
     * @param $value
     * @return $this
     */
    public function neq($field, $value)
    {
        return $this->whereFieldOp($field, '<>', $value);
    }

    /**
     * @param $field
     * @param $value
     * @return $this
     */
    public function aesEq($field, $value)
    {
        return $this->andWhere($field, '=', $this->aesParam($value));
    }

    /**
     * @param $field
     * @param $value
     * @return $this
     */
    public function gt($field, $value)
    {
        return $this->whereFieldOp($field, '>', $value);
    }

    /**
     * @param $field
     * @param $value
     * @return $this
     */
    public function lt($field, $value)
    {
        return $this->whereFieldOp($field, '<', $value);
    }

    /**
     * @param $field
     * @param $value
     * @return $this
     */

    public function gte($field, $value)
    {
        return $this->whereFieldOp($field, '>=', $value);
    }

    /**
     * @param $field
     * @param $value
     * @return $this
     */
    public function lte($field, $value)
    {
        return $this->whereFieldOp($field, '<=', $value);
    }

    /**
     * @param $field
     * @param $value
     * @return $this
     */
    public function in($field, array $values)
    {
        if (empty($values)) {
            //mysql IN () 报错,这里替代一下
            return $this->andWhere('"in" = "empty array"');
        } else {
            return $this->andWhere($field, 'IN', $this->params($values));
        }
    }

    public function aesIn($field, $value)
    {
        return $this->andWhere($field, 'IN', $this->aesParam($value));
    }

    /**
     * @param string $keyField 外键字段名 XXSchema::FLD_YY
     * @param callable|string $parentKey 传callable时参数为parent单行数据，返回该行的key值(false代表跳过)，字符串代表$row->{$parentKey}为key值
     * @return JoinTarget
     * @throws \Exception
     */
    public function on($keyField, $parentKey)
    {
        if (!in_array($keyField, static::$fields)) {
            throw new \Exception(__METHOD__ . '/' . __LINE__);
        }

        return new JoinTarget($this, $keyField, $parentKey);
    }

    /**
     * @param $field
     * @param $value
     * @return $this
     */
    protected function whereFieldOp($field, $op, $value)
    {
        return $this->andWhere($field, $op, $this->param($value));
    }

    /**
     * @param $field
     * @param $value
     * @return $this
     */
    public function dmOp($field, array $values)
    {
        return $this->andWhere($this->param($field), '=', $this->param($values[0]).'|'.$values[1]);
    }

    /**
     * @param $args
     * @return $this
     */
    public function where($args)
    {
        return call_user_func_array(
            ['parent', 'where'],
            array_map(['static', 'quoteField'], func_get_args())
        );
    }

    /**
     * @param $args
     * @return $this
     */
    public function andWhere($args)
    {
        return call_user_func_array(
            ['parent', 'andWhere'],
            array_map(['static', 'quoteField'], func_get_args())
        );
    }

    /**
     * @param $value
     * @param null $key
     * @return null|string
     */
    public function aesParam($value, $key = null)
    {
        if (is_null($key)) {
            $key = 'p_' . count($this->params);
        }
        $key = ":$key";
        $aes = ipher::init(static::AESKEY);
        $this->params[$key] = $aes->encrypt($value);
        return $key;
    }

    /**
     * @param array $values
     * @param null $keyPrefix
     * @return mixed
     */
    public function aesParams(array $values, $keyPrefix = null)
    {
        if (is_null($keyPrefix)) {
            $keyPrefix = 'p_' . count($this->params);
        }
        $driver = static::getDriver();

        $keys = [];
        $aes = Cipher::init(static::AESKEY);
        foreach ($values as $k => $value) {
            $key = ':' . $keyPrefix . '_' . $k;
            $this->params[$key] = $aes->encrypt($value);
            $keys[] = $key;
        }

        return $driver->wrap($driver->comma($keys));
    }

    /**
     * @param $args
     * @return $this
     */
    public function order($args)
    {
        return call_user_func_array(
            ['parent', 'order'],
            array_map(['static', 'quoteField'], func_get_args())
        );
    }

    protected function quoteField($arg)
    {
        if (in_array($arg, static::$fields)) {
            return static::getDriver()->quote($arg);
        } else {
            return $arg;
        }
    }

    public static function getPkField()
    {
        if (count(static::$pk) !== 1) {
            throw new \Exception(__METHOD__ . '/' . __LINE__);
        }
        return reset(static::$pk);
    }
}
