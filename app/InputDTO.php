<?php

namespace Shortcuts;

use Shortcuts\ICommand\CommandWithArgs;

readonly class InputDTO
{
    public IBuilder $builder;

    function __construct(public ?string $shortcut = null, public array $arguments = [])
    {}

    function setBuilder(IBuilder $builder): void
    {
        $this->builder = $builder;
    }

    function parseAndEscapeArguments(): array
    {
        $argsEscaped = [];
        $regex = '/^' . CommandWithArgs::ARG_PREFIX . '([\w]+)(=?)(.*)$/';
        foreach ($this->arguments as $arg) {
            if (preg_match($regex, $arg, $matches)) {
                $name = $matches[1];
                $value = $matches[2] === '=' ? escapeshellarg($matches[3]) : true;
                if (isset($argsEscaped[$name])) {
                    if (!is_array($argsEscaped[$name])) {
                        $argsEscaped[$name] = [$argsEscaped[$name]];
                    }
                    $argsEscaped[$name][] = $value;
                } else {
                    $argsEscaped[$name] = $value;
                }
            }
        }

        return $argsEscaped;
    }
}
