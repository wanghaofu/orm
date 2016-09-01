<?php namespace King\Orm\Base;

use King\Core\CoreFactory;

abstract class BaseModel extends AbstractModel implements \JsonSerializable
{
    const SCHEMA = 'override this';


    /**
     * check whether this row exists in db
     *
     * @return bool
     */
    public function isRowExists()
    {
        return $this->_rowExist;
    }

    /**
     * get changed data
     *
     * @return array
     */
    public function getRowDataForUpdate()
    {
        return array_diff_assoc($this->getRowData(), $this->_rowData);
    }

    public function save()
    {
        $this->triggerSaveHook();
        $class = static::SCHEMA;

        if (!is_subclass_of($class, BaseSchema::class)) {
            throw new \Exception(__METHOD__ . '/' . __LINE__);
        }

        /** @noinspection PhpUndefinedMethodInspection */
        $class::save($this);
        $this->syncRowData();
    }

    public function syncRowData()
    {
        $this->_rowData = $this->getRowData();
        $this->afterSave();
    }

    public function triggerSaveHook()
    {
        if ($this->isRowExists()) {
            $this->beforeUpdate();
        } else {
            $this->beforeInsert();
        }
        $this->beforeSave();
    }

    public static function getSchemaClass()
    {
        return static::SCHEMA;
    }

    public static function getPropertyName($field)
    {
        return static::$_mapping[$field];
    }

    protected function beforeSave()
    {
    }

    protected function beforeInsert()
    {
    }

    protected function afterSave(){
    }

    protected function beforeUpdate()
    {
    }

    /**
     * 维护createdTime 和 updatedTime
     */
    protected function maintainTimestamps()
    {
        if (!$this->isRowExists()) {
            $this->createdTime = time();
        }
        $this->updatedTime = time();
    }

    public function remove()
    {
        $class = static::SCHEMA;
        /** @noinspection PhpUndefinedMethodInspection */
        $class::remove($this);
        $this->_rowData = [];
        $this->_rowExist = false;
    }
}
