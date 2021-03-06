`='<'`?php namespace King\Orm\Generated;

use King\Orm\Base\BaseSchema;
use King\Orm\Generated\`$tableClassName`;

/**
 * Generated by king-orm/bin/korm.php
 *
 * @method `$tableClassName` findFirst
 * @method `$tableClassName`[]|\Traversable find
 */
class `$schemaClassName` extends BaseSchema
{
    const MODEL = `$tableClassName`::class;
    const TABLE = '`$acute . $tableName . $acute`';
`if $pk
    protected static $pk = ['`$pk|implode "', '"`'];
`/if
    const SEQNEXT = 'null';

`loop $fields $field $property
    /**
     * `$fieldComment[$field]`
     */
    const FLD_`$field|strtoupper` = '`$field`';
`/loop

`if $pk && count($pk) === 1
    /**
     * @param $value
     * @return `$tableClassName`
     */
    public static function findByPk($value)
    {
        $q = static::query();
        return $q
            ->andWhere(static::FLD_`$pk[0]|strtoupper`, '=', $q->param($value))
            ->findFirst();
    }
`/if

    protected static $fields = [
`loop $fields $field $property
        self::FLD_`$field|strtoupper`,
`/loop
    ];
}
