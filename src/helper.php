<?php

use think\App;
use think\migration\Factory;
use think\migration\FactoryBuilder;

if (!function_exists('factory')) {
    /**
     * Create a model factory builder for a given class, name, and amount.
     *
     * @param mixed ...$arguments  class|class,name|class,amount|class,name,amount
     * @return FactoryBuilder
     */
    function factory(mixed ...$arguments)
    {
        $app = function_exists('app') ? app() : App::getInstance();
        /** @var Factory $factory */
        $factory = $app->make(Factory::class);

        if (isset($arguments[1]) && is_string($arguments[1])) {
            return $factory->of($arguments[0], $arguments[1])->times($arguments[2] ?? null);
        } elseif (isset($arguments[1])) {
            return $factory->of($arguments[0])->times($arguments[1]);
        }

        return $factory->of($arguments[0]);
    }
}

if (!function_exists('database_path')) {
    /**
     * 获取数据迁移脚本地址
     * @param string $path
     * @return string
     */
    function database_path($path = '')
    {
        $app = function_exists('app') ? app() : App::getInstance();
        return (
            $app->getRootPath() . 'database' . ( $path ? DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR) : '' )
        );
    }
}
