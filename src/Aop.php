<?php

declare(strict_types=1);

namespace Rabbit\Aop;

use Exception;
use Go\Core\AspectContainer;
use InvalidArgumentException;
use Rabbit\Base\Helper\FileHelper;

/**
 * Class Aop
 * @package Rabbit\Aop
 */
class Aop
{
    /**
     * Aop constructor.
     * @param array $aspects
     * @param array $options
     * @throws Exception
     */
    public function __construct(array $aspects, array $options)
    {
        $kernel = AopAspectKernel::getInstance();
        $kernel->setAspects($aspects);
        if (!isset($options['cacheDir'])) {
            $options['cacheDir'] = sys_get_temp_dir();
        }
        $options['cacheDir'] .= '/aop';
        if (isset($options['auto_clear']) && $options['auto_clear'] === true) {
            FileHelper::removeDirectory($options['cacheDir']);
        }

        $kernel->init($options);
        $this->bootStrap($options['cacheDir']);
    }

    /**
     * @param string $cacheDir
     * @throws Exception
     */
    private function bootStrap(string $cacheDir): void
    {
        $loaders = spl_autoload_functions();
        foreach ($loaders as $loader) {
            foreach ($loader as $item) {
                if ($item instanceof AopComposerLoader) {
                    if ($item->getIncludePath()) {
                        foreach ($item->getEnumerator()->enumerate() as $file) {
                            $fileName = $file->getPathname();
                            if (($fp = fopen($fileName, 'r')) === false) {
                                throw new InvalidArgumentException("Unable to open file: {$fileName}");
                            }
                            $contents = fread($fp, filesize($fileName));
                            $metadata = new StreamMetaData($fp, $contents);
                            fclose($fp);
                            $class = $this->getClassByString($contents);
                            if (!empty($class)) {
                                $aopFile = $item->findFile($class);
                                if ($aopFile !== false && strpos($aopFile, 'php://') === 0) {
                                    SourceTransformingLoader::transformCode($metadata);
                                    $contents = $metadata->source;
                                    $aopClass = $this->getClassByString($contents);
                                    if (strpos($aopClass, AspectContainer::AOP_PROXIED_SUFFIX) !== false) {
                                        $dir = $cacheDir . '/' . $file->getPathname();
                                        FileHelper::createDirectory(dirname($dir), 0777);
                                        $len = file_put_contents(
                                            $dir,
                                            $contents
                                        );
                                        if (!$len) {
                                            new InvalidArgumentException("Unable to write file: {$dir}");
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

    /**
     * @param string $contents
     * @return string
     */
    public function getClassByString(string $contents): string
    {
        //Start with a blank namespace and class
        $namespace = $class = "";

        //Set helper values to know that we have found the namespace/class token and need to collect the string values after them
        $getting_namespace = $getting_class = false;

        //Go through each token and evaluate it as necessary
        foreach (token_get_all($contents) as $token) {

            //If this token is the namespace declaring, then flag that the next tokens will be the namespace name
            if (is_array($token) && $token[0] === T_NAMESPACE) {
                $getting_namespace = true;
            }

            //If this token is the class declaring, then flag that the next tokens will be the class name
            if (is_array($token) && $token[0] === T_CLASS) {
                $getting_class = true;
            }

            //While we're grabbing the namespace name...
            if ($getting_namespace === true) {

                //If the token is a string or the namespace separator...
                if (is_array($token) && in_array($token[0], [T_STRING, T_NS_SEPARATOR, 314])) {

                    //Append the token's value to the name of the namespace
                    $namespace .= $token[1];
                } else {
                    if ($token === ';') {

                        //If the token is the semicolon, then we're done with the namespace declaration
                        $getting_namespace = false;
                    }
                }
            }

            //While we're grabbing the class name...
            if ($getting_class === true) {

                //If the token is a string, it's the name of the class
                if (is_array($token) && $token[0] === T_STRING) {

                    //Store the token's value as the class name
                    $class = $token[1];

                    //Got what we need, stope here
                    break;
                }
            }
        }

        //Build the fully-qualified class name and return it
        return $namespace ? $namespace . '\\' . $class : $class;
    }
}
