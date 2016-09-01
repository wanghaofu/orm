<?php namespace King\Orm\Dev;

use Symfony\Component\Console\Application;


class Toolbox extends Application
{
    public function __construct($name = 'KingOrm Toolbox', $version = 'dev')
    {
        parent::__construct($name, $version);

        foreach(glob(__DIR__ . '/Commands/*.php') as $file) {
            $name = substr($file, strlen(__DIR__), -4);
            $name = __NAMESPACE__ . str_replace('/', '\\', $name);
            $this->add(new $name);
        }
    }

    public static function main()
    {
        $instance = new static;
        $instance->run();
    }
}
