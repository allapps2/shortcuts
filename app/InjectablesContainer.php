<?php

namespace Shortcuts;

class InjectablesContainer
{
    private const INJECTABLES = [ConsoleServiceFactory::class, InputDTO::class];
    private const DEPENDENCY_METHOD = '__construct';

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

    function detectInjections(string $className): array
    {
        $params = [];
        if (!method_exists($className, self::DEPENDENCY_METHOD)) {
            return $params;
        }

        $refMethod = new \ReflectionMethod($className, self::DEPENDENCY_METHOD);
        foreach ($refMethod->getParameters() as $refParam) {
            $refType = $refParam->getType();
            if (!($refType instanceof \ReflectionNamedType) || $refType->isBuiltin()) {
                throw new \Exception(
                    'Expected class/interface type hint for every parameter in ' .
                    $className . '::' . self::DEPENDENCY_METHOD . '()'
                );
            }
            $paramClassName = $refType->getName();
            if (!in_array($paramClassName, self::INJECTABLES, true)) {
                throw new \Exception(
                    'Unknown service: ' . $paramClassName . ', available: ' .
                    implode(', ', self::INJECTABLES)
                );
            }
            $params[] = match ($paramClassName) {
                ConsoleServiceFactory::class => $this->getConsoleServiceFactory(),
                InputDTO::class => $this->dtoInput,
                default => throw new \Exception(
                    'Missing service creating for ' . $paramClassName
                ),
            };
        }

        return $params;
    }
}
