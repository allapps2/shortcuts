<?php

namespace Shortcuts\Command;

use Shortcuts\Command;

class CommandsCollection
{
    private array $items = [];

    function add(string $command, bool $echoCommand = false): static
    {
        $this->items[] = new Command($command, $echoCommand);

        return $this;
    }

    function addCommand(Command $command): static
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
     * @return Command[]
     */
    function asArray(): array
    {
        return $this->items;
    }

    function removeByKey(int $key): static
    {
        unset($this->items[$key]);

        return $this;
    }

    function addEcho(string $str): static
    {
        $this->items[] = new Command("echo \"{$str}\"", echoCommand: false);

        return $this;
    }

    function addCD(string $dir): static
    {
        $this->items[] = new Command("cd \"{$dir}\"", echoCommand: false);

        return $this;
    }
}
