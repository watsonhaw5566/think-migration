<?php

declare(strict_types = 1);

namespace think\migration\Tests;

use Phinx\Db\Adapter\AdapterFactory;
use Phinx\Db\Table\Column as PhinxColumn;
use PHPUnit\Framework\TestCase;
use think\App;
use think\migration\command\migrate\Reverse;

final class ReverseTest extends TestCase
{
    private string $tmpDir;
    private string $sqliteFile;

    protected function setUp(): void
    {
        $this->tmpDir     = sys_get_temp_dir() . '/think_migration_reverse_test_' . uniqid();
        $this->sqliteFile = $this->tmpDir . '/test.sqlite';
        @mkdir($this->tmpDir, 0755, true);
    }

    protected function tearDown(): void
    {
        $migrationDir = $this->tmpDir . '/database/migrations';
        if (is_dir($migrationDir)) {
            $files = glob($migrationDir . '/*.php');
            if (is_array($files)) {
                foreach ($files as $file) {
                    unlink($file);
                }
            }
            rmdir($migrationDir);
        }
        if (is_dir($this->tmpDir . '/database')) {
            rmdir($this->tmpDir . '/database');
        }
        if (file_exists($this->sqliteFile)) {
            unlink($this->sqliteFile);
        }
        if (is_dir($this->tmpDir)) {
            rmdir($this->tmpDir);
        }
    }

    // --- 纯逻辑测试 ---

    public function testCamelizeConvertsSnakeCase(): void
    {
        $command = $this->createReverseCommand();
        $result  = $this->callProtected($command, 'camelize', ['users_table']);
        $this->assertSame('UsersTable', $result);
    }

    public function testCamelizeConvertsKebabCase(): void
    {
        $command = $this->createReverseCommand();
        $result  = $this->callProtected($command, 'camelize', ['user-posts']);
        $this->assertSame('UserPosts', $result);
    }

    public function testCamelizeHandlesSingleWord(): void
    {
        $command = $this->createReverseCommand();
        $result  = $this->callProtected($command, 'camelize', ['users']);
        $this->assertSame('Users', $result);
    }

    public function testGenerateFilenameFollowsPhinxConvention(): void
    {
        $command  = $this->createReverseCommand();
        $filename = $this->callProtected($command, 'generateFilename', ['CreateUsersTable']);

        $this->assertMatchesRegularExpression('/^\d{14}_create_users_table\.php$/', $filename);
        $this->assertStringEndsWith('_create_users_table.php', $filename);
    }

    public function testGenerateFilenameWithOffsetIncrementsTimestamp(): void
    {
        $command = $this->createReverseCommand();
        $first   = $this->callProtected($command, 'generateFilename', ['CreateUsersTable', 0]);
        $second  = $this->callProtected($command, 'generateFilename', ['CreatePostsTable', 1]);

        $firstTs  = (int) explode('_', $first)[0];
        $secondTs = (int) explode('_', $second)[0];

        $this->assertGreaterThan($firstTs, $secondTs);
    }

    // --- 列类型映射测试 ---

    public function testGenerateColumnCodeMapsVarcharToString(): void
    {
        $command = $this->createReverseCommand();
        $column  = (new PhinxColumn())
            ->setName('email')
            ->setType('varchar')
            ->setLimit(255)
            ->setNull(true);

        $code = $this->callProtected($command, 'generateColumnCode', [$column]);

        $this->assertStringContainsString('addColumn', $code);
        $this->assertStringContainsString("'email'", $code);
        $this->assertStringContainsString("'string'", $code);
        $this->assertStringNotContainsString("'limit' => 255", $code);
    }

    public function testGenerateColumnCodeMapsIntToInteger(): void
    {
        $command = $this->createReverseCommand();
        $column  = (new PhinxColumn())
            ->setName('status')
            ->setType('int')
            ->setLimit(11)
            ->setNull(true);

        $code = $this->callProtected($command, 'generateColumnCode', [$column]);

        $this->assertStringContainsString("'status'", $code);
        $this->assertStringContainsString("'integer'", $code);
    }

    public function testGenerateColumnCodeIncludesNotNull(): void
    {
        $command = $this->createReverseCommand();
        $column  = (new PhinxColumn())
            ->setName('name')
            ->setType('varchar')
            ->setLimit(100)
            ->setNull(false);

        $code = $this->callProtected($command, 'generateColumnCode', [$column]);

        $this->assertStringContainsString("'null' => false", $code);
        $this->assertStringContainsString("'limit' => 100", $code);
    }

    public function testGenerateColumnCodeIncludesDefaultValue(): void
    {
        $command = $this->createReverseCommand();
        $column  = (new PhinxColumn())
            ->setName('role')
            ->setType('varchar')
            ->setLimit(50)
            ->setNull(false)
            ->setDefault('user');

        $code = $this->callProtected($command, 'generateColumnCode', [$column]);

        $this->assertStringContainsString("'default' => 'user'", $code);
    }

    public function testGenerateColumnCodeIncludesNumericDefault(): void
    {
        $command = $this->createReverseCommand();
        $column  = (new PhinxColumn())
            ->setName('counter')
            ->setType('int')
            ->setLimit(11)
            ->setNull(false)
            ->setDefault(0);

        $code = $this->callProtected($command, 'generateColumnCode', [$column]);

        $this->assertStringContainsString("'default' => 0", $code);
    }

    public function testGenerateColumnCodeIncludesUnsigned(): void
    {
        $command = $this->createReverseCommand();
        $column  = (new PhinxColumn())
            ->setName('views')
            ->setType('bigint')
            ->setLimit(20)
            ->setNull(false)
            ->setSigned(false);

        $code = $this->callProtected($command, 'generateColumnCode', [$column]);

        $this->assertStringContainsString("'signed' => false", $code);
        $this->assertStringContainsString("'biginteger'", $code);
    }

    public function testGenerateColumnCodeIncludesIdentity(): void
    {
        $command = $this->createReverseCommand();
        $column  = (new PhinxColumn())
            ->setName('id')
            ->setType('bigint')
            ->setLimit(20)
            ->setNull(false)
            ->setIdentity(true);

        $code = $this->callProtected($command, 'generateColumnCode', [$column]);

        $this->assertStringContainsString("'identity' => true", $code);
    }

    public function testGenerateColumnCodePreservesUnknownTypes(): void
    {
        $command = $this->createReverseCommand();
        $column  = (new PhinxColumn())
            ->setName('data')
            ->setType('some_custom_type')
            ->setNull(true);

        $code = $this->callProtected($command, 'generateColumnCode', [$column]);

        $this->assertStringContainsString("'some_custom_type'", $code);
    }

    // --- SQLite 集成测试 ---

    public function testGetAllTablesFromSqlite(): void
    {
        $pdo = $this->createTestSqlitePdo();
        $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT NOT NULL)');
        $pdo->exec('CREATE TABLE posts (id INTEGER PRIMARY KEY, title TEXT NOT NULL)');

        $command = $this->createReverseCommand();
        $tables  = $this->callProtected($command, 'getAllTables', [$pdo, '']);

        $this->assertCount(2, $tables);
        $this->assertContains('users', $tables);
        $this->assertContains('posts', $tables);
    }

    public function testGetAllTablesWithTablePrefix(): void
    {
        $pdo = $this->createTestSqlitePdo();
        $pdo->exec('CREATE TABLE wp_users (id INTEGER PRIMARY KEY, name TEXT NOT NULL)');
        $pdo->exec('CREATE TABLE wp_posts (id INTEGER PRIMARY KEY, title TEXT NOT NULL)');

        $command = $this->createReverseCommand();
        $tables  = $this->callProtected($command, 'getAllTables', [$pdo, 'wp_']);

        $this->assertCount(2, $tables);
        $this->assertContains('wp_users', $tables);
        $this->assertContains('wp_posts', $tables);
    }

    public function testGetTableIndexesFromSqlite(): void
    {
        $pdo = $this->createTestSqlitePdo();
        $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, email TEXT NOT NULL, username TEXT NOT NULL)');
        $pdo->exec('CREATE UNIQUE INDEX idx_users_email ON users(email)');
        $pdo->exec('CREATE INDEX idx_users_username ON users(username)');

        $command = $this->createReverseCommand();
        $indexes = $this->callProtected($command, 'getTableIndexes', [$pdo, 'users']);

        $indexColumns = array_map(fn($idx) => $idx['columns'][0] ?? null, $indexes);
        $this->assertContains('email', $indexColumns);
        $this->assertContains('username', $indexColumns);

        $emailIdx = null;
        foreach ($indexes as $idx) {
            if (in_array('email', $idx['columns'])) {
                $emailIdx = $idx;
                break;
            }
        }
        $this->assertNotNull($emailIdx);
        $this->assertTrue($emailIdx['unique']);
    }

    public function testGetTableIndexesReturnsNoSecondaryForSimpleTable(): void
    {
        $pdo = $this->createTestSqlitePdo();
        $pdo->exec('CREATE TABLE simple (id INTEGER PRIMARY KEY, value TEXT)');

        $command = $this->createReverseCommand();
        $indexes = $this->callProtected($command, 'getTableIndexes', [$pdo, 'simple']);

        $hasSecondary = false;
        foreach ($indexes as $idx) {
            if (!$idx['primary']) {
                $hasSecondary = true;
                break;
            }
        }
        $this->assertFalse($hasSecondary);
    }

    public function testGetPathCreatesMigrationsDirectory(): void
    {
        $command = $this->createReverseCommand();
        $path    = $this->callProtected($command, 'getPath', []);

        $this->assertStringContainsString('database' . DIRECTORY_SEPARATOR . 'migrations', $path);
        $this->assertDirectoryExists($path);
    }

    // --- 端到端代码生成测试 ---

    public function testGenerateTableCodeContainsExpectedStructure(): void
    {
        $pdo = $this->createTestSqlitePdo();
        $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT NOT NULL, email TEXT NOT NULL)');

        $adapter = $this->createSqliteAdapter($pdo);
        $columns = $adapter->getColumns('users');

        $this->assertNotEmpty($columns);

        $command = $this->createReverseCommand();
        $code    = $this->callProtected($command, 'generateTableCode', ['users', $columns, 'users', $adapter]);

        $this->assertStringContainsString('$this->table(\'users\')', $code);
        $this->assertStringContainsString('$table->addColumn', $code);
        $this->assertStringContainsString('$table->create()', $code);
        $this->assertStringContainsString("'name'", $code);
        $this->assertStringContainsString("'email'", $code);
    }

    public function testGenerateMigrationContentProducesValidPhp(): void
    {
        $pdo = $this->createTestSqlitePdo();
        $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT NOT NULL, email TEXT NOT NULL)');

        $adapter = $this->createSqliteAdapter($pdo);

        $command = $this->createReverseCommand();
        $this->injectAdapter($command, $adapter);

        $content = $this->callProtected($command, 'generateMigrationContent', ['CreateUsersTable', ['users'], '']);

        $this->assertStringStartsWith('<?php', $content);
        $this->assertStringContainsString('declare(strict_types = 1);', $content);
        $this->assertStringContainsString('use think\migration\Migrator;', $content);
        $this->assertStringContainsString('class CreateUsersTable extends Migrator', $content);
        $this->assertStringContainsString('public function change(): void', $content);
        $this->assertStringContainsString('$this->table', $content);
        $this->assertStringContainsString('$table->create()', $content);

        $tmpFile = $this->tmpDir . '/generated_migration.php';
        file_put_contents($tmpFile, $content);
        $result = exec('php -l ' . escapeshellarg($tmpFile) . ' 2>&1');
        $this->assertStringContainsString('No syntax errors', $result);
        @unlink($tmpFile);
    }

    public function testGenerateMigrationContentWithMultipleTables(): void
    {
        $pdo = $this->createTestSqlitePdo();
        $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT NOT NULL)');
        $pdo->exec('CREATE TABLE posts (id INTEGER PRIMARY KEY, title TEXT NOT NULL, user_id INTEGER NOT NULL)');

        $adapter = $this->createSqliteAdapter($pdo);

        $command = $this->createReverseCommand();
        $this->injectAdapter($command, $adapter);

        $content = $this->callProtected($command, 'generateMigrationContent', ['InitialSchema', ['users', 'posts'], '']);

        $this->assertStringContainsString('class InitialSchema extends Migrator', $content);
        $this->assertStringContainsString("'users'", $content);
        $this->assertStringContainsString("'posts'", $content);
    }

    public function testGenerateMigrationContentWithTablePrefix(): void
    {
        $pdo = $this->createTestSqlitePdo();
        $pdo->exec('CREATE TABLE wp_users (id INTEGER PRIMARY KEY, name TEXT NOT NULL)');

        $adapter = $this->createSqliteAdapter($pdo);

        $command = $this->createReverseCommand();
        $this->injectAdapter($command, $adapter);

        $content = $this->callProtected($command, 'generateMigrationContent', ['CreateUsersTable', ['wp_users'], 'wp_']);

        $this->assertStringContainsString("'users'", $content);
        $this->assertStringNotContainsString("'wp_users'", $content);
    }

    // --- 辅助方法 ---

    private function createReverseCommand(): Reverse
    {
        $app = new App($this->tmpDir);
        $app->setNamespace('app');

        $command = new Reverse();
        $command->setApp($app);
        return $command;
    }

    private function injectAdapter(Reverse $command, \Phinx\Db\Adapter\AdapterInterface $adapter): void
    {
        $reflection  = new \ReflectionClass($command);
        $parent      = $reflection->getParentClass();
        $adapterProp = $parent->getProperty('adapter');
        $adapterProp->setAccessible(true);
        $adapterProp->setValue($command, $adapter);
    }

    private function callProtected(Reverse $command, string $method, array $args): mixed
    {
        $reflection = new \ReflectionClass($command);
        $m          = $reflection->getMethod($method);
        $m->setAccessible(true);
        return $m->invokeArgs($command, $args);
    }

    private function createTestSqlitePdo(): \PDO
    {
        $pdo = new \PDO('sqlite:' . $this->sqliteFile);
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        return $pdo;
    }

    private function createSqliteAdapter(\PDO $pdo): \Phinx\Db\Adapter\AdapterInterface
    {
        $options = [
            'adapter'      => 'sqlite',
            'name'         => $this->sqliteFile,
            'memory'       => false,
            'table_prefix' => '',
            'suffix'       => '',
            'version'      => '3.0.0',
        ];

        $adapter = AdapterFactory::instance()->getAdapter('sqlite', $options);
        $adapter->connect();
        return $adapter;
    }
}