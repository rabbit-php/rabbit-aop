<?php


namespace rabbit\aop;

use Go\Instrument\Transformer\StreamMetaData;
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
        $this->init($options['cacheDir']);
    }

    /**
     *
     */
    private function init(string $cacheDir): void
    {
        $loaders = spl_autoload_functions();
        foreach ($loaders as $loader) {
            foreach ($loader as $item) {
                if ($item instanceof AopComposerLoader) {
                    foreach ($item->getEnumerator()->enumerate() as $file) {
                        $class = $this->getClassByFile($file);
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

    /**
     * @param string $file
     * @return string
     */
    private function getClassByFile(string $file): string
    {
        //Grab the contents of the file
        $contents = file_get_contents($file);
        return $this->getClassByString($contents);
    }

    /**
     * @param string $contents
     * @return string
     */
    private function getClassByString(string $contents): string
    {
        //Start with a blank namespace and class
        $namespace = $class = "";

        //Set helper values to know that we have found the namespace/class token and need to collect the string values after them
        $getting_namespace = $getting_class = false;

        //Go through each token and evaluate it as necessary
        foreach (token_get_all($contents) as $token) {

            //If this token is the namespace declaring, then flag that the next tokens will be the namespace name
            if (is_array($token) && $token[0] == T_NAMESPACE) {
                $getting_namespace = true;
            }

            //If this token is the class declaring, then flag that the next tokens will be the class name
            if (is_array($token) && $token[0] == T_CLASS) {
                $getting_class = true;
            }

            //While we're grabbing the namespace name...
            if ($getting_namespace === true) {

                //If the token is a string or the namespace separator...
                if (is_array($token) && in_array($token[0], [T_STRING, T_NS_SEPARATOR])) {

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
                if (is_array($token) && $token[0] == T_STRING) {

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