<?php

namespace Shortcuts;

use Phar;
use Shortcuts\ICommand\CallbackWithArgs\ArgDefinitionDTO;
use Shortcuts\ICommand\CommandsCollection;
use Shortcuts\ICommand\CallbackWithArgs;

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
    const VERSION_MINOR = 3;
    const VERSION_PATCH = 2;

    const APP_SHORTCUT_PHAR = 'compile-shortcuts-phar';
    const APP_SHORTCUT_SETUP = 'setup-shortcuts-global';
    const APP_SHORTCUT_JSON = 'json';
    const RESERVED_SHORTCUTS = [
        self::APP_SHORTCUT_PHAR,
        self::APP_SHORTCUT_SETUP,
        self::APP_SHORTCUT_JSON,
    ];

    const JSON_SUBCOMMAND_VERSION = 'version';
    const JSON_SUBCOMMANDS = [
        self::JSON_SUBCOMMAND_VERSION,
    ];

    const ARG_VERBOSE = 'verbose';

    private InjectablesContainer $di;

    function __construct()
    {
        $this->di = new InjectablesContainer();
    }

    function handle(array $argv): void
    {
        define('ROOT_DIR', dirname(__DIR__));

        try {
            $dtoInput = $this->_parseInput($argv);
            if (!$dtoInput) { // error during input parsing
                return;
            }
            $this->di->setInput($dtoInput);

            if ($this->_handleReservedShortcuts($dtoInput)) {
                return;
            }

            $shortcutsCollection = $dtoInput->builder->build();
            $shortcutsCollection->onAfterConstruct($this->di);

            if (!$dtoInput->shortcut) {
                $this->_echoAppNameIfNoEchoed();
                ConsoleService::echo(
                    'Usage: '. basename($argv[0]) . ' [<shortcut>] [<arguments>]'
                );
                $this->_echoShortcuts($shortcutsCollection);
                return;
            }

            $availableShortcuts = $shortcutsCollection->getAvailableShortcuts();
            if (!isset($availableShortcuts[$dtoInput->shortcut])) {
                $this->_echoError("unknown shortcut '{$dtoInput->shortcut}'");
                $this->_echoShortcuts($shortcutsCollection, showEnv: false);
                return;
            }
            $commands = $availableShortcuts[$dtoInput->shortcut];

            $this->handleShortcut($commands, $shortcutsCollection);
        } catch(UserFriendlyException $e) {
            $this->_echoError($e->getMessage());
        }
    }

    private function _handleReservedShortcuts(InputDTO $dtoInput): bool
    {
        switch ($dtoInput->shortcut) {
            case self::APP_SHORTCUT_PHAR:
                $this->_echoAppNameIfNoEchoed();
                (new PharCompiler)->compile();
                return true;
            case self::APP_SHORTCUT_SETUP:
                $this->_echoAppNameIfNoEchoed();
                $this->setupGlobal($dtoInput->arguments[0] ?? '');
                return true;
            case self::APP_SHORTCUT_JSON:
                $subcommand = $dtoInput->arguments[0] ?? '';
                switch ($subcommand) {
                    case self::JSON_SUBCOMMAND_VERSION:
                        ConsoleService::echo(json_encode([
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
                                    implode(', ', self::JSON_SUBCOMMANDS)
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
            ConsoleService::echo(
                "Now you can use '{$_alias}' in any directory with shortcuts.php"
            );
        }
    }

    private function _echoAppNameIfNoEchoed(): void
    {
        static $wasEchoed = false;
        if (!$wasEchoed) {
            ConsoleService::echo(self::NAME . ', version: ' . self::_getVersion());
            $wasEchoed = true;
        }
    }

    private static function _getVersion(): string
    {
        return self::VERSION_MAJOR . '.' . self::VERSION_MINOR . '.' . self::VERSION_PATCH;
    }

    private function _echoError(string $msg): void
    {
        $this->_echoAppNameIfNoEchoed();
        ConsoleService::echo($msg);
    }

    private function _echoJsonError(string $msg): void
    {
        ConsoleService::echo(json_encode(['error' => $msg]));
    }

    private function _echoShortcuts(
        ShortcutsCollection $collection, bool $showEnv = true
    ): void
    {
        $prefix = '  ';
        $descSeparator = '- ';
        $argsSeparator = str_repeat(' ', strlen($descSeparator));
        $shortcuts = $collection->getAvailableShortcuts();

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
            $argMaxLen + strlen($prefix) + strlen(CallbackWithArgs::ARG_PREFIX)
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
                $shortcutsCount = count($shortcuts);
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
        ConsoleService::echo('available shortcuts:');
        foreach ($shortcuts as $shortcut => $commands) {
            if ($commands->getDescription()) {
                ConsoleService::echo(
                    $prefix .
                    str_pad($shortcut, $shortcutLen) .
                    $descSeparator . $commands->getDescription()
                );
            } else {
                ConsoleService::echo($prefix . $shortcut);
            }

            foreach ($commands->getArguments() as $dtoArg) {
                $prefixedName = $dtoArg->type === ArgDefinitionDTO::TYPE_VARIADIC
                    ? '*'
                    : (CallbackWithArgs::ARG_PREFIX . $dtoArg->name);
                $arg = $prefix . $prefix . str_pad($prefixedName, $argLen) . $argsSeparator;
                if (isset($dtoArg->description)) {
                    $descriptionSeparator = ', ';
                    $arg .= $dtoArg->description;
                } else {
                    $descriptionSeparator = '';
                }
                switch ($dtoArg->type) {
                    case 'bool':
                        $arg .= $descriptionSeparator . 'optional flag';
                        break;
                    case 'string':
                        if ($dtoArg->hasDefaultValue()) {
                            $arg .= $descriptionSeparator . 'optional';
                            if (!empty($dtoArg->defaultValue)) {
                                $arg .= ', default: ' . $dtoArg->defaultValue;
                            }
                        }
                        break;
                    case 'array':
                        $arg .= $descriptionSeparator . 'multiple values';
                        break;
                    case ArgDefinitionDTO::TYPE_VARIADIC:
                        $arg .= $descriptionSeparator . 'all arguments are passed as is';
                        break;
                    default:
                        throw new \Exception(
                            "Unsupported type '{$dtoArg->type}', fix filling of " .
                            get_class($dtoArg)
                        );
                }
                ConsoleService::echo($arg);
            }

            if (isset($envNonDefault[$shortcut])) {
                ConsoleService::echo($prefix . $prefix . 'environment variables:');
                $vars = $envNonDefault[$shortcut];
                ksort($vars);
                foreach ($vars as $var => $value) {
                    ConsoleService::echo($prefix . $prefix . $prefix . "{$var}={$value}");
                }
            }
        }

        // default environment variables
        if ($envDefault) {
            ConsoleService::echo('environment variables:');
            ksort($envDefault);
            foreach ($envDefault as $var => $value) {
                ConsoleService::echo($prefix . "{$var}={$value}");
            }
        }
    }

    private function _parseInput(array $argv): ?InputDTO
    {
        $shortcut = trim($argv[1] ?? '') ?: null;
        if ($shortcut) {
            $args = array_map('trim', array_slice($argv, 2));
            if (in_array($shortcut, self::RESERVED_SHORTCUTS)) {
                return new InputDTO($shortcut, $args);
            }
        } else {
            $args = [];
        }

        $configFile = getcwd() . '/' . IBuilder::CONFIG_FILE;
        if (is_file($configFile)) {
            ob_start(); // prevent file content output in case of invalid php or errors
            $builder = @require($configFile);
            ob_end_clean();
            if ($builder instanceof IBuilder) {
                return new InputDTO($shortcut, $args, $builder);
            }
            $this->_echoError(
                'must return instance of ' . IBuilder::class . ': ' . $configFile
            );
        } else {
            $this->_echoError('not found ' . $configFile);
        }

        return null;
    }

    private function handleShortcut(
        CommandsCollection $commands, ShortcutsCollection $shortcuts
    ): void
    {
        $console = $this->di->getConsoleServiceFactory()->create($commands->getEnv());
        $shortcuts->enableRuntimeMode($console);
        foreach ($shortcuts->resolveCallbacks($commands) as $command) {
            if (!$console->execSTDOUT($command->command, $command->isEchoRequired())) {
                break;
            }
        }
    }
}
