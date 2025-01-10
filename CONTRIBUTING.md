Clone the repository and create `sc` alias that points to index.php instead of
short.phar:

`sudo php </path/to/clone>/app/index.php install-global`

This way `sc` will run your development version to let you test your changes.

### recompile short.phar

`./bin/compile.sh`

### create git tag

Set the version number in constants VERSION_* and recompile short.phar.\
For tag name use the version number shown by

`php ./bin/short.phar`
