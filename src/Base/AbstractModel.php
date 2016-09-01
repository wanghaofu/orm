<?php
namespace King\Orm\Base;

use Common\Utility\BigNumber;
use Common\Utility\Utility;
use King\Core\CoreFactory;
//use King\Core\Aes\Cipher;



abstract class AbstractModel
{
    protected static $_mapping;
    protected static $_bigNumFields = [];
    protected static $_jsonFields = [];
    protected static $_aesEncrypt = [];
    protected static $_aesKey;
    protected $_rowData;
    protected $_rowExist;

    /**
     * hydrate model instance
     *
     * @param array $row
     * @param $exist
     * @return static
     * @throws \Exception
     */
    public static function hydrate(array $row, $exist)
    {
        $instance = new static;
        $instance->_rowExist = $exist;
        $instance->_rowData = $row;

        foreach (static::$_mapping as $field => $property) {
            $instance->{$property} = static::mapField($field, $row[$field], false);
        }

        return $instance;
    }

    /**
     * 字段映射
     *
     * @param string $field field名
     * @param mixed $value value
     * @param bool $toDb true代表 PHP对象向DB实际值转换，反之代表向PHP对象转换
     * @return mixed 转换结果
     * @throws \Exception
     */
    protected static function mapField($field, $value, $toDb)
    {
        if (in_array($field, static::$_bigNumFields)) {
            if ($toDb) {
                if (!$value instanceof BigNumber) {
                    throw new \Exception(__METHOD__ . '/' . __LINE__);
                }
                return rtrim(rtrim(strval($value), '0'), '.');
            } else {
                return Utility::bigNumber($value);
            }
        }

        if (in_array($field, static::$_jsonFields)) {
            if ($toDb) {
                $json = json_encode($value, JSON_UNESCAPED_UNICODE);
                if (json_encode(json_decode($json, true)) !== json_encode($value)) {
                    CoreFactory::instance()->logger()->debug(__METHOD__, [
                        'error' => json_last_error(),
                        'value' => $value,
                    ]);
                    throw new \Exception(__METHOD__ . '/' . __LINE__);//bad json
                }

                return $json;
            } else {
                $res = json_decode($value, true);
                if (is_null($res) && strtolower($value) !== 'null') {
                    CoreFactory::instance()->logger()->debug(__METHOD__, [
                        'error' => json_last_error(),
                        'value' => $value,
                    ]);
                    throw new \Exception(__METHOD__ . '/' . __LINE__);//bad json
                }
                return $res;
            }
        }
        if (in_array($field, static::$_aesEncrypt)) {
            $cipher = NoahCipher::init(static::$_aesKey);

            if ($toDb) {
                return $cipher->encrypt($value);
            } else {
                return $cipher->decrypt($value);
            }
        }


        return $value;
    }

    /**
     * (PHP 5 &gt;= 5.4.0)<br/>
     * Specify data which should be serialized to JSON
     * @link http://php.net/manual/en/jsonserializable.jsonserialize.php
     * @return mixed data which can be serialized by <b>json_encode</b>,
     * which is a value of any type other than a resource.
     */
    function jsonSerialize()
    {
        $data = $this->getRowData();
        $result = [];
        foreach (static::$_mapping as $field => $property) {
            $result[$property] = static::mapField($field, $data[$field], false);
        }

        return $result;
    }

    /**
     * get data stored in db
     *
     * @return array
     */
    public function getRowData()
    {
        $row = [];
        foreach (static::$_mapping as $field => $property) {
            $row[$field] = static::mapField($field, $this->{$property}, true);
        }

        return $row;
    }

    public function getRowDataWithNoConvert(){
        $row = [];
        foreach (static::$_mapping as $field => $property) {
            $row[$field] = $this->{$property};
        }
        return $row;
    }
}