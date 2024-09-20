<?php

namespace Shortcuts;

use Phar;
use Shortcuts\Command\CommandsCollection;
use Shortcuts\Shortcut\ShortcutDefinitionDTO;
use Shortcuts\ShortcutArg\ArgDefinitionDTO;

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
    const VERSION_MINOR = 4;
    const VERSION_PATCH = 1;

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

    const ARGS_FOR_ME_TITLE = 'special arguments';
    const ARG_VERBOSE = 'vvv';

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

            $shortcuts = $dtoInput->builder->build(
                new ShortcutsCollectionFactory($this->di)
            );

            if (!$dtoInput->shortcut) {
                $this->_echoAppNameIfNoEchoed();
                ConsoleService::echo(
                    'Usage: '. basename($argv[0]) .
                    ' [<' . self::ARGS_FOR_ME_TITLE . '> ' . InputDTO::ARG_PREFIX . ']' .
                    ' [<shortcut> [<arguments>]]'
                );
                $this->_echoShortcuts($shortcuts);
                return;
            }

            $availableShortcuts = $shortcuts->getAvailableShortcuts();
            $dtoShortcut = $availableShortcuts->getByName($dtoInput->shortcut);
            if (!$dtoShortcut) {
                $this->_echoError("unknown shortcut '{$dtoInput->shortcut}'");
                $this->_echoShortcuts($shortcuts, showShortcutsOnly: true);
                return;
            }

            $this->handleShortcut($dtoShortcut, $shortcuts);
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
        ShortcutsCollection $collection, bool $showShortcutsOnly = false
    ): void
    {
        $prefix = '  ';
        $descSeparator = '- ';
        $argsSeparator = str_repeat(' ', strlen($descSeparator));
        $shortcuts = $collection->getAvailableShortcuts();

        $shortcutMaxLen = 0;
        $argMaxLen = 0;
        foreach ($shortcuts->walk() as $dtoShortcut) {
            $shortcutMaxLen = max($shortcutMaxLen, strlen($dtoShortcut->name));
            foreach ($dtoShortcut->getArguments() as $dtoArg) {
                $argMaxLen = max($argMaxLen, strlen($dtoArg->name));
            }
        }
        $shortcutLen = max(
            $shortcutMaxLen,
            $argMaxLen + strlen($prefix) + strlen(InputDTO::ARG_PREFIX)
        ) + 1;
        $argLen = $shortcutLen - strlen($prefix);

        // environment variables
        $envDefault = [];
        $envNonDefault = [];
        if (!$showShortcutsOnly) {
            $envAll = [];
            foreach ($shortcuts->walk() as $dtoShortcut) {
                foreach ($dtoShortcut->getEnv() as $var => $value) {
                    if (!isset($envAll[$var][$value])) {
                        $envAll[$var][$value] = [];
                    }
                    $envAll[$var][$value][] = $dtoShortcut->name;
                }
            }

            if (!empty($envAll)) {
                $shortcutsCount = $shortcuts->count();
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
        foreach ($shortcuts->walk() as $dtoShortcut) {
            if (isset($dtoShortcut->description)) {
                ConsoleService::echo(
                    $prefix .
                    str_pad($dtoShortcut->name, $shortcutLen) .
                    $descSeparator . $dtoShortcut->description
                );
            } else {
                ConsoleService::echo($prefix . $dtoShortcut->name);
            }

            foreach ($dtoShortcut->getArguments() as $dtoArg) {
                $prefixedName = $dtoArg->type === ArgDefinitionDTO::TYPE_VARIADIC
                    ? '*'
                    : (InputDTO::ARG_PREFIX . $dtoArg->name);
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
                        if (!isset($dtoArg->description)) {
                            $arg .= $descriptionSeparator . 'all arguments are passed as is';
                        }
                        break;
                    default:
                        throw new \Exception(
                            "Unsupported type '{$dtoArg->type}', fix filling of " .
                            get_class($dtoArg)
                        );
                }
                ConsoleService::echo($arg);
            }

            if (isset($envNonDefault[$dtoShortcut->name])) {
                ConsoleService::echo($prefix . $prefix . 'environment variables:');
                $vars = $envNonDefault[$dtoShortcut->name];
                ksort($vars);
                foreach ($vars as $var => $value) {
                    ConsoleService::echo($prefix . $prefix . $prefix . "{$var}={$value}");
                }
            }
        }

        // arguments for me
        if (!$showShortcutsOnly) {
            ConsoleService::echo(self::ARGS_FOR_ME_TITLE . ':');
            ConsoleService::echo(
                $prefix . str_pad(InputDTO::ARG_PREFIX . self::ARG_VERBOSE, $argLen) .
                $argsSeparator . $descSeparator . 'verbose mode'
            );
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
        // args for me
        $_argv = array_values($argv);
        if ($i = array_search(InputDTO::ARG_PREFIX, $_argv, true)) {
            $argsForMe = array_splice($_argv, 1, $i);
            array_pop($argsForMe);
        } else {
            $argsForMe = [];
        }

        // args for config
        $shortcut = trim($_argv[1] ?? '') ?: null;
        if ($shortcut) {
            $args = array_map('trim', array_slice($_argv, 2));
            if (in_array($shortcut, self::RESERVED_SHORTCUTS)) {
                return new InputDTO($shortcut, $argsForMe, $args);
            }
        } else {
            $args = [];
        }

        // config
        $configFile = getcwd() . '/' . IBuilder::CONFIG_FILE;
        if (is_file($configFile)) {
            ob_start(); // prevent file content output in case of invalid php or errors
            $builder = @require($configFile);
            ob_end_clean();
            if ($builder instanceof IBuilder) {
                return new InputDTO($shortcut, $argsForMe, $args, $builder);
            }
            $this->_echoError(
                'must return instance of ' . IBuilder::class . ': ' . $configFile
            );
        } else {
            $this->_echoError('no shortcuts found, missing ' . $configFile);
        }

        return null;
    }

    private function handleShortcut(
        ShortcutDefinitionDTO $dtoShortcut, ShortcutsCollection $shortcuts
    ): void
    {
        $console = $this->di->getConsoleServiceFactory()->create($dtoShortcut->getEnv());
        /** @var CommandsCollection $commands */
        $commands = $dtoShortcut->refMethod->invoke(
            $shortcuts,
            ...$this->_populateArgsWithValues($dtoShortcut, $console->dtoInput)
        );
        $aCommands = [];
        foreach ($commands->asArray() as $command) {
            if ($console->isVerboseMode || $command->echoCommand) {
                $aCommands[] = 'echo -e ' . ConsoleService::composeEchoColored(
                    escapeshellarg($command->command), ConsoleService::VERBOSE_COLOR
                );
            }
            $aCommands[] = $command->command;
        }
        if ($aCommands) {
            $console->execSTDOUT(implode(" &&\n", $aCommands), ignoreVerboseMode: true);
        }
    }

    private function _populateArgsWithValues(
        ShortcutDefinitionDTO $dtoShortcut, InputDTO $dtoInput
    ): array
    {
        $values = [];

        foreach ($dtoShortcut->getArguments() as $dtoArg) {
            if ($dtoArg->type === ArgDefinitionDTO::TYPE_VARIADIC) {
                $values[] = implode(' ', $dtoInput->arguments);
            } elseif (array_key_exists($dtoArg->name, $dtoInput->namedArguments)) {
                $value = $dtoInput->namedArguments[$dtoArg->name];
                $type = gettype($value);
                if ($type === 'boolean') {
                    $type = 'bool';
                }
                if ($type !== $dtoArg->type) {
                    if ($dtoArg->type === 'array' && $type === 'string') {
                        $value = [$value];
                    } elseif($type === 'bool') { // argument was specified, but as flag, without value
                        throw new UserFriendlyException(
                            'Missing value in ' . InputDTO::ARG_PREFIX . $dtoArg->name
                        );
                    } else {
                        throw new UserFriendlyException(sprintf(
                            'Invalid value type in %s, expected %s, got %s',
                            InputDTO::ARG_PREFIX . $dtoArg->name,
                            $dtoArg->type,
                            $type
                        ));
                    }
                }
                $values[$dtoArg->name] = $value;
            } else {
                if (!$dtoArg->hasDefaultValue()) {
                    throw new UserFriendlyException(
                        'Missing required argument ' . InputDTO::ARG_PREFIX . $dtoArg->name
                    );
                }
                $values[$dtoArg->name] = $dtoArg->defaultValue;
            }
        }

        return $values;
    }
}
