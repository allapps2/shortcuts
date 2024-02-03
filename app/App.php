<?php

namespace Shortcuts;

use Phar;
use Shortcuts\ICommand\CommandWithArgs;
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
            $this->echoShortcuts($shortcuts, showEnv: false);
            return;
        }

        try {
            $this->handleShortcut($dtoShortcut, $shortcuts->getEnv(), $dtoInput);
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
        $this->echoLn('available shortcuts:');
        $prefix = '  ';
        $descSeparator = '- ';
        $argsSeparator = str_repeat(' ', strlen($descSeparator));

        $argMaxLen = 0;
        foreach ($shortcuts as $dtoShortcut) {
            foreach ($dtoShortcut->commands->getArguments() as $dtoArg) {
                $argMaxLen = max($argMaxLen, strlen($dtoArg->name));
            }
        }
        $shortcutLen = max(
            $shortcuts->getShortcutMaxLen(),
            $argMaxLen + strlen($prefix) + strlen(CommandWithArgs::ARG_PREFIX)
        ) + 1;
        $argLen = $shortcutLen - strlen($prefix);

        foreach ($shortcuts as $dtoShortcut) {
            if ($dtoShortcut->description) {
                $this->echoLn(
                    $prefix .
                    str_pad($dtoShortcut->shortcut, $shortcutLen) .
                    $descSeparator . $dtoShortcut->description
                );
            } else {
                $this->echoLn($prefix . $dtoShortcut->shortcut);
            }

            foreach ($dtoShortcut->commands->getArguments() as $dtoArg) {
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
        }

        if ($showEnv) {
            $this->echoLn('environment variables:');
            $env = $shortcuts->getEnv()->asArray();
            ksort($env);
            foreach ($env as $name => $value) {
                $this->echoLn($prefix . "{$name} = {$value}");
            }
        }
    }

    private function buildShortcutsCollection(InputDTO $dtoInput): ShortcutsCollection
    {
        $defaultBuilder = $dtoInput->config->getDefaultShortcutsBuilder();
        $shortcuts = $defaultBuilder->getShortcuts();
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
        if ($shortcut = trim($argv[1] ?? '')) {
            $dto = new InputDTO($shortcut, array_slice($argv, 2));

            if (in_array($shortcut, [self::APP_SHORTCUT_PHAR, self::APP_SHORTCUT_SETUP])) {
                return $dto;
            }
        } else {
            $dto = new InputDTO();
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
            $dto->setConfig($config);
        } else {
            $this->echoLn('not found ' . $configFile);
            return null;
        }

        return $dto;
    }

    private function handleShortcut(
        ShortcutDTO $dtoShortcut, IEnvDTO $dtoEnv, InputDTO $dtoInput
    ): void
    {
        $env = $dtoEnv->asArray();
        $argsEscaped = $dtoInput->parseAndEscapeArguments();
        foreach ($dtoShortcut->commands as $command) {
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
