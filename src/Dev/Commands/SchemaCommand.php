<?php namespace King\Orm\Dev\Commands;


/**
 * 构建数据库 这里是目前主要的地方
 */

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Comparator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use King\Core\CoreFactory;
use King\Orm\Dev\SchemaTool;


class SchemaCommand extends Command
{
    const MODE = 'mode';
    const TARGET = 'target';

    const MODE_CREATE = 'create';
    const MODE_MIGRATE = 'migrate';
    const MODE_DROP = 'drop';
    const MODE_MIGRATE_FROM = 'migrate_from';

    const OPTION_EXEC = 'exec';

    const OPTION_TABLE = 'table';

    const OPTION_DB = 'db';

    protected function configure()
    {
        parent::configure();
        $this
            ->setName( 'schema' )
            ->setDescription( 'generate/run schema sql' )
            ->addArgument( self::MODE, InputArgument::OPTIONAL, 'drop|create|migrate|migrate_from', self::MODE_CREATE )
            ->addArgument( self::TARGET, InputArgument::OPTIONAL, '<target>', '' )
            ->addOption( self::OPTION_EXEC, null, null, '直接执行' )
            ->addOption( self::OPTION_TABLE, null, InputOption::VALUE_REQUIRED, '表名' )
            ->addOption( self::OPTION_DB, null, InputOption::VALUE_REQUIRED, '库名', 'passport' );
    }

    protected function execute( InputInterface $input, OutputInterface $output )
    {
        $schema = $this->getSchema( $input );


        $pdo = CoreFactory::instance()->pdo();


        $connection = DriverManager::getConnection( [
            'pdo'    => $pdo,
            'dbname' => $input->getOption( self::OPTION_DB ),
        ] );


        /**
         * @link http://doctrine-orm.readthedocs.org/en/latest/cookbook/mysql-enums.html
         */
        $connection->getDatabasePlatform()->registerDoctrineTypeMapping( 'enum', 'string' );

//
//        $schema = $connection->getSchemaManager()->createSchema();
//        var_dump($schema->toSql($connection->getDatabasePlatform()));
//        return;

        $platform = $connection->getDatabasePlatform();
        switch ( $input->getArgument( self::MODE ) ) {
            case self::MODE_DROP:
//                $sqls = $this->getDropSql($schema, $platform);
                $sqls = $schema->toDropSql( $platform );
                break;
            case self::MODE_CREATE:
                $sqls = $schema->toSql( $platform );
                break;
            case self::MODE_MIGRATE:
                $comparator = new Comparator();
                $schemaDiff = $comparator->compare( $connection->getSchemaManager()->createSchema(), $schema );

                $sqls = $schemaDiff->toSaveSql( $platform );
                break;
            case self::MODE_MIGRATE_FROM:
                $comparator = new Comparator();
                $fromSchema = SchemaTool::createSchemaFromDir( $input->getArgument( self::TARGET ) );
                if ( empty( $fromSchema->getTables() ) ) {
                    throw new \Exception( 'empty target schema' );
                }
                $schemaDiff = $comparator->compare( $fromSchema, $schema );

                $sqls = $schemaDiff->toSaveSql( $platform );
                break;
            default:
                throw new \Exception( 'unimplemented' );
        }

        if ( $input->getOption( self::OPTION_EXEC ) ) {
//            $pdo = OrmDevFactory::instance()->pdo();
            foreach ( $sqls as $sql ) {
                echo "> running sql:", PHP_EOL, $sql, PHP_EOL;
                try {
                    $pdo->query( $sql );
                }catch(PDOException $e)
                {
                    de($e);
                }
            }
            echo '> done', PHP_EOL;
        } else {
            echo implode( ";\n----\n", $sqls ), PHP_EOL;
        }
    }

    protected function getSchema( InputInterface $input )
    {


        $file = $input->getOption( self::OPTION_TABLE );
        if ( empty( $file ) ) {
            $dir = __DIR__ . '/../../../schema';
            $schema = SchemaTool::createSchemaFromDir( $dir );
        } else {
            $file = sprintf( '%s/../../../schema/%s.yml', __DIR__, $file );
            if ( !is_file( $file ) ) {
                throw new \InvalidArgumentException( 'cannot find: ' . $file );
            }
            $schema = SchemaTool::createTableFromFile( $file );
        }

        return $schema;
    }
}
