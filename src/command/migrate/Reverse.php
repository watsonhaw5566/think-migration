<?php

declare(strict_types = 1);

// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK IT ]
// +----------------------------------------------------------------------
// | Copyright (c) 2016 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed under the Apache License, Version 2.0
// +----------------------------------------------------------------------

namespace think\migration\command\migrate;

use Phinx\Db\Table\Column as PhinxColumn;
use think\console\Input;
use think\console\input\Option as InputOption;
use think\console\Output;
use think\migration\command\Migrate;

class Reverse extends Migrate
{
    protected function configure()
    {
        $this
            ->setName('migrate:reverse')
            ->setDescription('Reverse-engineer migration files from existing database')
            ->addOption('connection', 'c', InputOption::VALUE_REQUIRED, 'The database connection to use')
            ->addOption('table', 't', InputOption::VALUE_OPTIONAL, 'Specific table to reverse (optional)')
            ->addOption('all', 'a', InputOption::VALUE_NONE, 'Generate all tables in one migration file')
            ->setHelp(
                'This command reads the existing database structure and reverse-engineers migration files.' . PHP_EOL .
                'Usage:' . PHP_EOL .
                '  php think migrate:reverse                 # Generate migration for each table' . PHP_EOL .
                '  php think migrate:reverse -a              # Generate all tables in one file' . PHP_EOL .
                '  php think migrate:reverse -t users        # Generate migration for specific table'
            );
    }

    protected function execute(Input $input, Output $output)
    {
        $adapter = $this->getAdapter();
        $adapter->connect();

        $specificTable = $input->getOption('table');
        $allInOne      = $input->getOption('all');

        $dbConfig    = $this->getDbConfig();
        $tablePrefix = $dbConfig['table_prefix'] ?? '';

        $pdo    = $adapter->getConnection();
        $tables = $this->getAllTables($pdo, $tablePrefix);

        if ($specificTable) {
            $tables = array_filter($tables, fn($t) => $t === $tablePrefix . $specificTable);
            if (empty($tables)) {
                $output->writeln("<error>Table '{$specificTable}' not found!</error>");
                return;
            }
        }

        $migrationTable = $dbConfig['migration_table'] ?? 'migrations';
        $tables         = array_filter($tables, fn($t) => $t !== $migrationTable);

        if (empty($tables)) {
            $output->writeln('<comment>No tables found in database.</comment>');
            return;
        }

        $output->writeln("<info>Found " . count($tables) . " table(s) in database.</info>");
        $output->writeln("");

        if ($allInOne) {
            $this->generateSingleMigration($tables, $tablePrefix, $output);
        } else {
            $this->generateMultipleMigrations($tables, $tablePrefix, $output);
        }

        $output->writeln("");
        $output->writeln('<info>Done!</info>');
    }

    protected function getAllTables($pdo, $prefix): array
    {
        $tables = [];
        $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);

        switch ($driver) {
            case 'mysql':
                $stmt = $pdo->query('SHOW TABLES');
                while ($row = $stmt->fetch(\PDO::FETCH_NUM)) {
                    $tables[] = $row[0];
                }
                break;
            case 'pgsql':
                $stmt = $pdo->query("SELECT tablename FROM pg_tables WHERE schemaname = 'public'");
                while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                    $tables[] = $row['tablename'];
                }
                break;
            case 'sqlite':
                $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table'");
                while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                    $tables[] = $row['name'];
                }
                break;
            case 'sqlsrv':
                $stmt = $pdo->query("SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_TYPE = 'BASE TABLE'");
                while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                    $tables[] = $row['TABLE_NAME'];
                }
                break;
            default:
                $stmt = $pdo->query('SHOW TABLES');
                while ($row = $stmt->fetch(\PDO::FETCH_NUM)) {
                    $tables[] = $row[0];
                }
        }

        return $tables;
    }

    protected function generateSingleMigration(array $tables, string $prefix, Output $output): void
    {
        $className = 'InitialSchema';
        $fileName  = $this->generateFilename($className);
        $path      = $this->getPath() . DIRECTORY_SEPARATOR . $fileName;
        $content   = $this->generateMigrationContent($className, $tables, $prefix);

        if (file_put_contents($path, $content, LOCK_EX)) {
            $output->writeln("  <info>+</info> {$fileName}");
        } else {
            $output->writeln("  <error>Failed to create {$fileName}</error>");
        }
    }

    protected function generateMultipleMigrations(array $tables, string $prefix, Output $output): void
    {
        $counter = 0;

        foreach ($tables as $table) {
            $tableNameWithoutPrefix = $prefix ? preg_replace('/^' . preg_quote($prefix, '/') . '/', '', $table) : $table;
            $className              = 'Create' . ucfirst($this->camelize($tableNameWithoutPrefix)) . 'Table';
            $fileName               = $this->generateFilename($className, $counter++);
            $path                   = $this->getPath() . DIRECTORY_SEPARATOR . $fileName;
            $content                = $this->generateMigrationContent($className, [$table], $prefix);

            if (file_put_contents($path, $content, LOCK_EX)) {
                $output->writeln("  <info>+</info> {$fileName}");
            } else {
                $output->writeln("  <error>Failed to create {$fileName}</error>");
            }

            usleep(100000);
        }
    }

    protected function generateMigrationContent(string $className, array $tables, string $prefix): string
    {
        $adapter     = $this->getAdapter();
        $tableCodes  = [];

        foreach ($tables as $tableName) {
            $tableNameWithoutPrefix = $prefix ? preg_replace('/^' . preg_quote($prefix, '/') . '/', '', $tableName) : $tableName;

            try {
                $columns = $adapter->getColumns($tableName);
            } catch (\Exception $e) {
                $columns = [];
            }

            if (empty($columns)) {
                continue;
            }

            $tableCodes[] = $this->generateTableCode($tableNameWithoutPrefix, $columns, $tableName, $adapter);
        }

        $tablesBlock = implode("\n\n", $tableCodes);

        $content = '<?php' . "\n\n"
            . 'declare(strict_types = 1);' . "\n\n"
            . 'use think\migration\Migrator;' . "\n"
            . 'use think\migration\db\Column;' . "\n\n"
            . "class {$className} extends Migrator" . "\n"
            . '{' . "\n"
            . '    public function change(): void' . "\n"
            . '    {' . "\n"
            . $tablesBlock . "\n"
            . '    }' . "\n"
            . '}' . "\n";

        return $content;
    }

    protected function generateTableCode(string $tableName, array $columns, string $fullTableName, $adapter): string
    {
        $indent = '        ';
        $lines  = [];

        $lines[] = $indent . '$table = $this->table(\'' . $tableName . '\');';

        foreach ($columns as $column) {
            $lines[] = $indent . $this->generateColumnCode($column);
        }

        $indexes = $this->getTableIndexes($adapter->getConnection(), $fullTableName);
        foreach ($indexes as $index) {
            if (!$index['primary']) {
                $columnsStr = "['" . implode("', '", $index['columns']) . "']";
                $options    = [];
                if ($index['unique']) {
                    $options[] = "'unique' => true";
                }
                $optionsStr = $options ? ', [' . implode(', ', $options) . ']' : '';
                $lines[]    = $indent . '$table->addIndex(' . $columnsStr . $optionsStr . ');';
            }
        }

        $lines[] = $indent . '$table->create();';

        return implode("\n", $lines);
    }

    protected function generateColumnCode(PhinxColumn $column): string
    {
        $name = $column->getName();
        $type = strtolower((string) $column->getType());

        $typeMap = [
            'int'        => 'integer',
            'tinyint'    => 'integer',
            'smallint'   => 'integer',
            'mediumint'  => 'integer',
            'bigint'     => 'biginteger',
            'varchar'    => 'string',
            'char'       => 'char',
            'text'       => 'text',
            'tinytext'   => 'text',
            'mediumtext' => 'text',
            'longtext'   => 'text',
            'datetime'   => 'datetime',
            'date'       => 'date',
            'time'       => 'time',
            'timestamp'  => 'timestamp',
            'decimal'    => 'decimal',
            'float'      => 'float',
            'double'     => 'double',
            'bool'       => 'boolean',
            'boolean'    => 'boolean',
            'json'       => 'json',
            'blob'       => 'blob',
            'binary'     => 'binary',
            'enum'       => 'enum',
        ];

        $phinxType = $typeMap[$type] ?? $type;
        $options   = [];

        if ($column->getLimit()) {
            $limit    = $column->getLimit();
            $defaults = [
                'string'     => 255,
                'integer'    => 11,
                'biginteger' => 20,
            ];
            if (!isset($defaults[$phinxType]) || $defaults[$phinxType] !== (int) $limit) {
                $options[] = "'limit' => {$limit}";
            }
        }

        if ($phinxType === 'decimal') {
            $precision = $column->getPrecision();
            $scale     = $column->getScale();
            if ($precision !== null) {
                $options[] = "'precision' => {$precision}";
            }
            if ($scale !== null) {
                $options[] = "'scale' => {$scale}";
            }
        }

        if ($column->isNull() === false) {
            $options[] = "'null' => false";
        }

        $default = $column->getDefault();
        if ($default !== null) {
            if (is_string($default)) {
                $options[] = "'default' => '" . addslashes($default) . "'";
            } elseif (is_bool($default)) {
                $options[] = "'default' => " . ($default ? 'true' : 'false');
            } elseif (is_numeric($default)) {
                $options[] = "'default' => {$default}";
            }
        }

        if ($column->getSigned() === false) {
            $options[] = "'signed' => false";
        }

        if ($column->isIdentity()) {
            $options[] = "'identity' => true";
        }

        $comment = $column->getComment();
        if (!empty($comment)) {
            $options[] = "'comment' => '" . addslashes($comment) . "'";
        }

        $optionsStr = $options ? ', [' . implode(', ', $options) . ']' : '';

        return '$table->addColumn(\'' . $name . '\', \'' . $phinxType . '\'' . $optionsStr . ');';
    }

    protected function getTableIndexes($pdo, string $tableName): array
    {
        $indexes = [];
        $driver  = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);

        try {
            switch ($driver) {
                case 'mysql':
                    $stmt = $pdo->query("SHOW INDEX FROM `{$tableName}`");
                    $rawIndexes = [];
                    while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                        $keyName = $row['Key_name'];
                        if (!isset($rawIndexes[$keyName])) {
                            $rawIndexes[$keyName] = [
                                'columns' => [],
                                'unique'  => !((bool) $row['Non_unique']),
                                'primary' => $keyName === 'PRIMARY',
                            ];
                        }
                        $rawIndexes[$keyName]['columns'][] = $row['Column_name'];
                    }
                    $indexes = array_values($rawIndexes);
                    break;

                case 'pgsql':
                    $stmt = $pdo->query("
                        SELECT 
                            i.relname as index_name,
                            a.attname as column_name,
                            ix.indisunique as is_unique,
                            ix.indisprimary as is_primary
                        FROM pg_indexes idx
                        JOIN pg_index ix ON idx.indexname = i.relname
                        JOIN pg_class t ON idx.tablename = t.relname
                        JOIN pg_class i ON idx.indexname = i.relname
                        LEFT JOIN LATERAL unnest(ix.indkey) WITH ORDINALITY u(key, n) ON true
                        LEFT JOIN pg_attribute a ON a.attrelid = t.oid AND a.attnum = u.key
                        WHERE idx.tablename = '{$tableName}'
                        ORDER BY i.relname, u.n
                    ");
                    $rawIndexes = [];
                    while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                        $keyName = $row['index_name'];
                        if (!isset($rawIndexes[$keyName])) {
                            $rawIndexes[$keyName] = [
                                'columns' => [],
                                'unique'  => (bool) $row['is_unique'],
                                'primary' => (bool) $row['is_primary'],
                            ];
                        }
                        $rawIndexes[$keyName]['columns'][] = $row['column_name'];
                    }
                    $indexes = array_values($rawIndexes);
                    break;

                case 'sqlite':
                    $stmt = $pdo->query("PRAGMA index_list('{$tableName}')");
                    $rawIndexes = [];
                    while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                        $indexName = $row['name'];
                        $isUnique  = (bool) $row['unique'];
                        $isPrimary = str_contains($indexName, 'pk') || str_contains($indexName, 'sqlite_autoindex');

                        $stmt2   = $pdo->query("PRAGMA index_info('{$indexName}')");
                        $columns = [];
                        while ($row2 = $stmt2->fetch(\PDO::FETCH_ASSOC)) {
                            $columns[] = $row2['name'];
                        }

                        $rawIndexes[$indexName] = [
                            'columns' => $columns,
                            'unique'  => $isUnique,
                            'primary' => $isPrimary,
                        ];
                    }
                    $indexes = array_values($rawIndexes);
                    break;

                case 'sqlsrv':
                    $stmt = $pdo->query("
                        SELECT 
                            i.name as index_name,
                            col.name as column_name,
                            i.is_unique,
                            i.is_primary_key
                        FROM sys.indexes i
                        JOIN sys.index_columns ic ON i.object_id = ic.object_id AND i.index_id = ic.index_id
                        JOIN sys.columns col ON ic.object_id = col.object_id AND ic.column_id = col.column_id
                        JOIN sys.tables t ON i.object_id = t.object_id
                        WHERE t.name = '{$tableName}'
                        ORDER BY i.name, ic.index_column_id
                    ");
                    $rawIndexes = [];
                    while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                        $keyName = $row['index_name'];
                        if (!isset($rawIndexes[$keyName])) {
                            $rawIndexes[$keyName] = [
                                'columns' => [],
                                'unique'  => (bool) $row['is_unique'],
                                'primary' => (bool) $row['is_primary_key'],
                            ];
                        }
                        $rawIndexes[$keyName]['columns'][] = $row['column_name'];
                    }
                    $indexes = array_values($rawIndexes);
                    break;
            }
        } catch (\Exception $e) {
            // 忽略索引获取错误
        }

        return $indexes;
    }

    protected function generateFilename(string $className, int $offset = 0): string
    {
        $timestamp = date('YmdHis') + $offset;
        return $timestamp . '_' . strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $className)) . '.php';
    }

    protected function camelize(string $input): string
    {
        return str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $input)));
    }

    protected function getPath(): string
    {
        $path = $this->app->getRootPath() . 'database' . DIRECTORY_SEPARATOR . 'migrations';

        if (!is_dir($path) && !mkdir($path, 0755, true)) {
            throw new \RuntimeException("Could not create directory: {$path}");
        }

        return $path;
    }
}
