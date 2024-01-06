# shortcuts

Command line tool to call sets of commands using short aliases.

A distinctive feature is that it is configured entirely in **PHP**, which means 
accessibility of **code-completion**, finding of **usage** (and other features of your 
IDE), using of your application **constants**, **validation** and actually brings 
the whole range of PHP possibilities.

The disadvantage of this is that the configuration is more verbose compared to YAML and
other simple formats, but it is negligible compared to the benefits that integration 
into the host application brings.

## global install

to make `short` alias available everywhere:

`sudo php short.phar setup-shortcuts-global`

## usage

in folder with shortcuts.php:

`short [shortcut]`

### example of shortcuts.php:

```php
use Shortcuts\ICommand\CommandsCollection;
use Shortcuts\ICommand\CommandWithoutArgs;
use Shortcuts\IConfig;
use Shortcuts\IDefaultBuilder;
use Shortcuts\IEnvDTO;
use Shortcuts\IEnvDTO\_EnvDTO;
use Shortcuts\ILocalBuilder;
use Shortcuts\ShortcutDTO;
use Shortcuts\ShortcutDTO\ShortcutsCollection;

class Env extends _EnvDTO {
    public string $ENV_VARIABLE1 = 'value1';
    public string $ENV_VARIABLE2 = 'value2';
}

return new class implements IConfig {
    function getDefaultShortcutsBuilder(): IDefaultBuilder {
        return new class implements IDefaultBuilder {
            function getShortcuts(array $commandLineArguments): ShortcutsCollection {
                return (new ShortcutsCollection)
                    ->add(new ShortcutDTO(
                        'alias1',
                        (new CommandsCollection)
                            ->add(new CommandWithoutArgs('long command1'))
                            ->add(new CommandWithoutArgs('long command2')),
                        description: 'alias description'
                    ))
                    ->add(new ShortcutDTO(
                        'alias2', 'long command'
                    ));
            }

            function getEnv(): ?IEnvDTO {
                return new Env();
            }
        };
    }

    function getLocalShortcutsBuilder(): ?ILocalBuilder {
        return require __DIR__ . '/shortcuts.local.php';
    }

    function onBuildComplete(ShortcutsCollection $shortcuts): void {
        $env = $shortcuts->getEnv();
        if ($env->ENV_VARIABLE1 === 'value') {
            $env->ENV_VARIABLE2 = 'new value';
        }
    }
};

```

To simplify `shortcuts.php` editing you can put `short.phar` into your project 
(usually into the folder with `shortcuts.php`) and your IDE will be able to provide code 
completion (at least PhpStorm can do it).

# For contributors

clone the repository and create `short` alias that points to index.php instead of 
short.phar:

`sudo php </path/to/clone>/app/index.php setup-shortcuts-global`

This way `short` will run your development version instead of the distributed one to
let you test your changes.

## recompile short.phar

`./bin/compile.sh`
