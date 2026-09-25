<?php

namespace Shortcuts\ShortcutArg;

use BackedEnum;

class ArgDefinitionDTO
{
    const TYPE_VARIADIC = 'variadic';
    const TYPE_ENUM = 'enum';

    public readonly string|int|float|bool|array|null $defaultValue;
    public readonly string $description;
    private bool $hasDefaultValue = false;
    private string $enumClass;
    private bool $isEscapeRequired = true;

    function __construct(readonly public string $name, readonly public string $type)
    {
        if (!in_array(
            $type,
            ['string', 'int', 'float', 'bool', 'array', self::TYPE_VARIADIC, self::TYPE_ENUM]
        )) {
            throw new \Exception("Unsupported type '{$type}' for argument '{$this->name}'");
        }
    }

    function setDefaultValue(string|int|float|bool|array|null $value): void
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

    function disableEscaping(): void
    {
        $this->isEscapeRequired = false;
    }

    function isEscapeRequired(): bool
    {
        return $this->isEscapeRequired;
    }

    /**
     * @param class-string<BackedEnum> $enumClass
     */
    function setEnumClass(string $enumClass): void
    {
        if ($this->type !== self::TYPE_ENUM) {
            throw new \Exception(
                "Cannot set enum for argument '{$this->name}' of type '{$this->type}'"
            );
        }
        if (!is_a($enumClass, BackedEnum::class, true)) {
            throw new \Exception(
                "Argument '{$this->name}' ({$enumClass}) must be a backed enum " .
                "(implements " . BackedEnum::class . "), plain enums are not supported"
            );
        }
        $this->enumClass = $enumClass;
    }

    /**
     * @return class-string<BackedEnum>|null
     */
    function getEnumClass(): ?string
    {
        return $this->enumClass ?? null;
    }
}
