<?php

namespace Shortcuts\IEnvDTO;

use Shortcuts\IEnvDTO;

abstract class _EnvDTO implements IEnvDTO
{
    function asArray(): array
    {
        return array_filter(get_object_vars($this));
    }

    static function newFromParent(IEnvDTO $parent): static
    {
        $object = new static();
        foreach (get_object_vars($parent) as $prop => $value) {
            $object->{$prop} = $value;
        }

        return $object;
    }
}
