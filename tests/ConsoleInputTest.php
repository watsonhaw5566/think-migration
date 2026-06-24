<?php

declare(strict_types = 1);

namespace think\migration\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use think\console\Input as ThinkInput;
use think\migration\ConsoleInput;

final class ConsoleInputTest extends TestCase
{
    public function testImplementsSymfonyInputInterface(): void
    {
        $thinkInput = new ThinkInput([]);
        $consoleInput = new ConsoleInput($thinkInput);

        $this->assertInstanceOf(InputInterface::class, $consoleInput);
    }

    public function testBindUpdatesDefinitionWithoutException(): void
    {
        $thinkInput = new ThinkInput([]);
        $consoleInput = new ConsoleInput($thinkInput);

        $definition = new InputDefinition([
            new InputOption('connection', 'c', InputOption::VALUE_OPTIONAL, 'The connection to use'),
        ]);

        $consoleInput->bind($definition);

        $this->assertTrue($consoleInput->hasOption('connection'));
    }

    public function testBindWithUnexpectedTokensDoesNotThrow(): void
    {
        $thinkInput = new ThinkInput(['--connection', 'default']);
        $consoleInput = new ConsoleInput($thinkInput);

        $definition = new InputDefinition([
            new InputOption('verbose', 'v', InputOption::VALUE_NONE, 'Verbose output'),
        ]);

        // Should NOT throw an exception about "connection" option not existing
        $consoleInput->bind($definition);

        $this->assertTrue($consoleInput->hasOption('verbose'));
        $this->assertFalse($consoleInput->hasOption('connection'));
    }

    public function testHasArgumentAndOptionAfterBind(): void
    {
        $thinkInput = new ThinkInput([]);
        $consoleInput = new ConsoleInput($thinkInput);

        $definition = new InputDefinition([
            new \Symfony\Component\Console\Input\InputArgument('name', \Symfony\Component\Console\Input\InputArgument::OPTIONAL),
            new InputOption('dry-run', null, InputOption::VALUE_NONE, 'Run in dry mode'),
        ]);
        $consoleInput->bind($definition);

        $this->assertTrue($consoleInput->hasArgument('name'));
        $this->assertTrue($consoleInput->hasOption('dry-run'));
        $this->assertFalse($consoleInput->hasArgument('nonexistent'));
        $this->assertFalse($consoleInput->hasOption('nonexistent'));
    }

    public function testBindWithValueRequiredOption(): void
    {
        $thinkInput = new ThinkInput([]);
        $consoleInput = new ConsoleInput($thinkInput);

        $definition = new InputDefinition([
            new InputOption('environment', 'e', InputOption::VALUE_REQUIRED, 'Environment'),
        ]);

        $consoleInput->bind($definition);

        $this->assertTrue($consoleInput->hasOption('environment'));
    }

    public function testBindWithValueNoneFlag(): void
    {
        $thinkInput = new ThinkInput([]);
        $consoleInput = new ConsoleInput($thinkInput);

        $definition = new InputDefinition([
            new InputOption('force', 'f', InputOption::VALUE_NONE, 'Force the operation'),
        ]);

        $consoleInput->bind($definition);

        $this->assertTrue($consoleInput->hasOption('force'));
    }

    public function testEscapeTokenReturnsEscapedString(): void
    {
        $thinkInput = new ThinkInput([]);
        $consoleInput = new ConsoleInput($thinkInput);

        $result = $consoleInput->escapeToken('simple');
        $this->assertIsString($result);

        $result2 = $consoleInput->escapeToken('with space');
        $this->assertIsString($result2);
    }

    public function testIsInteractiveDefaultTrue(): void
    {
        $thinkInput = new ThinkInput([]);
        $consoleInput = new ConsoleInput($thinkInput);

        $this->assertTrue($consoleInput->isInteractive());
    }

    public function testSetInteractiveFalse(): void
    {
        $thinkInput = new ThinkInput([]);
        $consoleInput = new ConsoleInput($thinkInput);

        $consoleInput->setInteractive(false);
        $this->assertFalse($consoleInput->isInteractive());
    }

    public function testGetFirstArgumentWithEmptyInput(): void
    {
        $thinkInput = new ThinkInput([]);
        $consoleInput = new ConsoleInput($thinkInput);

        $this->assertNull($consoleInput->getFirstArgument());
    }

    public function testHasParameterOption(): void
    {
        $thinkInput = new ThinkInput([]);
        $consoleInput = new ConsoleInput($thinkInput);

        $this->assertFalse($consoleInput->hasParameterOption(['--foo']));
    }

    public function testValidateAfterBind(): void
    {
        $thinkInput = new ThinkInput([]);
        $consoleInput = new ConsoleInput($thinkInput);

        $definition = new InputDefinition([]);
        $consoleInput->bind($definition);

        $consoleInput->validate();

        $this->assertTrue(true);
    }
}
