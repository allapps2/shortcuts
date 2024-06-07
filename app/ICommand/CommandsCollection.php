<?php

namespace Shortcuts\ICommand;

use ArrayIterator;
use Closure;
use IteratorAggregate;
use Shortcuts\ICommand;
use Shortcuts\ICommand\CommandWithArgs\ArgDefinitionsCollection;
use Shortcuts\IEnvDTO;
use Traversable;

class CommandsCollection implements IteratorAggregate
{
    private array $items = [];
    private string $description = '';
    private Closure $onBefore;

    /** @var IEnvDTO[] */
    private array $envs = [];

    function add(ICommand|string $command): static
    {
        $this->items[] = is_string($command) ? new CommandWithoutArgs($command) : $command;

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
    function getIterator(): Traversable
    {
        return new ArrayIterator($this->items);
    }

    function getArguments(): ArgDefinitionsCollection
    {
        $arguments = new ArgDefinitionsCollection();

        foreach ($this->items as $command) {
            if ($command instanceof CommandWithArgs) {
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

    function addEnv(IEnvDTO $dto): static
    {
        $this->envs[] = $dto;

        return $this;
    }

    function getEnv(): array
    {
        $env = [];
        foreach ($this->envs as $dto) {
            $env = array_merge($env, $dto->asArray());
        }

        return $env;
    }

    function onBefore(Closure $closure): static
    {
        $this->onBefore = $closure;

        return $this;
    }

    function getOnBefore(): ?Closure
    {
        return $this->onBefore ?? null;
    }
}
