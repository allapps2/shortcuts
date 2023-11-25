<?php

namespace Shortcuts\CommandDTO;

use ArrayIterator;
use IteratorAggregate;
use Shortcuts\CommandDTO;
use Traversable;

class CommandsCollection implements IteratorAggregate
{
    private array $items = [];

    function add(CommandDTO $dto): static
    {
        $this->items[] = $dto;

        return $this;
    }

    /**
     * @return CommandDTO[]
     */
    function getIterator(): Traversable
    {
        return new ArrayIterator($this->items);
    }
}
