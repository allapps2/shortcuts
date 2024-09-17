<?php

namespace Shortcuts;

readonly class InputDTO
{
    const ARG_PREFIX = '--';

    public array $namedArguments;

    function __construct(
        public ?string   $shortcut = null,
        public array     $arguments = [],
        public ?IBuilder $builder = null
    ) {
        $this->namedArguments = $this->_parseAndEscapeArguments();
    }

    private function _parseAndEscapeArguments(): array
    {
        $argsEscaped = [];
        $regex = '/^' . self::ARG_PREFIX . '([\w]+)(=?)(.*)$/';
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
