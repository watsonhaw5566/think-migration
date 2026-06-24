<?php

declare(strict_types = 1);

namespace think\migration;

use Symfony\Component\Console\Input\InputArgument as SymfonyInputArgument;
use Symfony\Component\Console\Input\InputDefinition as SymfonyInputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption as SymfonyInputOption;
use think\console\Input;
use think\console\input\Argument as ThinkInputArgument;
use think\console\input\Definition as ThinkInputDefinition;
use think\console\input\Option as ThinkInputOption;

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

    /**
     * 将 Symfony InputDefinition 绑定到 ThinkPHP Input
     *
     * 注意：不直接调用 ThinkPHP 的 bind()，因为它会调用 parse() 重新解析 tokens。
     * 如果 tokens 中包含 ThinkPHP 自身的 --connection 等选项但 Phinx 定义中没有，
     * 会抛出 "The option does not exist" 异常。
     * 这里通过反射直接替换 definition，并重置已解析的 arguments/options。
     */
    public function bind(SymfonyInputDefinition $definition): void
    {
        $thinkDefinition = $this->convertDefinition($definition);

        $reflection = new \ReflectionObject($this->input);

        if ($reflection->hasProperty('definition')) {
            $prop = $reflection->getProperty('definition');
            $prop->setAccessible(true);
            $prop->setValue($this->input, $thinkDefinition);
        }

        if ($reflection->hasProperty('arguments')) {
            $prop = $reflection->getProperty('arguments');
            $prop->setAccessible(true);
            $prop->setValue($this->input, []);
        }
        if ($reflection->hasProperty('options')) {
            $prop = $reflection->getProperty('options');
            $prop->setAccessible(true);
            $prop->setValue($this->input, []);
        }
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
        $reflection = new \ReflectionObject($this->input);
        if ($reflection->hasProperty('definition')) {
            $prop = $reflection->getProperty('definition');
            $prop->setAccessible(true);
            $definition = $prop->getValue($this->input);
            if ($definition !== null) {
                return $definition->hasOption($name);
            }
        }
        return false;
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

    public function escapeToken(string $token): string
    {
        if (method_exists($this->input, 'escapeToken')) {
            return $this->input->escapeToken($token);
        }
        return preg_match('{^[\w-]+$}', $token) ? $token : escapeshellarg($token);
    }

    /**
     * 将 Symfony InputDefinition 转换为 ThinkPHP Definition
     */
    private function convertDefinition(SymfonyInputDefinition $symfonyDefinition): ThinkInputDefinition
    {
        $thinkDefinition = new ThinkInputDefinition();

        foreach ($symfonyDefinition->getArguments() as $argument) {
            $thinkDefinition->addArgument($this->convertArgument($argument));
        }

        foreach ($symfonyDefinition->getOptions() as $option) {
            $thinkDefinition->addOption($this->convertOption($option));
        }

        return $thinkDefinition;
    }

    private function convertArgument(SymfonyInputArgument $symfonyArg): ThinkInputArgument
    {
        $mode = ThinkInputArgument::OPTIONAL;
        if ($symfonyArg->isRequired()) {
            $mode = ThinkInputArgument::REQUIRED;
        }
        if ($symfonyArg->isArray()) {
            $mode |= ThinkInputArgument::IS_ARRAY;
        }

        return new ThinkInputArgument(
            $symfonyArg->getName(),
            $mode,
            $symfonyArg->getDescription() ?? '',
            $symfonyArg->getDefault()
        );
    }

    private function convertOption(SymfonyInputOption $symfonyOpt): ThinkInputOption
    {
        $mode = 0;
        if ($symfonyOpt->isValueRequired()) {
            $mode |= ThinkInputOption::VALUE_REQUIRED;
        }
        if ($symfonyOpt->isValueOptional()) {
            $mode |= ThinkInputOption::VALUE_OPTIONAL;
        }
        if ($symfonyOpt->isArray()) {
            $mode |= ThinkInputOption::VALUE_IS_ARRAY;
        }
        if ($mode === 0) {
            $mode = ThinkInputOption::VALUE_NONE;
        }

        $default = $mode === ThinkInputOption::VALUE_NONE ? null : $symfonyOpt->getDefault();

        return new ThinkInputOption(
            $symfonyOpt->getName(),
            $symfonyOpt->getShortcut(),
            $mode,
            $symfonyOpt->getDescription() ?? '',
            $default
        );
    }
}
