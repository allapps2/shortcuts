<?php

namespace Shortcuts;

readonly class InputDTO
{
    const ARG_PREFIX = '--';
    private const ARG_REGEX = '/^' . self::ARG_PREFIX . '([\w]+)(=?)(.*)$/';

    public array $namedArguments;
    public array $namedArgumentsForMe;

    // arguments that don't match --name(=value) syntax, in the order they were passed
    public array $positionalArguments;

    function __construct(
        public ?string   $shortcut = null,
        array            $argumentsForMe = [],
        public array     $arguments = [],
        public ?IBuilder $builder = null
    ) {
        $this->namedArgumentsForMe = $this->_parseNamedArguments($argumentsForMe);
        $this->namedArguments = $this->_parseNamedArguments($this->arguments);
        $this->positionalArguments = array_values(array_filter(
            $this->arguments, fn(string $arg) => !preg_match(self::ARG_REGEX, $arg)
        ));
    }

    private function _parseNamedArguments(array $args): array
    {
        $parsedArgs = [];
        foreach ($args as $arg) {
            if (preg_match(self::ARG_REGEX, $arg, $matches)) {
                $name = $matches[1];
                $value = $matches[2] === '=' ? $matches[3] : true;
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
