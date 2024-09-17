<?php

namespace Shortcuts;

use ReflectionClass;
use ReflectionMethod;
use ReflectionObject;
use Shortcuts\Command\CommandsCollection;
use Shortcuts\Shortcut\ShortcutDefinitionCollection;
use Shortcuts\Shortcut\ShortcutDefinitionDTO;

abstract class ShortcutsCollection
{
    private ShortcutDefinitionCollection $availableShortcuts;

    function __construct(protected InjectablesContainer $di) {}

    function _onShortcutDefinitionCreate(ShortcutDefinitionDTO $dtoShortcut): void {}

    function getAvailableShortcuts(): ShortcutDefinitionCollection
    {
        if (!isset($this->availableShortcuts)) {
            $this->availableShortcuts = new ShortcutDefinitionCollection();

            $refThis = new ReflectionObject($this);
            $methods = $refThis->getMethods(ReflectionMethod::IS_PUBLIC);
            $forbiddenShortcutNames = array_map(
                fn(ReflectionMethod $refMethod) => $refMethod->getName(),
                (new ReflectionClass(self::class))->getMethods()
            );
            foreach ($methods as $refMethod) {
                $shortcut = $refMethod->getName();
                if (
                    $shortcut[0] === '_' ||
                    in_array($shortcut, $forbiddenShortcutNames, true)
                ) {
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
                        "All public methods of {$className} not starting with _ " .
                        "are shortcuts and must return " . CommandsCollection::class .
                        ", please fix {$shortcut}()"
                    );
                }

                $dtoShortcut = new ShortcutDefinitionDTO($refMethod);
                if ($attrs = $refMethod->getAttributes(Shortcut::class)) {
                    /** @var Shortcut $attrDef */
                    $attrDef = $attrs[0]->newInstance();
                    $dtoShortcut->setDescription($attrDef->description);
                }
                $this->_onShortcutDefinitionCreate($dtoShortcut);
                $this->availableShortcuts->add($dtoShortcut);
            }

            $this->availableShortcuts->sort();
        }

        return $this->availableShortcuts;
    }
}
