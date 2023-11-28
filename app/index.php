<?php

namespace Shortcuts;

initClassAutoloader();

(new App)->handle($argv);

function initClassAutoloader(): void
{
    spl_autoload_register(function(string $className) {
        include(
            __DIR__ . '/' . implode('/', array_slice(explode('\\', $className), 1)) . '.php'
        );
    });
}
