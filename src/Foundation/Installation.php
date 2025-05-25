<?php

namespace Dybasedev\LunaPrototype\Foundation;

use Illuminate\Console\OutputStyle;
use Symfony\Component\Console\Output\OutputInterface;

abstract class Installation
{
    protected ?OutputStyle $output = null;

    protected int $verbosity = OutputInterface::VERBOSITY_NORMAL;

    protected array $verbosityMap = [
        'v' => OutputInterface::VERBOSITY_VERBOSE,
        'vv' => OutputInterface::VERBOSITY_VERY_VERBOSE,
        'vvv' => OutputInterface::VERBOSITY_DEBUG,
        'quiet' => OutputInterface::VERBOSITY_QUIET,
        'normal' => OutputInterface::VERBOSITY_NORMAL,
    ];

    public function withOutput(OutputStyle $output): static
    {
        $this->output = $output;
        return $this;
    }

    protected function parseVerbosity($level = null)
    {
        if (isset($this->verbosityMap[$level])) {
            $level = $this->verbosityMap[$level];
        } elseif (! is_int($level)) {
            $level = $this->verbosity;
        }

        return $level;
    }

    public function writeln(string $string, int|string|null $verbosity = null): void
    {
        $this->output?->writeln($string, $this->parseVerbosity($verbosity));
    }

    /**
     * 安装逻辑
     *
     * 注意，安装逻辑里不要包括 DDL 操作，会导致事务失效
     *
     * @return void
     */
    abstract public function install(): void;
}