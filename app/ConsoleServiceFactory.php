<?php

namespace Shortcuts;

readonly class ConsoleServiceFactory implements IInjectable
{
    private bool $forceEcho;

    function __construct(private InputDTO $dtoInput)
    {
        $this->forceEcho = isset($dtoInput->namedArgumentsForMe[App::ARG_VERBOSE]);
    }

    function create(array $env = []): ConsoleService
    {
        return new ConsoleService($env, $this->dtoInput, $this->forceEcho);
    }
}
