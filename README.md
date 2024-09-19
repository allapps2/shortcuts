# shortcuts

Console tool to call sets of commands using short aliases.

A distinctive feature is that it is configured entirely in **PHP**, which means
accessibility of **code-completion**, finding of **usage**, using of your application **constants**, **validation** and actually brings
the whole range of PHP possibilities and your IDE features.

The disadvantage of this is that the configuration is more verbose compared to YAML and
other simple formats, but it is negligible compared to the benefits that integration
into the host application brings.

## installation

### requirements

- PHP 8.2+\
  php-cli package is enough (for example on Alpine Linux it can be installed by `apk add php82-cli`).

### download

download `short.phar` from https://github.com/allapps2/shortcuts/blob/main/bin/short.phar
and put it into your project (for example to .for-auto-completion folder to be used
by your IDE like PhpStorm for code auto-completion).

### global install

to make `sc` alias available everywhere:

`sudo php short.phar setup-shortcuts-global`

or any custom alias:

`sudo php short.phar setup-shortcuts-global myalias`

## usage

in folder with shortcuts.php:

`sc [<shortcut> [<arguments>]]`

### example of shortcuts.php:

```php
use Shortcuts\Command\CommandsCollection;
use Shortcuts\IBuilder;
use Shortcuts\Shortcut;
use Shortcuts\ShortcutArg;
use Shortcuts\ShortcutsCollection;
use Shortcuts\ShortcutsCollectionFactory;

class Shortcuts extends ShortcutsCollection
{
    function shortcut1(): CommandsCollection
    {
        return (new CommandsCollection)->add('long command1');
    }

    #[Shortcut(description: 'Shortcut description')]
    function shortcut2(
        string $requiredArgument,
        #[ShortcutArg(description: 'Optional argument')]
        string $optionalArgument = 'default value'
    ): CommandsCollection
    {
        return (new CommandsCollection)
            ->add('long command2 ' . $requiredArgument)
            ->add('long command3 ' . $optionalArgument);
    }
}

return new class implements IBuilder {
    function build(ShortcutsCollectionFactory $factory): ShortcutsCollection {
        return $factory->create(Shortcuts::class);
    }
};
```

## for contributors

clone the repository and create `sc` alias that points to index.php instead of
short.phar:

`sudo php </path/to/clone>/app/index.php setup-shortcuts-global`

This way `sc` will run your development version instead of the distributed one to
let you test your changes.

### recompile short.phar

`./bin/compile.sh`

### create git tag

Set the version number in constants VERSION_* and recompile short.phar.\
For tag name use the version number shown by

`php ./bin/short.phar`
