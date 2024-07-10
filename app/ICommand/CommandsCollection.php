<?php

namespace Shortcuts\ICommand;

use Closure;
use Shortcuts\ICommand;
use Shortcuts\ICommand\CallbackWithArgs\ArgDefinitionsCollection;

class CommandsCollection
{
    private array $items = [];
    private string $description = '';
    private array $env = [];
    private string $workingDir;

    function add(string $command, bool $echoCommand = false): static
    {
        $this->items[] = new CommandWithoutArgs($command, $echoCommand);

        return $this;
    }

    function removeAll(): static
    {
        $this->items = [];

        return $this;
    }

    /**
     * @return ICommand[]
     */
    function walk(): \Generator
    {
        foreach ($this->items as $command) {
            yield $command;
        }
    }

    function getArguments(): ArgDefinitionsCollection
    {
        $arguments = new ArgDefinitionsCollection();

        foreach ($this->items as $command) {
            if ($command instanceof CallbackWithArgs) {
                $arguments->mergeWithCollection($command->detectArguments());
            }
        }

        return $arguments;
    }

    function removeByKey(int $key): static
    {
        unset($this->items[$key]);

        return $this;
    }

    function setDescription(string $description): static
    {
        $this->description = $description;

        return $this;
    }

    function getDescription(): string
    {
        return $this->description;
    }

    function addEnv(array $env): static
    {
        $this->env = array_merge($this->env, $env);

        return $this;
    }

    function getEnv(): array
    {
        return $this->env;
    }

    function addCallback(Closure $closure): static
    {
        $this->items[] = new CallbackWithArgs($closure);

        return $this;
    }

    function addEcho(string $str): static
    {
        $this->items[] = new CommandWithoutArgs("echo \"{$str}\"", echoCommand: false);

        return $this;
    }

    function changeWorkingDir(string $dir): static
    {
        $this->items[] = new WorkingDir($dir);

        return $this;
    }

    function getWorkingDir(): ?string
    {
        return $this->workingDir ?? null;
    }
}
