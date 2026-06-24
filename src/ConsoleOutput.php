<?php
declare(strict_types=1);

namespace think\migration;

use Symfony\Component\Console\Formatter\OutputFormatterInterface;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Formatter\OutputFormatterStyleInterface;
use Symfony\Component\Console\Output\OutputInterface;
use think\console\Output;

/**
 * 将 ThinkPHP 的 Output 适配到 Symfony Console OutputInterface
 * 以便直接使用 vendor 中原始 Phinx 类，消除文件复制机制
 */
class ConsoleOutput implements OutputInterface
{
    public function __construct(
        private Output $output,
    ) {
    }

    /**
     * @param string|iterable<string> $messages
     */
    public function write(iterable|string $messages, bool $newline = false, int $options = 0): void
    {
        if (is_iterable($messages)) {
            foreach ($messages as $message) {
                $this->output->write($message, $newline);
            }
            return;
        }
        $this->output->write($messages, $newline);
    }

    /**
     * @param string|iterable<string> $messages
     */
    public function writeln(iterable|string $messages, int $options = 0): void
    {
        if (is_iterable($messages)) {
            foreach ($messages as $message) {
                $this->output->writeln($message);
            }
            return;
        }
        $this->output->writeln($messages);
    }

    public function setVerbosity(int $level): void
    {
        $this->output->setVerbosity($level);
    }

    public function getVerbosity(): int
    {
        return $this->output->getVerbosity();
    }

    public function isQuiet(): bool
    {
        return $this->output->getVerbosity() === self::VERBOSITY_QUIET;
    }

    public function isVerbose(): bool
    {
        return $this->output->getVerbosity() >= self::VERBOSITY_VERBOSE;
    }

    public function isVeryVerbose(): bool
    {
        return $this->output->getVerbosity() >= self::VERBOSITY_VERY_VERBOSE;
    }

    public function isDebug(): bool
    {
        return $this->output->getVerbosity() >= self::VERBOSITY_DEBUG;
    }

    public function setDecorated(bool $decorated): void
    {
        // ThinkPHP Output 不支持独立设置 decorated
    }

    public function isDecorated(): bool
    {
        return false;
    }

    public function setFormatter(OutputFormatterInterface $formatter): void
    {
        // ThinkPHP Output 不支持独立设置 formatter
    }

    public function getFormatter(): OutputFormatterInterface
    {
        // 返回简单的 formatter，Phinx 只会用 write/writeln/getVerbosity
        return new class implements OutputFormatterInterface {
            public function setDecorated(bool $decorated): void {}
            public function isDecorated(): bool { return false; }
            public function setStyle(string $name, OutputFormatterStyleInterface $style): void {}
            public function hasStyle(string $name): bool { return false; }
            public function getStyle(string $name): OutputFormatterStyleInterface {
                return new OutputFormatterStyle();
            }
            public function format(?string $message): ?string { return $message; }
        };
    }
}