# shortcuts

Console tool to call sets of commands using short aliases - like a Makefile, but in PHP.

A distinctive feature is that it is configured entirely in **PHP**, which gives you
**code-completion**, **find usages**, access to your application's **constants**,
**validation**, and the whole range of PHP and IDE capabilities.

The downside is that the configuration is more verbose compared to YAML and other
simple formats, but that's negligible compared to the benefits of integrating with
the host application.

## installation

### requirements

- PHP 8.2+\
  php-cli package is enough (for example on Alpine Linux it can be installed by `apk add php82-cli`).

### download

download `short.phar` from https://github.com/allapps2/shortcuts/blob/main/bin/short.phar
and put it into your project (for example into the .for-auto-completion folder, to be
used by your IDE, like PhpStorm, for code auto-completion).

### global install

to make the `sc` alias available everywhere:

`sudo php short.phar install-global`

or with a custom alias:

`sudo php short.phar install-global <myalias>`

## usage

in a folder containing shortcuts.php:

`sc [<shortcut> [<arguments>]]`

### arguments

arguments can be passed named, `--name=value` (or `--name` alone for `bool` flags),
in any order:

`sc shortcut2 --optionalArgument=value --requiredArgument=value`

arguments can also be passed positionally, matching the order they're declared:

`sc shortcut2 value1 value2`

Named and positional arguments can be mixed; a named argument always takes precedence
over a positional one for the same parameter. `bool` flags and `array` arguments must
always be passed named, since a bare value would be ambiguous for them.

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

see [CONTRIBUTING.md](/CONTRIBUTING.md)
