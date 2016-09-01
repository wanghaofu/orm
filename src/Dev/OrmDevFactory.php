<?php namespace King\Orm\Dev;

use Common\Dependency\Dependency;
use Common\Dependency\Derived\FrozenKeyValue;
use Doctrine\DBAL\Driver\PDOConnection;

/**
 */
class OrmDevFactory extends Dependency
{
    protected function __construct()
    {
        parent::__construct();

        $this->import([
            static::CONFIG =>
                FrozenKeyValue::wrapOffset([
                    'dsn' => 'oci:dbname=//:1521/ractest',
                    'username' => 'sit',
                    'password' => 'sit',
                ]),
        ]);
    }

    /**
     * @return \PDO
     */
    public function pdo()
    {
        $pdo = new \PDO($this->packageConfig('dsn'),
            $this->packageConfig('username'),
            $this->packageConfig('password')
        );
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        return $pdo;
    }
}
