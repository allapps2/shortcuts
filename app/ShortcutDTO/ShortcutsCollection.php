<?php

namespace Shortcuts\ShortcutDTO;

use ArrayIterator;
use IteratorAggregate;
use Shortcuts\IEnvDTO;
use Shortcuts\ShortcutDTO;
use Traversable;

class ShortcutsCollection implements IteratorAggregate
{
    private array $items = [];
    private IEnvDTO $dtoEnv;
    private int $maxLen = 0;

    function add(ShortcutDTO $dto): static
    {
        if (isset($this->items[$dto->shortcut])) {
            throw new \Exception("already exists '{$dto->shortcut}");
        }

        $this->items[$dto->shortcut] = $dto;
        $this->maxLen = max($this->maxLen, strlen($dto->shortcut));

        return $this;
    }

    function get(string $shortcut): ?ShortcutDTO
    {
        return $this->items[$shortcut] ?? null;
    }

    function setEnv(IEnvDTO $dtoEnv): void
    {
        $this->dtoEnv = $dtoEnv;
    }

    function getEnv(): IEnvDTO
    {
        return $this->dtoEnv;
    }

    /**
     * @return ShortcutDTO[]
     */
    function getIterator(): Traversable
    {
        ksort($this->items);

        return new ArrayIterator($this->items);
    }

    function getShortcutMaxLen(): int
    {
        return $this->maxLen;
    }
}
