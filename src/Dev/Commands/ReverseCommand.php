<?php namespace King\Orm\Dev\Commands;

use Doctrine\DBAL\DriverManager;
use King\Core\CoreFactory;
use King\Orm\Dev\SchemaTool;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;


class ReverseCommand extends Command
{
    const ARG_TABLE = 'table';
    const OPTION_DB = 'db';

    protected function configure()
    {
        parent::configure();
        $this
            ->setName('reverse')
            ->setDescription('reverse yml content from table')
            ->addOption(self::OPTION_DB, null, InputOption::VALUE_REQUIRED, '库名', 'king')
            ->addArgument(self::ARG_TABLE, InputArgument::REQUIRED, '表名');
    }

    /**
     * @inheritDoc
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $pdo = CoreFactory::instance()->pdo();
        $connection = DriverManager::getConnection([
            'pdo' => $pdo,
            'dbname' => $input->getOption(self::OPTION_DB),
        ]);

        /**
         * @link http://doctrine-orm.readthedocs.org/en/latest/cookbook/mysql-enums.html
         */
        $connection->getDatabasePlatform()->registerDoctrineTypeMapping('enum', 'string');

        $platform = $connection->getDatabasePlatform();

        $schema = $connection->getSchemaManager()->createSchema();

        $table = $schema->getTable($input->getArgument(self::ARG_TABLE));

        $output->write(SchemaTool::reverseYmlFromTable($table));
    }
}
