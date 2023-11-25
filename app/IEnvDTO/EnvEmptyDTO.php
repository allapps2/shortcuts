<?php

namespace Shortcuts;

use Shortcuts\IEnvDTO;

class EnvEmptyDTO implements IEnvDTO
{
    function asArray(): array
    {
        return [];
    }
}
