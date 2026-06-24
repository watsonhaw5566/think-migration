<?php

declare(strict_types = 1);

namespace think\migration\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Formatter\OutputFormatterInterface;
use Symfony\Component\Console\Output\OutputInterface;
use think\console\Output as ThinkOutput;
use think\migration\ConsoleOutput;

final class ConsoleOutputTest extends TestCase
{
    private ThinkOutput $thinkOutput;
    private ConsoleOutput $consoleOutput;

    protected function setUp(): void
    {
        $this->thinkOutput = new ThinkOutput();
        $this->consoleOutput = new ConsoleOutput($this->thinkOutput);
    }

    public function testImplementsSymfonyOutputInterface(): void
    {
        $this->assertInstanceOf(OutputInterface::class, $this->consoleOutput);
    }

    public function testDefaultVerbosityIsNormal(): void
    {
        $this->assertSame(OutputInterface::VERBOSITY_NORMAL, $this->consoleOutput->getVerbosity());
    }

    public function testSetVerbosityToQuiet(): void
    {
        $this->consoleOutput->setVerbosity(OutputInterface::VERBOSITY_QUIET);
        $this->assertSame(OutputInterface::VERBOSITY_QUIET, $this->consoleOutput->getVerbosity());
        $this->assertTrue($this->consoleOutput->isQuiet());
    }

    public function testSetVerbosityToVerbose(): void
    {
        $this->consoleOutput->setVerbosity(OutputInterface::VERBOSITY_VERBOSE);
        $this->assertTrue($this->consoleOutput->isVerbose());
        $this->assertFalse($this->consoleOutput->isVeryVerbose());
    }

    public function testSetVerbosityToVeryVerbose(): void
    {
        $this->consoleOutput->setVerbosity(OutputInterface::VERBOSITY_VERY_VERBOSE);
        $this->assertTrue($this->consoleOutput->isVerbose());
        $this->assertTrue($this->consoleOutput->isVeryVerbose());
        $this->assertFalse($this->consoleOutput->isDebug());
    }

    public function testSetVerbosityToDebug(): void
    {
        $this->consoleOutput->setVerbosity(OutputInterface::VERBOSITY_DEBUG);
        $this->assertTrue($this->consoleOutput->isDebug());
    }

    public function testHasVerbosityChecksCorrectly(): void
    {
        $this->consoleOutput->setVerbosity(OutputInterface::VERBOSITY_VERBOSE);
        $this->assertTrue($this->consoleOutput->hasVerbosity(OutputInterface::VERBOSITY_NORMAL));
        $this->assertTrue($this->consoleOutput->hasVerbosity(OutputInterface::VERBOSITY_VERBOSE));
        $this->assertFalse($this->consoleOutput->hasVerbosity(OutputInterface::VERBOSITY_VERY_VERBOSE));
    }

    public function testSetDecoratedUpdatesDecoratedState(): void
    {
        $this->consoleOutput->setDecorated(true);
        $this->assertTrue($this->consoleOutput->isDecorated());
    }

    public function testSetDecoratedFalseUpdatesDecoratedState(): void
    {
        $this->consoleOutput->setDecorated(true);
        $this->consoleOutput->setDecorated(false);
        $this->assertFalse($this->consoleOutput->isDecorated());
    }

    public function testGetFormatterReturnsSymfonyFormatterInterface(): void
    {
        $formatter = $this->consoleOutput->getFormatter();
        $this->assertInstanceOf(OutputFormatterInterface::class, $formatter);
    }

    public function testFormatterFormatsStyledMessages(): void
    {
        $this->consoleOutput->setDecorated(true);
        $formatter = $this->consoleOutput->getFormatter();
        $result = $formatter->format('<info>test message</info>');
        $this->assertStringContainsString('test message', $result);
    }

    public function testFormatterDecoratedStateIsRespected(): void
    {
        $this->consoleOutput->setDecorated(false);
        $formatter = $this->consoleOutput->getFormatter();
        $this->assertFalse($formatter->isDecorated());
    }

    public function testWriteAndWritelnAreCallable(): void
    {
        $this->consoleOutput->write('hello');
        $this->consoleOutput->writeln('world');
        $this->assertTrue(true);
    }

    public function testVerbosityLevelMappingIsBidirectional(): void
    {
        $levels = [
            OutputInterface::VERBOSITY_QUIET => ThinkOutput::VERBOSITY_QUIET,
            OutputInterface::VERBOSITY_NORMAL => ThinkOutput::VERBOSITY_NORMAL,
            OutputInterface::VERBOSITY_VERBOSE => ThinkOutput::VERBOSITY_VERBOSE,
            OutputInterface::VERBOSITY_VERY_VERBOSE => ThinkOutput::VERBOSITY_VERY_VERBOSE,
            OutputInterface::VERBOSITY_DEBUG => ThinkOutput::VERBOSITY_DEBUG
        ];

        foreach ($levels as $symfonyLevel => $thinkLevel) {
            $this->consoleOutput->setVerbosity($symfonyLevel);
            $reflection = new \ReflectionObject($this->thinkOutput);
            $verbosityProp = $reflection->getProperty('verbosity');
            $verbosityProp->setAccessible(true);
            $actualThinkLevel = $verbosityProp->getValue($this->thinkOutput);
            $this->assertSame($thinkLevel, $actualThinkLevel);
        }
    }
}
