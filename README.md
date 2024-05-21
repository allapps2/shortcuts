# shortcuts

Command line tool to call sets of commands using short aliases.

A distinctive feature is that it is configured entirely in **PHP**, which means 
accessibility of **code-completion**, finding of **usage**, using of your application **constants**, **validation** and actually brings 
the whole range of PHP possibilities and your IDE features.

The disadvantage of this is that the configuration is more verbose compared to YAML and
other simple formats, but it is negligible compared to the benefits that integration 
into the host application brings.

## global install

to make `short` alias available everywhere:

`sudo php ./bin/short.phar setup-shortcuts-global`

## usage

in folder with shortcuts.php:

`short [<shortcut> [<arguments>]]`

### example of shortcuts.php:

```php
use Shortcuts\IBuilder;
use Shortcuts\ICommand\CommandsCollection;
use Shortcuts\ShortcutsCollection;

class EnvDTO extends Shortcuts\IEnvDTO\_EnvDTO {
    public string $ENV_VARIABLE1 = 'value1';
    public string $ENV_VARIABLE2 = 'value2';
}

return new class implements IBuilder {
    function build(): ShortcutsCollection {
        return new class extends ShortcutsCollection {

            function alias1(): CommandsCollection {
                return (new CommandsCollection)->add('long command1');
            }
        
            function alias2(): CommandsCollection {
                return (new CommandsCollection)
                    ->addEnv(new EnvDTO())
                    ->add('long command2')
                    ->add('long command3')
                    ->setDescription('alias description');
                };
            }
        };
    }
};
```

To simplify `shortcuts.php` editing you can put `short.phar` into your project 
(usually into the folder with `shortcuts.php`) and your IDE will be able to provide code 
completion (at least PhpStorm can do it).

# for contributors

clone the repository and create `short` alias that points to index.php instead of 
short.phar:

`sudo php </path/to/clone>/app/index.php setup-shortcuts-global`

This way `short` will run your development version instead of the distributed one to
let you test your changes.

## recompile short.phar

`./bin/compile.sh`
