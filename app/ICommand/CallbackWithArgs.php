<?php

namespace Shortcuts\ICommand;

use Shortcuts\ICommand;
use Shortcuts\ICommand\CallbackWithArgs\ArgDefinition;
use Shortcuts\ICommand\CallbackWithArgs\ArgDefinitionDTO;
use Shortcuts\ICommand\CallbackWithArgs\ArgDefinitionsCollection;
use Shortcuts\ShortcutsCollection;
use Shortcuts\UserFriendlyException;

readonly class CallbackWithArgs implements ICommand
{
    const ARG_PREFIX = '--';

    function __construct(private \Closure $composer)
    {
        $refFunc = new \ReflectionFunction($composer);
        if (
            !$refFunc->getReturnType() ||
            $refFunc->getReturnType()->getName() !== CommandsCollection::class ||
            $refFunc->getReturnType()->allowsNull() === false
        ) {
            throw new \Exception(
                'Missing return type for callback, that must be ?' .
                CommandsCollection::class . ' ("?" is required) ' .
                ' in ' . $refFunc->getFileName() . ':' . $refFunc->getStartLine()
            );
        }
    }

    function compose(array $argumentsEscaped, ShortcutsCollection $thisForCallback): string
    {
        $args = $this->_populateArgsWithValues($argumentsEscaped);

        $cmds = [];
        $commands = $this->composer->call($thisForCallback, ...$args);
        if ($commands instanceof CommandsCollection) {
            foreach ($commands->walk() as $command) {
                $cmds[] = $command->compose($argumentsEscaped, $thisForCallback);
            }
        }

        return implode("\n", $cmds);
    }

    function isEchoRequired(): bool
    {
        // "echo" makes no sense for callback, it is used for commands only
        return false;
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
            if ($param->isVariadic()) {
                $dtoArg = new ArgDefinitionDTO('', ArgDefinitionDTO::TYPE_VARIADIC);
            } else {
                if (!$param->getType()) {
                    $refFunc = (new \ReflectionFunction($this->composer));
                    throw new \Exception(
                        "Missing type hint for parameter '{$param->getName()}' in " . (
                            $refFunc->getFileName() . ':' . $refFunc->getStartLine()
                        )
                    );
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
                        throw new \Exception(
                            sprintf($boolException, self::ARG_PREFIX . $paramName)
                        );
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
                        throw new \Exception(
                            sprintf($boolException, self::ARG_PREFIX . $paramName)
                        );
                    }
                }
            }

            if ($attrs = $param->getAttributes(ArgDefinition::class)) {
                /** @var ArgDefinition $attrDef */
                $attrDef = $attrs[0]->newInstance();
                $dtoArg->setDescription($attrDef->description);
            }

            $args->add($dtoArg);
        }

        return $args;
    }
}
