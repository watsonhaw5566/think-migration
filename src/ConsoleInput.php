<?php

declare(strict_types = 1);

namespace think\migration;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use think\console\Input;
use think\console\input\Argument;
use think\console\input\Definition;
use think\console\input\Option;

/**
 * 将 ThinkPHP 的 Input 适配到 Symfony Console InputInterface
 * 以便直接使用 vendor 中原始 Phinx 类，消除文件复制机制
 */
class ConsoleInput implements InputInterface
{
    public function __construct(
        private Input $input
    ) {
    }

    public function getFirstArgument(): ?string
    {
        return $this->input->getFirstArgument();
    }

    public function hasParameterOption(string|array $values, bool $onlyParams = false): bool
    {
        $values = (array) $values;
        foreach ($values as $v) {
            if ($this->input->hasParameterOption($v)) {
                return true;
            }
        }
        return false;
    }

    public function getParameterOption(
        string|array $values,
        string|bool|int|float|array|null $default = false,
        bool $onlyParams = false
    ): mixed {
        return $this->input->getParameterOption($values, $default);
    }

    public function bind(InputDefinition $definition): void
    {
        $this->input->bind($this->convertDefinition($definition));
    }

    public function validate(): void
    {
        $this->input->validate();
    }

    public function getArguments(): array
    {
        return $this->input->getArguments();
    }

    public function getArgument(string $name): mixed
    {
        return $this->input->getArgument($name);
    }

    public function setArgument(string $name, mixed $value): void
    {
        $this->input->setArgument($name, $value);
    }

    public function hasArgument(string $name): bool
    {
        return $this->input->hasArgument($name);
    }

    public function getOptions(): array
    {
        return $this->input->getOptions();
    }

    public function getOption(string $name): mixed
    {
        return $this->input->getOption($name);
    }

    public function setOption(string $name, mixed $value): void
    {
        $this->input->setOption($name, $value);
    }

    public function hasOption(string $name): bool
    {
        return $this->input->hasOption($name);
    }

    public function isInteractive(): bool
    {
        return $this->input->isInteractive();
    }

    public function setInteractive(bool $interactive): void
    {
        $this->input->setInteractive($interactive);
    }

    public function __toString(): string
    {
        return (string) $this->input;
    }

    /**
     * 将 Symfony InputDefinition 转换为 ThinkPHP Definition
     */
    private function convertDefinition(InputDefinition $symfonyDefinition): Definition
    {
        $thinkDefinition = new Definition();

        foreach ($symfonyDefinition->getArguments() as $argument) {
            $thinkDefinition->addArgument($this->convertArgument($argument));
        }

        foreach ($symfonyDefinition->getOptions() as $option) {
            $thinkDefinition->addOption($this->convertOption($option));
        }

        return $thinkDefinition;
    }

    private function convertArgument(InputArgument $symfonyArg): Argument
    {
        $mode = 0;
        if ($symfonyArg->isRequired()) {
            $mode |= Argument::REQUIRED;
        }
        if ($symfonyArg->isArray()) {
            $mode |= Argument::IS_ARRAY;
        }

        return new Argument(
            $symfonyArg->getName(),
            $mode,
            $symfonyArg->getDescription() ?? '',
            $symfonyArg->getDefault()
        );
    }

    private function convertOption(InputOption $symfonyOpt): Option
    {
        $mode = 0;
        if ($symfonyOpt->isValueRequired()) {
            $mode |= Option::VALUE_REQUIRED;
        }
        if ($symfonyOpt->isValueOptional()) {
            $mode |= Option::VALUE_OPTIONAL;
        }
        if ($symfonyOpt->isArray()) {
            $mode |= Option::VALUE_IS_ARRAY;
        }
        if ($mode === 0) {
            $mode = Option::VALUE_NONE;
        }

        return new Option(
            $symfonyOpt->getName(),
            $symfonyOpt->getShortcut(),
            $mode,
            $symfonyOpt->getDescription() ?? '',
            $symfonyOpt->getDefault()
        );
    }
}
