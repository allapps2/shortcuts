<?php

namespace Shortcuts;

readonly class InputDTO
{
    const ARG_PREFIX = '--';
    private const ARG_REGEX = '/^' . self::ARG_PREFIX . '([\w]+)(=?)(.*)$/';

    public array $namedArguments;
    public array $namedArgumentsForMe;

    function __construct(
        public ?string   $shortcut = null,
        array            $argumentsForMe = [],
        public array     $arguments = [],
        public ?IBuilder $builder = null
    ) {
        $this->namedArgumentsForMe = $this->_parseArguments($argumentsForMe);
        $this->namedArguments = $this->_parseArguments($this->arguments, escape: true);
    }

    private function _parseArguments(array $args, bool $escape = false): array
    {
        $parsedArgs = [];
        foreach ($args as $arg) {
            if (preg_match(self::ARG_REGEX, $arg, $matches)) {
                $name = $matches[1];
                $value = $matches[2] === '='
                    ? ($escape ? escapeshellarg($matches[3]) : $matches[3])
                    : true;
                if (isset($parsedArgs[$name])) {
                    if (!is_array($parsedArgs[$name])) {
                        $parsedArgs[$name] = [$parsedArgs[$name]];
                    }
                    $parsedArgs[$name][] = $value;
                } else {
                    $parsedArgs[$name] = $value;
                }
            }
        }

        return $parsedArgs;
    }
}
