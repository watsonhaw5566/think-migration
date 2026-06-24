<?php

declare(strict_types = 1);

namespace think\migration;

use Symfony\Component\Console\Formatter\OutputFormatterInterface;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Formatter\OutputFormatterStyleInterface;
use Symfony\Component\Console\Output\OutputInterface;
use think\console\Output as ThinkOutput;
use think\console\output\Formatter as ThinkFormatter;

/**
 * 将 ThinkPHP 的 Output 适配到 Symfony Console OutputInterface
 * 以便直接使用 vendor 中原始 Phinx 类，消除文件复制机制
 *
 * 关键细节：ThinkPHP 的 verbosity 使用 0-4 范围，而 Symfony 使用 16/32/64/128/256
 * 必须在两个体系之间做正确映射，否则 isQuiet/isVerbose/isVeryVerbose/isDebug
 * 会返回完全错误的结果。
 */
class ConsoleOutput implements OutputInterface
{
    private ThinkFormatter $thinkFormatter;

    /** Symfony verbosity => ThinkPHP verbosity */
    private const SYMFONY_TO_THINK = [
        self::VERBOSITY_QUIET        => ThinkOutput::VERBOSITY_QUIET,
        self::VERBOSITY_NORMAL       => ThinkOutput::VERBOSITY_NORMAL,
        self::VERBOSITY_VERBOSE      => ThinkOutput::VERBOSITY_VERBOSE,
        self::VERBOSITY_VERY_VERBOSE => ThinkOutput::VERBOSITY_VERY_VERBOSE,
        self::VERBOSITY_DEBUG        => ThinkOutput::VERBOSITY_DEBUG,
    ];

    /** ThinkPHP verbosity => Symfony verbosity */
    private const THINK_TO_SYMFONY = [
        ThinkOutput::VERBOSITY_QUIET        => self::VERBOSITY_QUIET,
        ThinkOutput::VERBOSITY_NORMAL       => self::VERBOSITY_NORMAL,
        ThinkOutput::VERBOSITY_VERBOSE      => self::VERBOSITY_VERBOSE,
        ThinkOutput::VERBOSITY_VERY_VERBOSE => self::VERBOSITY_VERY_VERBOSE,
        ThinkOutput::VERBOSITY_DEBUG        => self::VERBOSITY_DEBUG,
    ];

    public function __construct(
        private ThinkOutput $output
    ) {
        $this->thinkFormatter = new ThinkFormatter();
    }

    /**
     * 从 Symfony $options 参数中提取 verbosity level
     * $options 可以是 verbosity level 与 output type 的组合位掩码
     */
    private function getVerbosityFromOptions(int $options): int
    {
        if ($options === 0) {
            return self::VERBOSITY_NORMAL;
        }
        if ($options & self::VERBOSITY_QUIET) {
            return self::VERBOSITY_QUIET;
        }
        if ($options & self::VERBOSITY_DEBUG) {
            return self::VERBOSITY_DEBUG;
        }
        if ($options & self::VERBOSITY_VERY_VERBOSE) {
            return self::VERBOSITY_VERY_VERBOSE;
        }
        if ($options & self::VERBOSITY_VERBOSE) {
            return self::VERBOSITY_VERBOSE;
        }
        return self::VERBOSITY_NORMAL;
    }

    private function symfonyToThinkVerbosity(int $symfonyLevel): int
    {
        return self::SYMFONY_TO_THINK[$symfonyLevel] ?? ThinkOutput::VERBOSITY_NORMAL;
    }

    private function thinkToSymfonyVerbosity(int $thinkLevel): int
    {
        return self::THINK_TO_SYMFONY[$thinkLevel] ?? self::VERBOSITY_NORMAL;
    }

    private function shouldOutputAt(int $symfonyLevel): bool
    {
        $currentSymfony = $this->thinkToSymfonyVerbosity($this->output->getVerbosity());
        return $currentSymfony >= $symfonyLevel;
    }

    /**
     * @param string|iterable<string> $messages
     */
    public function write(iterable|string $messages, bool $newline = false, int $options = 0): void
    {
        $verbosity = $this->getVerbosityFromOptions($options);
        if (!$this->shouldOutputAt($verbosity)) {
            return;
        }

        if (is_iterable($messages)) {
            foreach ($messages as $message) {
                $this->output->write((string) $message, $newline);
            }
            return;
        }
        $this->output->write((string) $messages, $newline);
    }

    /**
     * @param string|iterable<string> $messages
     */
    public function writeln(iterable|string $messages, int $options = 0): void
    {
        $this->write($messages, true, $options);
    }

    public function setVerbosity(int $level): void
    {
        $this->output->setVerbosity($this->symfonyToThinkVerbosity($level));
    }

    public function getVerbosity(): int
    {
        return $this->thinkToSymfonyVerbosity($this->output->getVerbosity());
    }

    public function isQuiet(): bool
    {
        return $this->getVerbosity() === self::VERBOSITY_QUIET;
    }

    public function isVerbose(): bool
    {
        return $this->getVerbosity() >= self::VERBOSITY_VERBOSE;
    }

    public function isVeryVerbose(): bool
    {
        return $this->getVerbosity() >= self::VERBOSITY_VERY_VERBOSE;
    }

    public function isDebug(): bool
    {
        return $this->getVerbosity() >= self::VERBOSITY_DEBUG;
    }

    public function setDecorated(bool $decorated): void
    {
        if (method_exists($this->output, 'setDecorated')) {
            $this->output->setDecorated($decorated);
        }
        $this->thinkFormatter->setDecorated($decorated);
    }

    public function isDecorated(): bool
    {
        return $this->thinkFormatter->isDecorated();
    }

    public function setFormatter(OutputFormatterInterface $formatter): void
    {
        $this->thinkFormatter->setDecorated($formatter->isDecorated());
    }

    public function getFormatter(): OutputFormatterInterface
    {
        $thinkFormatter = $this->thinkFormatter;

        return new class($thinkFormatter) implements OutputFormatterInterface {
            public function __construct(
                private ThinkFormatter $thinkFormatter
            ) {
            }

            public function setDecorated(bool $decorated): void
            {
                $this->thinkFormatter->setDecorated($decorated);
            }

            public function isDecorated(): bool
            {
                return $this->thinkFormatter->isDecorated();
            }

            public function setStyle(string $name, OutputFormatterStyleInterface $style): void
            {
                // Symfony 样式无法直接映射到 ThinkPHP Formatter，暂不支持自定义样式
            }

            public function hasStyle(string $name): bool
            {
                return $this->thinkFormatter->hasStyle($name);
            }

            public function getStyle(string $name): OutputFormatterStyleInterface
            {
                return new OutputFormatterStyle();
            }

            public function format(?string $message): ?string
            {
                if ($message === null) {
                    return null;
                }
                return $this->thinkFormatter->format($message);
            }
        };
    }
}