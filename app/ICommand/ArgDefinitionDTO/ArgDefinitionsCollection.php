<?php

namespace Shortcuts\ICommand\ArgDefinitionDTO;

use ArrayIterator;
use IteratorAggregate;
use Shortcuts\ICommand\ArgDefinitionDTO;
use Traversable;

class ArgDefinitionsCollection implements IteratorAggregate
{
    private array $items = [];

    function add(ArgDefinitionDTO $dto): void
    {
        $this->items[$dto->name] = $dto;
    }

    /**
     * @return ArgDefinitionDTO[]
     */
    function getIterator(): Traversable
    {
        ksort($this->items);

        return new ArrayIterator($this->items);
    }

    function mergeWithCollection(self $collection): void
    {
        $this->items = array_merge($this->items, $collection->items);
    }
}
