<?php

namespace Shortcuts\ICommand;

use ArrayIterator;
use IteratorAggregate;
use Shortcuts\ICommand;
use Shortcuts\ICommand\ArgDefinitionDTO\ArgDefinitionsCollection;
use Traversable;

class CommandsCollection implements IteratorAggregate
{
    private array $items = [];

    function add(ICommand $command): static
    {
        $this->items[] = $command;

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
}
