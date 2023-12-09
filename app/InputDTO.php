<?php

namespace Shortcuts;

class InputDTO
{
    public IConfig $config;
    public ?string $shortcut = null;
    public array $arguments = [];
}
