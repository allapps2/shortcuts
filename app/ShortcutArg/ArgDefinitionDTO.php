<?php

namespace Shortcuts\ShortcutArg;

use BackedEnum;

class ArgDefinitionDTO
{
    const TYPE_VARIADIC = 'variadic';
    const TYPE_ENUM = 'enum';

    public readonly string|bool|array|null $defaultValue;
    public readonly string $description;
    private bool $hasDefaultValue = false;
    private string $enumClass;

    function __construct(readonly public string $name, readonly public string $type)
    {
        if (!in_array(
            $type, ['string', 'bool', 'array', self::TYPE_VARIADIC, self::TYPE_ENUM]
        )) {
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
