<?php

namespace Shortcuts;

readonly class ConsoleServiceFactory implements IInjectable
{
    private bool $forceEcho;

    function __construct(InputDTO $dtoInput)
    {
        $this->forceEcho = isset($dtoInput->namedArguments[App::ARG_VERBOSE]);
    }

    function create(array $env = []): ConsoleService
    {
        return new ConsoleService($env, $this->forceEcho);
    }
}
