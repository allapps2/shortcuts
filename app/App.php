<?php

namespace Shortcuts;

use Shortcuts\ShortcutDTO\ShortcutsCollection;

class App
{
    const APP_SHORTCUT_PHAR = 'compile-shortcuts-phar';
    const APP_SHORTCUT_SETUP = 'setup-shortcuts-global';

    function handle(array $argv): void
    {
        define('ROOT_DIR', dirname(__DIR__));

        $dtoInput = $this->parseInput($argv);

        if (!$dtoInput || is_null($dtoInput->shortcut)) {
            $this->echoLn('Usage: '. basename($argv[0]) . ' [<shortcut>] [<arguments>]');
            if (!$dtoInput) {
                return;
            }
        }

        switch ($dtoInput->shortcut) {
            case self::APP_SHORTCUT_PHAR:
                (new PharCompiler($this))->compile();
                return;
            case self::APP_SHORTCUT_SETUP:
                $this->setupGlobal();
                return;
        }

        $shortcuts = $this->buildShortcutsCollection($dtoInput);

        if (!$dtoInput->shortcut) {
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

    private function setupGlobal(): void
    {
        $dstFile = '/usr/local/bin/short';
        $fileContent = sprintf(
            "#!%s\n<?php\nrequire('%s');\n",
            PHP_BINARY,
            PharCompiler::getPharFilePath()
        );
        $writtenBytes = @file_put_contents($dstFile, $fileContent);
        if ($writtenBytes !== strlen($fileContent)) {
            $this->echoLn('Error writing to ' . $dstFile);
        } else {
            chmod($dstFile, fileperms($dstFile) | 0111); // +x
            $this->echoLn('Now you can use "short" in directory with shortcuts.php');
        }
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

        if ($shortcut = trim($argv[1] ?? '')) {
            $dto->shortcut = $shortcut;
            $dto->arguments = array_slice($argv, 2);

            if (in_array($shortcut, [self::APP_SHORTCUT_PHAR, self::APP_SHORTCUT_SETUP])) {
                return $dto;
            }
        }

        $configFile = getcwd() . '/' . IConfig::CONFIG_FILE;
        if (is_file($configFile)) {
            $config = @require($configFile);
            if (!$config instanceof IConfig) {
                $this->echoLn(
                    'must return instance of ' . IConfig::class . ': ' . $configFile
                );
                return null;
            }
            $dto->config = $config;
        } else {
            $this->echoLn('not found ' . $configFile);
            return null;
        }

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

    function echoLn(string $msg): void
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
