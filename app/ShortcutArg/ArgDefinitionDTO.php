<?php

namespace Shortcuts\ShortcutArg;

class ArgDefinitionDTO
{
    const TYPE_VARIADIC = 'variadic';

    public readonly string|bool|array|null $defaultValue;
    public readonly string $description;
    private bool $hasDefaultValue = false;

    function __construct(readonly public string $name, readonly public string $type)
    {
        if (!in_array($type, ['string', 'bool', 'array', self::TYPE_VARIADIC])) {
            throw new \Exception("Unsupported type '{$type}' for argument '{$this->name}'");
        }
    }

    function setDefaultValue(string|bool|array|null $value): void
    {
        $this->defaultValue = $value;
        $this->hasDefaultValue = true;
    }

    function hasDefaultValue(): bool
    {
        return $this->hasDefaultValue;
    }

    function setDescription(string $description): void
    {
        $this->description = $description;
    }
}
