<?php

namespace Shortcuts\ICommand\CommandWithArgs;

class ArgDefinitionDTO
{
    public readonly string|bool|array|null $defaultValue;
    private bool $hasDefaultValue = false;

    function __construct(readonly public string $name, readonly public string $type) {}

    function setDefaultValue(string|bool|array|null $value): void
    {
        $this->defaultValue = $value;
        $this->hasDefaultValue = true;
    }

    function hasDefaultValue(): bool
    {
        return $this->hasDefaultValue;
    }
}
