<?php

namespace Shortcuts;

use Shortcuts\ShortcutDTO\ShortcutsCollection;

class App
{
    function handle(array $argv): void
    {
        $dtoInput = $this->parseInput($argv);

        if (!$dtoInput || !isset($dtoInput->shortcut)) {
            $this->echoLn('Usage: '. basename($argv[0]) . ' [<shortcut>] [<arguments>]');
            if (!$dtoInput) {
                return;
            }
        }

        $shortcuts = $this->buildShortcutsCollection($dtoInput);

        if (!isset($dtoInput->shortcut)) {
            $this->echoShortcuts($shortcuts);
            return;
        }

        $dtoShortcut = $shortcuts->get($dtoInput->shortcut);
        if (!$dtoShortcut) {
            $this->echoLn("unknown shortcut '{$dtoInput->shortcut}'");
            $this->echoShortcuts($shortcuts);
            return;
        }

        $this->handleShortcut($dtoShortcut, $shortcuts->getEnv());
    }

    private function echoShortcuts(ShortcutsCollection $shortcuts): void
    {
        $this->echoLn('available shortcuts:');
        $prefix = '  ';
        $len = $shortcuts->getShortcutMaxLen() + 1;
        foreach ($shortcuts as $dtoShortcut) {
            if ($dtoShortcut->description) {
                $this->echoLn(
                    $prefix .
                    str_pad($dtoShortcut->shortcut, $len) .
                    '- ' . $dtoShortcut->description
                );
            } else {
                $this->echoLn($prefix . $dtoShortcut->shortcut);
            }
        }

        $this->echoLn('environment variables:');
        $env = $shortcuts->getEnv()->asArray();
        ksort($env);
        foreach ($env as $name => $value) {
            $this->echoLn($prefix . "{$name} = {$value}");
        }
    }

    private function buildShortcutsCollection(InputDTO $dtoInput): ShortcutsCollection
    {
        $defaultBuilder = $dtoInput->config->getDefaultShortcutsBuilder();
        $shortcuts = $defaultBuilder->getShortcuts($dtoInput->arguments);
        $dtoEnv = $defaultBuilder->getEnv() ?: new EnvEmptyDTO();
        if ($localBuilder = $dtoInput->config->getLocalShortcutsBuilder()) {
            $localBuilder->updateShortcuts($shortcuts);
            $dtoEnv = $localBuilder->updateEnv($dtoEnv);
        }
        $shortcuts->setEnv($dtoEnv);

        $dtoInput->config->onBuildComplete($shortcuts);

        return $shortcuts;
    }

    private function parseInput(array $argv): ?InputDTO
    {
        $dto = new InputDTO();

        $configFile = getcwd() . '/' . IConfig::CONFIG_FILE;
        if (!is_file($configFile)) {
            $this->echoLn('not found ' . $configFile);
            return null;
        }

        $config = @require($configFile);
        if (!$config instanceof IConfig) {
            $this->echoLn('must return instance of ' . IConfig::class . ': ' . $configFile);
            return null;
        }
        $dto->config = $config;

        if ($shortcut = trim($argv[1] ?? '')) {
            $dto->shortcut = $shortcut;
        }

        $dto->arguments = array_slice($argv, 2);

        return $dto;
    }

    private function handleShortcut(ShortcutDTO $dtoShortcut, IEnvDTO $dtoEnv): void
    {
        foreach ($dtoShortcut->commands as $dtoCommand) {
            if (!$this->execCommand($dtoCommand, $dtoEnv)) {
                break;
            }
        }
    }

    private function echoLn(string $msg): void
    {
        echo $msg . "\n";
    }

    private function execCommand(CommandDTO $dtoCommand, IEnvDTO $dtoEnv): bool
    {
        if ($dtoCommand->echoCommand) {
            $this->echoLn($dtoCommand->command);
        }

        $process = proc_open(
            $dtoCommand->command,
            [1 => STDOUT, 2 => STDERR],
            $pipes,
            env_vars: $dtoEnv->asArray()
        );
        if (!is_resource($process)) {
            $this->echoLn("failed to execute: {$dtoCommand->command}");
            return false;
        }
        while(proc_get_status($process)['running']) {
            sleep(1);
        }
        proc_close($process);

        return true;
    }
}
