<?php

namespace Shortcuts;

use Phar;
use Shortcuts\ICommand\CommandsCollection;
use Shortcuts\ICommand\CommandWithArgs;

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

        $shortcuts = $dtoInput->builder->build();

        if (!$dtoInput->shortcut) {
            $this->echoShortcuts($shortcuts);
            return;
        }

        $commands = $shortcuts->getIterator()[$dtoInput->shortcut] ?? null;
        if (!$commands) {
            $this->echoLn("unknown shortcut '{$dtoInput->shortcut}'");
            $this->echoShortcuts($shortcuts, showEnv: false);
            return;
        }

        try {
            $this->handleShortcut($commands, $dtoInput);
        } catch(UserFriendlyException $e) {
            $this->echoLn($e->getMessage());
        }
    }

    private function setupGlobal(): void
    {
        $executable = Phar::running() ?: $_SERVER['SCRIPT_NAME'];
        $dstFile = '/usr/local/bin/short';
        $fileContent = sprintf("#!%s\n<?php\nrequire('%s');\n", PHP_BINARY, $executable);
        $writtenBytes = @file_put_contents($dstFile, $fileContent);
        if ($writtenBytes !== strlen($fileContent)) {
            $this->echoLn('Error writing to ' . $dstFile);
        } else {
            chmod($dstFile, fileperms($dstFile) | 0111); // +x
            $this->echoLn('Now you can use "short" in any directory with shortcuts.php');
        }
    }

    private function echoShortcuts(
        ShortcutsCollection $shortcuts, bool $showEnv = true
    ): void
    {
        $prefix = '  ';
        $descSeparator = '- ';
        $argsSeparator = str_repeat(' ', strlen($descSeparator));

        $shortcutMaxLen = 0;
        $argMaxLen = 0;
        foreach ($shortcuts as $shortcut => $commands) {
            $shortcutMaxLen = max($shortcutMaxLen, strlen($shortcut));
            foreach ($commands->getArguments() as $dtoArg) {
                $argMaxLen = max($argMaxLen, strlen($dtoArg->name));
            }
        }
        $shortcutLen = max(
            $shortcutMaxLen,
            $argMaxLen + strlen($prefix) + strlen(CommandWithArgs::ARG_PREFIX)
        ) + 1;
        $argLen = $shortcutLen - strlen($prefix);

        // environment variables
        $envNonDefault = [];
        if ($showEnv) {
            $envAll = [];
            foreach ($shortcuts as $shortcut => $commands) {
                foreach ($commands->getEnv() as $var => $value) {
                    if (!isset($envAll[$var][$value])) {
                        $envAll[$var][$value] = [];
                    }
                    $envAll[$var][$value][] = $shortcut;
                }
            }

            if (!empty($envAll)) {
                $envDefault = [];
                $shortcutsCount = count($shortcuts->getIterator());
                foreach ($envAll as $var => $values) {
                    foreach ($values as $value => $shortcutNames) {
                        if (count($shortcutNames) === $shortcutsCount) {
                            $envDefault[$var] = $value;
                        } else {
                            foreach ($shortcutNames as $shortcutName) {
                                if (!isset($envNonDefault[$shortcutName])) {
                                    $envNonDefault[$shortcutName] = [];
                                }
                                $envNonDefault[$shortcutName][$var] = $value;
                            }
                        }
                    }
                }
                if ($envDefault) {
                    $this->echoLn('environment variables:');
                    ksort($envDefault);
                    foreach ($envDefault as $var => $value) {
                        $this->echoLn($prefix . "{$var}={$value}");
                    }
                }
            }
        }

        // shortcuts
        $this->echoLn('available shortcuts:');
        foreach ($shortcuts as $shortcut => $commands) {
            if ($commands->getDescription()) {
                $this->echoLn(
                    $prefix .
                    str_pad($shortcut, $shortcutLen) .
                    $descSeparator . $commands->getDescription()
                );
            } else {
                $this->echoLn($prefix . $shortcut);
            }

            foreach ($commands->getArguments() as $dtoArg) {
                $arg = $prefix . $prefix .
                    str_pad(CommandWithArgs::ARG_PREFIX . $dtoArg->name, $argLen);
                switch ($dtoArg->type) {
                    case 'bool':
                        $arg .= $argsSeparator . 'optional flag';
                        break;
                    case 'string':
                        if ($dtoArg->hasDefaultValue()) {
                            $arg .= $argsSeparator . 'optional';
                            if (!empty($dtoArg->defaultValue)) {
                                $arg .= ', default: ' . $dtoArg->defaultValue;
                            }
                        }
                        break;
                    case 'array':
                        $arg .= $argsSeparator . 'multiple values';
                        break;
                    default:
                        throw new \Exception(
                            "Unsupported type '{$dtoArg->type}', fix filling of " .
                            get_class($dtoArg)
                        );
                }
                $this->echoLn($arg);
            }

            if (isset($envNonDefault[$shortcut])) {
                $this->echoLn($prefix . $prefix . 'environment variables:');
                $vars = $envNonDefault[$shortcut];
                ksort($vars);
                foreach ($vars as $var => $value) {
                    $this->echoLn($prefix . $prefix . $prefix . "{$var}={$value}");
                }
            }
        }
    }

    private function parseInput(array $argv): ?InputDTO
    {
        if ($shortcut = trim($argv[1] ?? '')) {
            $dto = new InputDTO($shortcut, array_slice($argv, 2));

            if (in_array($shortcut, [self::APP_SHORTCUT_PHAR, self::APP_SHORTCUT_SETUP])) {
                return $dto;
            }
        } else {
            $dto = new InputDTO();
        }

        $configFile = getcwd() . '/' . IBuilder::CONFIG_FILE;
        if (is_file($configFile)) {
            $builder = @require($configFile);
            if (!$builder instanceof IBuilder) {
                $this->echoLn(
                    'must return instance of ' . IBuilder::class . ': ' . $configFile
                );
                return null;
            }
            $dto->setBuilder($builder);
        } else {
            $this->echoLn('not found ' . $configFile);
            return null;
        }

        return $dto;
    }

    private function handleShortcut(CommandsCollection $commands, InputDTO $dtoInput): void
    {
        $env = $commands->getEnv();
        $argsEscaped = $dtoInput->parseAndEscapeArguments();
        foreach ($commands as $command) {
            if (!$this->execCommand($command, $env, $argsEscaped)) {
                break;
            }
        }
    }

    function echoLn(string $msg): void
    {
        echo $msg . "\n";
    }

    private function execCommand(ICommand $command, array $env, array $argsEscaped): bool
    {
        $sCmd = $command->compose($argsEscaped);

        if ($command->isEchoRequired()) {
            $this->echoLn($sCmd);
        }

        $process = proc_open($sCmd, [1 => STDOUT, 2 => STDERR], $pipes, env_vars: $env);
        if (!is_resource($process)) {
            $this->echoLn("failed to execute: {$sCmd}");
            return false;
        }
        while(proc_get_status($process)['running']) {
            sleep(1);
        }
        proc_close($process);

        return true;
    }
}
