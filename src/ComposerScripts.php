<?php

declare(strict_types=1);

namespace NelsonMartell\PhpCodeSniffer;

use Composer\IO\IOInterface;
use Composer\Script\Event;

/**
 * Composer scripts helpers.
 */
class ComposerScripts
{
    /**
     * @var string|null
     */
    protected static $binDir;

    /**
     * @var string|null
     */
    protected static $vendorDir;

    protected static function getBinDir(Event $event)
    {
        if (!static::$binDir) {
            static::$binDir = realpath($event->getComposer()->getConfig()->get('bin-dir'));
        }

        return static::$binDir;
    }

    protected static function getVendorDir(Event $event)
    {
        if (!static::$vendorDir) {
            static::$vendorDir = realpath($event->getComposer()->getConfig()->get('vendor-dir'));
        }

        return static::$vendorDir;
    }

    protected static function bootstrap(Event $event)
    {
        require_once static::getVendorDir($event) . '/autoload.php';
    }

    /**
     * Custom PHP Code Sniffer Fixer to be run with lint-staged pre-commit hook.
     */
    public static function phpcbf(Event $event): void
    {
        $start_time = microtime(true);

        static::bootstrap($event);

        $rootDir = realpath(getcwd());

        $cmd = str_replace($rootDir . DIRECTORY_SEPARATOR, '', realpath(static::getBinDir($event) . '/phpcbf'));

        $files = $event->getArguments();
        $count = count($files);

        $ignoredPaths = [];

        if ($count > 0) {
            $event->getIO()->write("Fixing PHP Coding Standard of {$count} paths.");

            foreach ($files as $i => $file) {
                $realPath = realpath($file);

                if (!$realPath) {
                    $ignoredPaths[] = $file;
                    continue;
                }

                // if ($realPath === realpath(__FILE__)) {
                //     // Do not self-fix this file when lint-staged
                //     continue;
                // }

                $relativePath =  str_replace(
                    [$rootDir . DIRECTORY_SEPARATOR, DIRECTORY_SEPARATOR],
                    ['', '/'],
                    $realPath
                );

                $type = strlen($relativePath) < 4 || stripos($relativePath, '.php', -4) === false ? 'directory' : 'file';

                $event->getIO()->write("Improving <info>{$relativePath}</info> {$type}...");

                $output = [];
                $return = 0;

                // NOTE: workarround: need to run 2 times due to a bug that exits 1 instead of 0 when a file gets fixed
                // https://github.com/squizlabs/PHP_CodeSniffer/issues/1818#issuecomment-735620637
                exec("{$cmd} \"{$realPath}\" || {$cmd} \"{$realPath}\" -q", $output, $return);

                $event->getIO()->write($output, true, IOInterface::VERBOSE);

                if ($return !== 0) {
                    $event->getIO()->error("Error! Unable to autofix the {$relativePath} file!");
                    $event->getIO()->write(
                        '<comment>Run <options=bold>`phpcs`</> manually to check the conflicting files</comment>'
                    );
                    exit(1);
                }
            }
        }

        $event->getIO()->write('<info>Everything is awesome!</info>');

        $end_time       = microtime(true);
        $execution_time = round($end_time - $start_time, 2);

        $event->getIO()->write("Done in {$execution_time}s");

        if (count($ignoredPaths)) {
            $ignoredPaths = array_map(function ($item) {
                return '  - ' . $item;
            }, $ignoredPaths);

            $event->getIO()->write('<comment>Note: Some paths were not found:</comment>');
            $event->getIO()->write($ignoredPaths);
        }
    }
}
