<?php namespace King\Orm\Dev\Commands;

use Doctrine\Common\Inflector\Inflector;
use Doctrine\DBAL\Types\Type;
use King\Orm\Dev\SchemaTool;
use King\Orm\Dev\SimpleTemplate;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;


class GenerateCommand extends Command
{
    const OPTION_TABLE = 'table';
    const OPTION_DB = 'db';

    protected function configure()
    {
        parent::configure();
        $this
            ->setName('generate')
            ->setDescription('generate PHP Class files')
            ->addOption(self::OPTION_TABLE, null, InputOption::VALUE_REQUIRED, '表名')
            ->addOption(self::OPTION_DB, null, InputOption::VALUE_REQUIRED, '库名', 'king');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $table = $input->getOption(self::OPTION_TABLE);

        $target = __DIR__ . '/../../Generated/*';
        if (empty($table)) {
            echo `rm -rf $target`;
            $dir = __DIR__ . '/../../../schema';
            $schema = SchemaTool::createSchemaFromDir($dir);
        } else {
            $file = sprintf('%s/../../../schema/%s.yml', __DIR__, $table);
            if (!is_file($file)) {
                throw new \InvalidArgumentException('cannot find: ' . $file);
            }
            $schema = SchemaTool::createTableFromFile($file);
        }

        $tableTpl = SimpleTemplate::instance(file_get_contents(__DIR__ . '/../../../tpl/table.tpl'));
        $schemaTpl = SimpleTemplate::instance(file_get_contents(__DIR__ . '/../../../tpl/schema.tpl'));
        foreach ($schema->getTables() as $table) {
            $data = [];
            $data['acute'] = '`';
            $data['dbName'] = $input->getOption(self::OPTION_DB);
            $data['tableName'] = $table->getName();
            $data['tableClassName'] = Inflector::classify(strtolower($data['tableName'])) . 'Model';
            $data['schemaClassName'] = Inflector::classify(strtolower($data['tableName'])) . 'Schema';

            $data['fields'] = [];
            $data['fieldType'] = [];
            $data['fieldComment'] = [];
            $data['fieldDefault'] = [];
            foreach ($table->getColumns() as $col) {
                $field = $col->getName();
                $data['fields'][$field] = Inflector::camelize(strtolower($field));
                switch ($col->getType()->getName()) {
                    case Type::BIGINT:
                    case Type::STRING:
                    case Type::TEXT:
                    case Type::BINARY:
                    case Type::DECIMAL:
                    case Type::BLOB:
                        $data['fieldType'][$field] = 'string';
                        break;
                    case Type::INTEGER:
                    case Type::SMALLINT:
                        $data['fieldType'][$field] = 'int';
                        break;
                    case Type::FLOAT:
                        $data['fieldType'][$field] = 'float';
                        break;
                    case Type::BOOLEAN:
                        $data['fieldType'][$field] = 'bool';
                        break;
                    default:
                        $data['fieldType'][$field] = 'string';
                }

                $data['fieldDefault'][$field] = $col->getDefault();
                $data['fieldComment'][$field] = $col->getComment();
            }


            if ($table->hasPrimaryKey()) {
                $data['pk'] = $table->getPrimaryKeyColumns();
            } else {
                $data['pk'] = null;
            }

            $data['pkProperty'] = [];
            if (isset($data['pk']) == true) {
                foreach ($data['pk'] as $pk) {
                    $data['pkProperty'][] = $data['fields'][$pk];
                    $data['auto'] = $table->getColumn($pk)
                        ->getAutoincrement();
                }
            } else {
                echo "***Notice : Table config File [ {$data['tableName']} ] [ primaryKey ] is not set .\n";
            }

            if (count($data['pk']) > 1) {
                throw new \Exception('unimplemented...');
            }

            $this->flush(__DIR__ . '/../../Generated/' . $data['schemaClassName'] . '.php', $schemaTpl->render($data));
            $this->flush(__DIR__ . '/../../Generated/' . $data['tableClassName'] . '.php', $tableTpl->render($data));
        }
    }


    protected function flush($file, $content)
    {
        //f*ck windows
        $content = str_replace("\r\n", "\n", $content);
        file_put_contents($file, $content);
        $file = realpath($file);
        echo "write $file", PHP_EOL;
    }
}
