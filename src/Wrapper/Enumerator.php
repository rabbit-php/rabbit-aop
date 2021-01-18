<?php

declare(strict_types=1);

namespace Go\Instrument\FileSystem;

use ArrayIterator;
use Closure;
use InvalidArgumentException;
use Iterator;
use LogicException;
use SplFileInfo;
use Symfony\Component\Finder\Finder;
use UnexpectedValueException;

/**
 * Class Enumerator
 * @package Rabbit\Aop
 */
class Enumerator
{

    /**
     * Path to the root directory, where enumeration should start
     *
     * @var string
     */
    private string $rootDirectory;

    /**
     * List of additional include paths, should be below rootDirectory
     *
     * @var array
     */
    private array $includePaths = [];

    /**
     * List of additional exclude paths, should be below rootDirectory
     *
     * @var array
     */
    private array $excludePaths = [];

    /**
     * Initializes an enumerator
     *
     * @param string $rootDirectory Path to the root directory
     * @param array $includePaths List of additional include paths
     * @param array $excludePaths List of additional exclude paths
     */
    public function __construct(string $rootDirectory, array $includePaths = [], array $excludePaths = [])
    {
        $this->rootDirectory = $rootDirectory;
        $this->includePaths = $includePaths;
        $this->excludePaths = $excludePaths;
    }

    /**
     * Returns an enumerator for files
     *
     * @return Iterator|SplFileInfo[]
     * @throws UnexpectedValueException
     * @throws InvalidArgumentException
     * @throws LogicException
     */
    public function enumerate(): Iterator
    {
        $finder = new Finder();
        $finder->files()
            ->name('*.php')
            ->in($this->getInPaths());

        foreach ($this->getExcludePaths() as $path) {
            $finder->notPath($path);
        }

        $iterator = $finder->getIterator();

        // on Windows platform the default iterator is unable to rewind, not sure why
        if (strpos(PHP_OS, 'WIN') === 0) {
            $iterator = new ArrayIterator(iterator_to_array($iterator));
        }

        return $iterator;
    }

    /**
     * @return array
     * @throws UnexpectedValueException
     */
    private function getInPaths(): array
    {
        $inPaths = [];

        foreach ($this->includePaths as $path) {
            if (strpos($path, $this->rootDirectory, 0) === false) {
                throw new UnexpectedValueException(sprintf('Path %s is not in %s', $path, $this->rootDirectory));
            }

            $path = str_replace('*', '', $path);
            $inPaths[] = $path;
        }

        if (empty($inPaths)) {
            $inPaths[] = $this->rootDirectory;
        }

        return $inPaths;
    }

    /**
     * @return array
     */
    private function getExcludePaths(): array
    {
        $excludePaths = [];

        foreach ($this->excludePaths as $path) {
            $path = str_replace('*', '.*', $path);
            $excludePaths[] = '#' . str_replace($this->rootDirectory . '/', '', $path) . '#';
        }

        return $excludePaths;
    }

    /**
     * Returns a filter callback for enumerating files
     *
     * @return Closure
     */
    public function getFilter(): Closure
    {
        $rootDirectory = $this->rootDirectory;
        $includePaths = $this->includePaths;
        $excludePaths = $this->excludePaths;

        return function (SplFileInfo $file) use ($rootDirectory, $includePaths, $excludePaths) {
            if ($file->getExtension() !== 'php') {
                return false;
            }

            $fullPath = $this->getFileFullPath($file);
            // Do not touch files that not under rootDirectory
            if (strpos($fullPath, $rootDirectory) !== 0) {
                return false;
            }

            $found = false;
            if (!empty($includePaths)) {
                foreach ($includePaths as $includePattern) {
                    if (fnmatch("{$includePattern}*", $fullPath, FNM_NOESCAPE)) {
                        $found = true;
                        break;
                    }
                }
                if (!$found) {
                    return false;
                }
            }

            foreach ($excludePaths as $excludePattern) {
                if (fnmatch("{$excludePattern}*", $fullPath, FNM_NOESCAPE)) {
                    return false;
                }
            }

            return $found;
        };
    }

    /**
     * Return the real path of the given file
     *
     * This is used for testing purpose with virtual file system.
     * In a vfs the 'realPath' methode will always return false.
     * So we have a chance to mock this single function to return different path.
     *
     * @param SplFileInfo $file
     *
     * @return string
     */
    protected function getFileFullPath(SplFileInfo $file): string
    {
        return $file->getRealPath();
    }
}
