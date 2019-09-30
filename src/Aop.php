<?php


namespace rabbit\aop;

use Go\Instrument\Transformer\StreamMetaData;
use rabbit\helper\ClassHelper;
use rabbit\helper\FileHelper;

/**
 * Class Aop
 * @package rabbit\aop
 */
class Aop
{
    /**
     * Aop constructor.
     * @param string $kernel
     * @param array $aspects
     * @param array $options
     */
    public function __construct(string $kernel, array $aspects, array $options)
    {
        /** @var AbstractAopKernel kernel */
        $kernel = $kernel::getInstance();
        $kernel->setAspects($aspects);
        if (!isset($options['cacheDir'])) {
            $options['cacheDir'] = sys_get_temp_dir();
        }
        $kernel->init($options);
        $this->bootStrap($options['cacheDir']);
    }

    /**
     *
     */
    private function bootStrap(string $cacheDir): void
    {
        $loaders = spl_autoload_functions();
        foreach ($loaders as $loader) {
            foreach ($loader as $item) {
                if ($item instanceof AopComposerLoader) {
                    if ($item->getIncludePath()) {
                        foreach ($item->getEnumerator()->enumerate() as $file) {
                            $contents = file_get_contents($file);
                            $class = ClassHelper::getClassByString($contents);
                            if (!empty($class)) {
                                $aopFile = $item->findFile($class);
                                if (strpos($aopFile, 'php://') === 0) {
                                    if (($fp = fopen($file, 'r')) === false) {
                                        throw new \InvalidArgumentException("Unable to open file: {$fileName}");
                                    }
                                    $context = fread($fp, filesize($file));
                                    $metadata = new StreamMetaData($fp, $context);
                                    fclose($fp);
                                    SourceTransformingLoader::transformCode($metadata);
                                    $context = $metadata->source;
                                    $aopClass = $this->getClassByString($context);
                                    if (strpos($aopClass, '__AopProxied') !== false) {
                                        $dir = $cacheDir . '/' . $file->getPathname();
                                        FileHelper::createDirectory(dirname($dir), 0777);
                                        $len = file_put_contents($dir,
                                            $context);
                                        if (!$len) {
                                            new \InvalidArgumentException("Unable to write file: {$dir}");
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
    }
}