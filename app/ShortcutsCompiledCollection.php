<?php

namespace Shortcuts;

use ReflectionClass;
use ReflectionMethod;
use ReflectionObject;
use Shortcuts\ICommand\CommandsCollection;

readonly class ShortcutsCompiledCollection
{
    private array $items;

    private function __construct() {}

    static function compile(ShortcutsCollection $shortcuts): self
    {
        $o = new self();

        $items = [];

        $refThis = new ReflectionObject($shortcuts);
        $methods = $refThis->getMethods(ReflectionMethod::IS_PUBLIC);
        $forbidden = array_map(
            fn(ReflectionMethod $refMethod) => $refMethod->getName(),
            (new ReflectionClass(ShortcutsCollection::class))->getMethods()
        );
        $forbidden[] = '__construct';
        foreach ($methods as $refMethod) {
            $shortcut = $refMethod->getName();
            if (in_array($shortcut, $forbidden, true)) {
                continue;
            }

            if (
                !$refMethod->getReturnType() ||
                $refMethod->getReturnType()->getName() !== CommandsCollection::class
            ) {
                $className = $refThis->isAnonymous()
                    ? $refThis->getFileName()
                    : $refThis->getName();
                throw new \Exception(
                    "All public methods of {$className} are shortcuts " .
                    "and must return " . CommandsCollection::class .
                    ", please fix {$shortcut}()"
                );
            }

            $items[$shortcut] = $refMethod->invoke($shortcuts);
        }

        ksort($items);
        $o->items = $items;

        return $o;
    }

    /**
     * @return CommandsCollection[]
     */
    function walk(): \Generator
    {
        foreach ($this->items as $shortcut => $commands) {
            yield $shortcut => $commands;
        }
    }

    function count(): int
    {
        return count($this->items);
    }

    function getCommands(string $shortcut): ?CommandsCollection
    {
        return $this->items[$shortcut] ?? null;
    }
}
