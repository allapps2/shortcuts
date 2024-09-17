<?php

namespace Shortcuts\Shortcut;

use ReflectionMethod;
use Shortcuts\InputDTO;
use Shortcuts\ShortcutArg;
use Shortcuts\ShortcutArg\ArgDefinitionDTO;
use Shortcuts\ShortcutArg\ArgDefinitionsCollection;

class ShortcutDefinitionDTO
{
    public readonly string $name;
    public readonly string $description;
    private readonly ArgDefinitionsCollection $args;
    private array $env = [];

    function __construct(public readonly ReflectionMethod $refMethod)
    {
        $this->name = $refMethod->getName();
    }

    function setDescription(string $description): void
    {
        $this->description = $description;
    }

    function addEnv(array $env): void
    {
        $this->env = array_merge($this->env, $env);
    }

    function getEnv(): array
    {
        return $this->env;
    }

    function getArguments(): ArgDefinitionsCollection
    {
        if (!isset($this->args)) {
            $this->args = new ArgDefinitionsCollection();

            $params = $this->refMethod->getParameters();
            $supportedTypes = ['string', 'bool', 'array'];

            $boolException = "Fix definition of %s argument, bool arguments " .
                "must have default value FALSE, because it is used as optional flag";

            foreach ($params as $param) {
                if ($param->isVariadic()) {
                    $dtoArg = new ArgDefinitionDTO('', ArgDefinitionDTO::TYPE_VARIADIC);
                } else {
                    if (!$param->getType()) {
                        throw new \Exception(
                            "Missing type hint for parameter '{$param->getName()}' in " . (
                                $this->refMethod->getFileName() . ':' .
                                $this->refMethod->getStartLine()
                            )
                        );
                    }

                    $paramName = $param->getName();
                    $typeName = $param->getType()->getName();
                    if (!in_array($typeName, $supportedTypes, true)) {
                        throw new \Exception(
                            "Unsupported type {$typeName} for argument " .
                            InputDTO::ARG_PREFIX . "{$paramName}, supported types: " .
                            "string (" . InputDTO::ARG_PREFIX . "{$paramName}=<value>), " .
                            "bool (optional flag, " . InputDTO::ARG_PREFIX . "{$paramName}), " .
                            "array (" . InputDTO::ARG_PREFIX . "{$paramName}=<value1> " .
                            InputDTO::ARG_PREFIX . "{$paramName}=<value2> ...)"
                        );
                    }

                    $dtoArg = new ArgDefinitionDTO($paramName, $typeName);
                    if ($param->isDefaultValueAvailable()) {
                        $default = $param->getDefaultValue();
                        if ($typeName === 'bool' && $default !== false) {
                            throw new \Exception(
                                sprintf($boolException, InputDTO::ARG_PREFIX . $paramName)
                            );
                        }
                        if ($typeName === 'array' && $default !== []) {
                            throw new \Exception(sprintf(
                                "Fix definition of %s argument, array arguments " .
                                "can have only [] as default value",
                                InputDTO::ARG_PREFIX . $paramName
                            ));
                        }
                        $dtoArg->setDefaultValue($default);
                    } else {
                        if ($typeName === 'bool') {
                            // just for readability always require default value for
                            // bool, so developer can see that it's optional
                            throw new \Exception(
                                sprintf($boolException, InputDTO::ARG_PREFIX . $paramName)
                            );
                        }
                    }
                }

                if ($attrs = $param->getAttributes(ShortcutArg::class)) {
                    /** @var ShortcutArg $attrDef */
                    $attrDef = $attrs[0]->newInstance();
                    $dtoArg->setDescription($attrDef->description);
                }

                $this->args->add($dtoArg);
            }
        }

        return $this->args;
    }
}
