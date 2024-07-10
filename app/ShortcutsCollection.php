<?php

namespace Shortcuts;

use ArrayIterator;
use IteratorAggregate;
use ReflectionClass;
use ReflectionMethod;
use ReflectionObject;
use Shortcuts\ICommand\CommandsCollection;
use Traversable;

abstract class ShortcutsCollection implements IteratorAggregate
{
    private ArrayIterator $items;

    function init(InjectablesContainer $di): void {}

    /**
     * @return CommandsCollection[]
     */
    function getIterator(): Traversable
    {
        if (!isset($this->items)) {
            $items = [];

            $refThis = new ReflectionObject($this);
            $methods = $refThis->getMethods(ReflectionMethod::IS_PUBLIC);
            $forbidden = array_map(
                fn(ReflectionMethod $refMethod) => $refMethod->getName(),
                (new ReflectionClass(self::class))->getMethods()
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

                $items[$shortcut] = $refMethod->invoke($this);
            }

            ksort($items);
            $this->items = new ArrayIterator($items);
        }

        return $this->items;
    }
}
