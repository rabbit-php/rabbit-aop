<?php

declare(strict_types=1);

namespace Rabbit\Aop;

use Composer\Autoload\ClassLoader;
use Doctrine\Common\Annotations\AnnotationRegistry;
use Go\Core\AspectContainer;
use Go\Instrument\ClassLoading\AopComposerLoader as ClassLoadingAopComposerLoader;
use Go\Instrument\FileSystem\Enumerator;
use Go\Instrument\PathResolver;
use Rabbit\Aop\Transformers\FilterInjectorTransformer;

/**
 * Class AopComposerLoader
 * @package Rabbit\Aop
 */
class AopComposerLoader extends ClassLoadingAopComposerLoader
{
    /** @var bool */
    private static bool $wasInitialized = false;

    /**
     * AopComposerLoader constructor.
     * @param ClassLoader $original
     * @param AspectContainer $container
     * @param array $options
     */
    public function __construct(ClassLoader $original, array $options = [])
    {
        $this->options = $options;
        $this->original = $original;

        $prefixes = $original->getPrefixes();
        $excludePaths = $options['excludePaths'];

        if (!empty($prefixes)) {
            // Let's exclude core dependencies from that list
            if (isset($prefixes['Dissect'])) {
                $excludePaths[] = $prefixes['Dissect'][0];
            }
            if (isset($prefixes['Doctrine\\Common\\Annotations\\'])) {
                $excludePaths[] = substr($prefixes['Doctrine\\Common\\Annotations\\'][0], 0, -16);
            }
        }

        $fileEnumerator = new Enumerator($options['appDir'], $options['includePaths'], $excludePaths);
        $this->fileEnumerator = $fileEnumerator;
    }

    /**
     * @return array|null
     */
    public function getIncludePath(): ?array
    {
        return $this->options['includePaths'];
    }

    /**
     * @param array $options
     * @param AspectContainer $container
     * @return bool
     */
    public static function init(array $options, AspectContainer $container): bool
    {
        $loaders = spl_autoload_functions();

        foreach ($loaders as &$loader) {
            $loaderToUnregister = $loader;
            if (is_array($loader) && ($loader[0] instanceof ClassLoader)) {
                $originalLoader = $loader[0];
                // Configure library loader for doctrine annotation loader
                AnnotationRegistry::registerLoader(function ($class) use ($originalLoader) {
                    $originalLoader->loadClass($class);

                    return class_exists($class, false);
                });
                $loader[0] = new AopComposerLoader($loader[0], $options);
                self::$wasInitialized = true;
            }
            spl_autoload_unregister($loaderToUnregister);
        }
        unset($loader);

        foreach ($loaders as $loader) {
            spl_autoload_register($loader);
        }

        return self::$wasInitialized;
    }

    public static function wasInitialized(): bool
    {
        return self::$wasInitialized;
    }

    /**
     * @param string $class
     */
    public function loadClass($class): void
    {
        $file = $this->findFile($class);

        if ($file !== false) {
            if (strpos($file, 'php://') === 0) {
                if (preg_match('/resource=(.+)$/', $file, $matches)) {
                    $file = PathResolver::realpath($matches[1]);
                }
                $aopFile = $this->options['cacheDir'] . '/' . $file;
                if (file_exists($aopFile)) {
                    $file = $aopFile;
                }
            }
            \Co::disableScheduler();
            include $file;
            \Co::enableScheduler();
        }
    }

    /**
     * @param string $class
     * @return false|string
     */
    public function findFile(string $class)
    {
        static $isAllowedFilter = null;
        if (!$isAllowedFilter) {
            $isAllowedFilter = $this->fileEnumerator->getFilter();
        }

        $file = $this->original->findFile($class);

        if ($file !== false) {
            $file = PathResolver::realpath($file) ?: $file;
            if ($isAllowedFilter(new \SplFileInfo($file))) {
                // can be optimized here with $cacheState even for debug mode, but no needed right now
                $file = FilterInjectorTransformer::rewrite($file);
            }
        }

        return $file;
    }

    /**
     * @return Enumerator
     */
    public function getEnumerator(): Enumerator
    {
        return $this->fileEnumerator;
    }
}
