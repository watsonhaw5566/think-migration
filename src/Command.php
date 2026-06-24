<?php

declare(strict_types = 1);

// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK IT ]
// +----------------------------------------------------------------------
// | Copyright (c) 2016 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: yunwuxin <448901948@qq.com>
// +----------------------------------------------------------------------
namespace think\migration;

use InvalidArgumentException;
use Phinx\Config\Config;
use Phinx\Db\Adapter\AdapterFactory;

abstract class Command extends \think\console\Command
{
    protected $adapter;

    /** @var ConsoleInput|null */
    protected ?ConsoleInput $symfonyInput = null;

    /** @var ConsoleOutput|null */
    protected ?ConsoleOutput $symfonyOutput = null;

    /**
     * 获取适配为 Symfony InputInterface 的输入对象
     */
    public function getSymfonyInput(): ConsoleInput
    {
        if ($this->symfonyInput === null) {
            $this->symfonyInput = new ConsoleInput($this->input);
        }
        return $this->symfonyInput;
    }

    /**
     * 获取适配为 Symfony OutputInterface 的输出对象
     */
    public function getSymfonyOutput(): ConsoleOutput
    {
        if ($this->symfonyOutput === null) {
            $this->symfonyOutput = new ConsoleOutput($this->output);
        }
        return $this->symfonyOutput;
    }

    public function getAdapter()
    {
        if (isset($this->adapter)) {
            return $this->adapter;
        }

        $options = $this->getDbConfig();

        $adapter = AdapterFactory::instance()->getAdapter($options['adapter'], $options);

        if ($adapter->hasOption('table_prefix') || $adapter->hasOption('table_suffix')) {
            $adapter = AdapterFactory::instance()->getWrapper('prefix', $adapter);
        }

        $adapter->setInput($this->getSymfonyInput());
        $adapter->setOutput($this->getSymfonyOutput());

        $this->adapter = $adapter;

        return $adapter;
    }

    /**
     * 获取数据库配置
     * @return array
     */
    protected function getDbConfig(): array
    {
        // 获取连接名称，默认为默认连接
        $default = $this->input->getOption('connection') ?? $this->app->config->get('database.default');

        $config = $this->app->config->get("database.connections.{$default}");

        if (0 == $config['deploy']) {
            $dbConfig = [
                'adapter' => $config['type'],
                'host' => $config['hostname'],
                'name' => $config['database'],
                'user' => $config['username'],
                'pass' => $config['password'],
                'port' => $config['hostport'],
                'charset' => $config['charset'],
                'suffix' => $config['suffix'] ?? '',
                'table_prefix' => $config['prefix']
            ];
        } else {
            $typeParts = explode(',', (string) $config['type']);
            $hostParts = explode(',', (string) $config['hostname']);
            $nameParts = explode(',', (string) $config['database']);
            $userParts = explode(',', (string) $config['username']);
            $passParts = explode(',', (string) $config['password']);
            $portParts = explode(',', (string) $config['hostport']);
            $charsetParts = explode(',', (string) $config['charset']);
            $suffixParts = explode(',', (string) ( $config['suffix'] ?? '' ));
            $prefixParts = explode(',', (string) $config['prefix']);

            $dbConfig = [
                'adapter' => $typeParts[0] ?? '',
                'host' => $hostParts[0] ?? '',
                'name' => $nameParts[0] ?? '',
                'user' => $userParts[0] ?? '',
                'pass' => $passParts[0] ?? '',
                'port' => $portParts[0] ?? '',
                'charset' => $charsetParts[0] ?? '',
                'suffix' => $suffixParts[0] ?? '',
                'table_prefix' => $prefixParts[0] ?? ''
            ];
        }

        $table = $this->app->config->get('database.migration_table', 'migrations');

        $dbConfig['migration_table'] = $dbConfig['table_prefix'] . $table;
        $dbConfig['version_order'] = Config::VERSION_ORDER_CREATION_TIME;

        return $dbConfig;
    }

    protected function verifyMigrationDirectory(string $path)
    {
        if (!is_dir($path)) {
            throw new InvalidArgumentException(sprintf('Migration directory "%s" does not exist', $path));
        }

        if (!is_writable($path)) {
            throw new InvalidArgumentException(sprintf('Migration directory "%s" is not writable', $path));
        }
    }
}
