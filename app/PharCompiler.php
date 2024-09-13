<?php

namespace Shortcuts;

use FilesystemIterator;
use Phar;
use RecursiveCallbackFilterIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

class PharCompiler
{
    const EXCLUDE_FILES = ['composer.json', 'compile.sh'];

    function compile(): void
    {
        $pharFile = Phar::running() ?: (ROOT_DIR . '/bin/short.phar');
        if (file_exists($pharFile)) {
            unlink($pharFile);
        }
        ConsoleService::echo('destination: ' . $pharFile);

        $phar = new Phar($pharFile);
        $phar->startBuffering();
        $files = $phar->buildFromIterator(
            new RecursiveIteratorIterator(new RecursiveCallbackFilterIterator(
                new RecursiveDirectoryIterator(
                    ROOT_DIR,
                    FilesystemIterator::SKIP_DOTS | FilesystemIterator::CURRENT_AS_FILEINFO
                ),
                function (SplFileInfo $fileInfo) {
                    if (
                        ($fileInfo->getFilename()[0] !== '.') &&
                        (!in_array($fileInfo->getFilename(), self::EXCLUDE_FILES))
                    ) {
                        return $fileInfo;
                    }
                }
            )),
            ROOT_DIR
        );

        $phar->setStub($phar->createDefaultStub('app/index.php'));
        $phar->stopBuffering();

        ConsoleService::echo('included files:');
        ConsoleService::echo('  ' . implode("\n  ", array_keys($files)));
    }
}
