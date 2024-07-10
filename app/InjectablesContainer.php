<?php

namespace Shortcuts;

class InjectablesContainer
{
    const INJECTABLES = [ConsoleServiceFactory::class];

    private readonly InputDTO $dtoInput;

    function setInput(InputDTO $dtoInput): void
    {
        $this->dtoInput = $dtoInput;
    }

    function getConsoleServiceFactory(): ConsoleServiceFactory
    {
        static $o;
        return $o ?? $o = new ConsoleServiceFactory($this->dtoInput);
    }

    function injectInto(object $object): void
    {
        $params = [];
        $methodName = 'injects';
        $refMethod = new \ReflectionMethod($object, $methodName);
        foreach ($refMethod->getParameters() as $refParam) {
            $refType = $refParam->getType();
            if (!($refType instanceof \ReflectionNamedType) || $refType->isBuiltin()) {
                throw new \Exception(
                    'Expected class/interface type hint for every parameter in ' .
                    get_class($object) . '::' . $methodName
                );
            }
            $className = $refType->getName();
            if (!in_array($className, self::INJECTABLES, true)) {
                throw new \Exception(
                    'Unknown service: ' . $className . ', available: ' .
                    implode(', ', self::INJECTABLES)
                );
            }
            $params[] = match ($className) {
                ConsoleServiceFactory::class => $this->getConsoleServiceFactory(),
                default => throw new \Exception(
                    'Missing service creating for ' . $className
                ),
            };
        }

        $object->{$methodName}(...$params);
    }
}
