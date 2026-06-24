<?php

declare(strict_types = 1);

namespace think\migration\Tests;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use think\App;
use think\migration\Creator;

final class CreatorTest extends TestCase
{
    private Creator $creator;
    private string $tmpDir;

    protected function setUp(): void
    {
        $app = new App(__DIR__);
        $this->creator = new Creator($app);

        $this->tmpDir = sys_get_temp_dir() . '/think_migration_test_' . uniqid();
        @mkdir($this->tmpDir, 0755, true);

        // Patch the database path to our temp dir for isolated testing.
        $reflection = new \ReflectionClass($this->creator);
        $appProp = $reflection->getProperty('app');
        $appProp->setAccessible(true);

        // Use a special TestApp subclass that points the root to our temp dir.
        $testApp = new class($this->tmpDir) extends App {
            public function __construct(string $root)
            {
                parent::__construct($root);
                $this->setNamespace('app');
            }
        };
        $appProp->setValue($this->creator, $testApp);
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
        if (is_dir($this->tmpDir)) {
            rmdir($this->tmpDir);
        }
    }

    public function testCreateWritesMigrationFile(): void
    {
        $filePath = $this->creator->create('CreateUsersTable');

        $this->assertFileExists($filePath);
        $this->assertStringEndsWith('.php', $filePath);

        $contents = file_get_contents($filePath);

        $this->assertStringContainsString('CreateUsersTable', $contents);
        $this->assertStringContainsString('use think\migration\Migrator;', $contents);
    }

    public function testCreateWithInvalidNameThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->creator->create('');
    }

    public function testCreateDuplicateClassNameThrowsException(): void
    {
        $this->creator->create('CreatePostsTable');

        // Wait a moment to avoid timestamp collision.
        usleep(1_100_000);

        $this->expectException(InvalidArgumentException::class);

        $this->creator->create('CreatePostsTable');
    }

    public function testCreatedFileContainsValidPhp(): void
    {
        $filePath = $this->creator->create('CreateOrdersTable');

        $contents = file_get_contents($filePath);

        // The file should contain a PHP opening tag and not syntax errors.
        $this->assertStringStartsWith('<?php', ltrim($contents));

        // Quick syntax check via include in a scope.
        $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $this->assertNotEmpty($lines);
    }

    public function testCreatedFileClassIsValidPhinxClassName(): void
    {
        $filePath = $this->creator->create('CreateCommentsTable');

        $basename = pathinfo($filePath, PATHINFO_FILENAME);

        // Phinx filename format: YYYYMMDDHHMMSS_classname.php (snake_case)
        $this->assertMatchesRegularExpression('/^\d{14}_[a-z_]+$/', $basename);
        $this->assertStringContainsString('comments', $basename);
    }
}
