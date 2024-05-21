<?php

namespace Shortcuts\ICommand;

use Shortcuts\ICommand;
use Shortcuts\ICommand\CommandWithArgs\ArgDefinitionDTO;
use Shortcuts\ICommand\CommandWithArgs\ArgDefinitionsCollection;
use Shortcuts\UserFriendlyException;

readonly class CommandWithArgs implements ICommand
{
    const ARG_PREFIX = '--';

    function __construct(private \Closure $composer, private bool $echoCommand = true) {}

    function compose(array $argumentsEscaped): string
    {
        $args = $this->_populateArgsWithValues($argumentsEscaped);

        // cannot use $this->composer->call($this) to avoid $this change, because
        // it could be set via bindTo()
        $func = $this->composer;
        return $func(...$args);
    }

    function isEchoRequired(): bool
    {
        return $this->echoCommand;
    }

    private function _populateArgsWithValues(array $inputArguments): array
    {
        $values = [];

        foreach ($this->detectArguments() as $dtoArg) {
            if (array_key_exists($dtoArg->name, $inputArguments)) {
                $value = $inputArguments[$dtoArg->name];
                $type = gettype($value);
                if ($type === 'boolean') {
                    $type = 'bool';
                }
                if ($type !== $dtoArg->type) {
                    if ($dtoArg->type === 'array' && $type === 'string') {
                        $value = [$value];
                    } elseif($type === 'bool') { // argument was specified, but as flag, without value
                        throw new UserFriendlyException(
                            'Missing value in ' . self::ARG_PREFIX . $dtoArg->name
                        );
                    } else {
                        throw new UserFriendlyException(sprintf(
                            'Invalid value type in %s, expected %s, got %s',
                            self::ARG_PREFIX . $dtoArg->name,
                            $dtoArg->type,
                            $type
                        ));
                    }
                }
                $values[$dtoArg->name] = $value;
            } else {
                if (!$dtoArg->hasDefaultValue()) {
                    throw new UserFriendlyException(
                        'Missing required argument ' . self::ARG_PREFIX . $dtoArg->name
                    );
                }
                $values[$dtoArg->name] = $dtoArg->defaultValue;
            }
        }

        return $values;
    }

    function detectArguments(): ArgDefinitionsCollection
    {
        $args = new ArgDefinitionsCollection();

        $refFunc = new \ReflectionFunction($this->composer);
        $params = $refFunc->getParameters();
        $supportedTypes = ['string', 'bool', 'array'];

        $boolException = "Fix definition of %s argument, bool arguments " .
            "must have default value FALSE, because it is used as optional flag";

        foreach ($params as $param) {
            if (!$param->getType()) {
                throw new \Exception("Missing type hint for parameter " . $param->getName());
            }

            $paramName = $param->getName();
            $typeName = $param->getType()->getName();
            if (!in_array($typeName, $supportedTypes, true)) {
                throw new \Exception(
                    "Unsupported type {$typeName} for argument " .
                    self::ARG_PREFIX . "{$paramName}, supported types: " .
                    "string (" . self::ARG_PREFIX . "{$paramName}=<value>), " .
                    "bool (optional flag, " . self::ARG_PREFIX . "{$paramName}), " .
                    "array (" . self::ARG_PREFIX . "{$paramName}=<value1> " .
                        self::ARG_PREFIX . "{$paramName}=<value2> ...)"
                );
            }

            $dtoArg = new ArgDefinitionDTO($paramName, $typeName);
            if ($param->isDefaultValueAvailable()) {
                $default = $param->getDefaultValue();
                if ($typeName === 'bool' && $default !== false) {
                    throw new \Exception(sprintf($boolException, self::ARG_PREFIX . $paramName));
                }
                if ($typeName === 'array' && $default !== []) {
                    throw new \Exception(sprintf(
                        "Fix definition of %s argument, array arguments " .
                        "can have only [] as default value",
                        self::ARG_PREFIX . $paramName
                    ));
                }
                $dtoArg->setDefaultValue($default);
            } else {
                if ($typeName === 'bool') {
                    // just for readability always require default value for bool, so
                    // developer can see that it's optional
                    throw new \Exception(sprintf($boolException, self::ARG_PREFIX . $paramName));
                }
            }

            $args->add($dtoArg);
        }

        return $args;
    }
}
