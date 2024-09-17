<?php

namespace Shortcuts\Shortcut;

class ShortcutDefinitionCollection
{
    private array $items = [];

    function add(ShortcutDefinitionDTO $dto): void
    {
        $this->items[$dto->name] = $dto;
    }

    function sort(): void
    {
        ksort($this->items);
    }

    function getByName(string $name): ?ShortcutDefinitionDTO
    {
        return $this->items[$name] ?? null;
    }

    /**
     * @return ShortcutDefinitionDTO[]
     */
    function walk(): \Generator
    {
        foreach ($this->items as $dto) {
            yield $dto;
        }
    }

    function count(): int
    {
        return count($this->items);
    }
}
