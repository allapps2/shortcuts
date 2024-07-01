<?php

namespace Shortcuts;

use Phar;
use Shortcuts\ICommand\CommandsCollection;
use Shortcuts\ICommand\CommandWithArgs;

class App
{
    /**
     * mainly for internal use, to identify the app.
     * NEVER CHANGE THIS VALUE!
     */
    const CODENAME = 'shortcuts';

    /**
     * publicly used value, in opposite to @see self::CODENAME this one can be changed
     * if needed (for example to solve conflicts with trademarks)
     */
    const NAME = 'shortcuts';

    const VERSION_MAJOR = 1;
    const VERSION_MINOR = 1;
    const VERSION_PATCH = 0;

    const APP_SHORTCUT_PHAR = 'compile-shortcuts-phar';
    const APP_SHORTCUT_SETUP = 'setup-shortcuts-global';
    const APP_SHORTCUT_JSON = 'json';
    const RESERVED_SHORTCUTS = [
        self::APP_SHORTCUT_PHAR,
        self::APP_SHORTCUT_SETUP,
        self::APP_SHORTCUT_JSON,
    ];

    const SUBCOMMAND_JSON_VERSION = 'version';
    const SUBCOMMANDS_JSON = [
        self::SUBCOMMAND_JSON_VERSION,
    ];

    function handle(array $argv): void
    {
        define('ROOT_DIR', dirname(__DIR__));

        try {
            $dtoInput = $this->_parseInput($argv);
            if (!$dtoInput) { // error input parsing
                return;
            }

            if ($this->_handleReservedShortcuts($dtoInput)) {
                return;
            }

            $shortcuts = $dtoInput->builder->build();

            if (!$dtoInput->shortcut) {
                $this->_echoAppName();
                $this->echoLn('Usage: '. basename($argv[0]) . ' [<shortcut>] [<arguments>]');
                $this->_echoShortcuts($shortcuts);
                return;
            }

            $commands = $shortcuts->getIterator()[$dtoInput->shortcut] ?? null;
            if (!$commands) {
                $this->_echoError("unknown shortcut '{$dtoInput->shortcut}'");
                $this->_echoShortcuts($shortcuts, showEnv: false);
                return;
            }

            if ($onBefore = $commands->getOnBefore()) {
                $onBefore->call($shortcuts);
            }

            $this->handleShortcut($commands, $dtoInput);
        } catch(UserFriendlyException $e) {
            $this->_echoError($e->getMessage());
        }
    }

    private function _handleReservedShortcuts(InputDTO $dtoInput): bool
    {
        switch ($dtoInput->shortcut) {
            case self::APP_SHORTCUT_PHAR:
                $this->_echoAppName();
                (new PharCompiler($this))->compile();
                return true;
            case self::APP_SHORTCUT_SETUP:
                $this->_echoAppName();
                $this->setupGlobal($dtoInput->arguments[0] ?? '');
                return true;
            case self::APP_SHORTCUT_JSON:
                $subcommand = $dtoInput->arguments[0] ?? '';
                switch ($subcommand) {
                    case self::SUBCOMMAND_JSON_VERSION:
                        $this->echoLn(json_encode([
                            'major' => self::VERSION_MAJOR,
                            'minor' => self::VERSION_MINOR,
                            'patch' => self::VERSION_PATCH,
                        ]));
                        return true;
                    default:
                        $this->_echoJsonError(
                            $subcommand
                                ? 'unknown subcommand: ' . $dtoInput->arguments[0]
                                : 'missing subcommand, available: ' . (
                                    implode(', ', self::SUBCOMMANDS_JSON)
                                )
                        );
                        return true;
                }
        }

        return false;
    }

    private function setupGlobal(string $alias): void
    {
        if ($_alias = trim($alias)) {
            if (preg_match('/[^a-z0-9]/', $_alias)) {
                throw new UserFriendlyException(
                    'alias should be alphanumeric (a-z, 0-9), wrong value: ' . $_alias
                );
            }
        } else {
            $_alias = 'sc';
        }

        $executable = Phar::running() ?: $_SERVER['SCRIPT_NAME'];
        $dstFile = '/usr/local/bin/' . $_alias;
        $fileContent = sprintf("#!%s\n<?php\nrequire('%s');\n", PHP_BINARY, $executable);
        $writtenBytes = @file_put_contents($dstFile, $fileContent);
        if ($writtenBytes !== strlen($fileContent)) {
            $this->_echoError('Error writing to ' . $dstFile);
        } else {
            chmod($dstFile, fileperms($dstFile) | 0111); // +x
            $this->_echoError("Now you can use '{$_alias}' in any directory with shortcuts.php");
        }
    }

    private function _echoAppName(): void
    {
        $this->echoLn(self::NAME . ', version: ' . self::_getVersion());
    }

    private static function _getVersion(): string
    {
        return self::VERSION_MAJOR . '.' . self::VERSION_MINOR . '.' . self::VERSION_PATCH;
    }

    private function _echoError(string $msg): void
    {
        $this->_echoAppName();
        $this->echoLn($msg);
    }

    private function _echoJsonError(string $msg): void
    {
        $this->echoLn(json_encode(['error' => $msg]));
    }

    private function _echoShortcuts(
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
        $envDefault = [];
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

        // default environment variables
        if ($envDefault) {
            $this->echoLn('environment variables:');
            ksort($envDefault);
            foreach ($envDefault as $var => $value) {
                $this->echoLn($prefix . "{$var}={$value}");
            }
        }
    }

    private function _parseInput(array $argv): ?InputDTO
    {
        if ($shortcut = trim($argv[1] ?? '')) {
            $dto = new InputDTO($shortcut, array_map('trim', array_slice($argv, 2)));
            if (in_array($shortcut, self::RESERVED_SHORTCUTS)) {
                return $dto;
            }
        } else {
            $dto = new InputDTO();
        }

        $configFile = getcwd() . '/' . IBuilder::CONFIG_FILE;
        if (is_file($configFile)) {
            ob_start(); // prevent file content output in case of invalid php or errors
            $builder = @require($configFile);
            ob_end_clean();
            if (!$builder instanceof IBuilder) {
                $this->_echoError(
                    'must return instance of ' . IBuilder::class . ': ' . $configFile
                );
                return null;
            }
            $dto->setBuilder($builder);
        } else {
            $this->_echoError('not found ' . $configFile);
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
