<?php

declare(strict_types=1);

namespace Rabbit\Aop;

use Go\Aop\Aspect;
use Go\Core\AspectContainer;
use Go\Core\AspectLoader;
use Go\Core\AspectLoaderExtension;

/**
 * Class MemCachedAspectLoader
 * @package Rabbit\Aop
 */
class MemCachedAspectLoader extends AspectLoader
{
    /**
     * Identifier of original loader
     *
     * @var string
     */
    protected string $loaderId;

    /**
     * Cached loader constructor
     *
     * @param AspectContainer $container Instance of container
     * @param string $loaderId Original loader identifier
     */
    public function __construct(AspectContainer $container, string $loaderId)
    {
        $this->loaderId = $loaderId;
        $this->container = $container;
    }

    /**
     * {@inheritdoc}
     */
    public function load(Aspect $aspect): array
    {
        return $this->loader->load($aspect);
    }

    /**
     * {@inheritdoc}
     */
    public function registerLoaderExtension(AspectLoaderExtension $loader): void
    {
        $this->loader->registerLoaderExtension($loader);
    }

    /**
     * @param $name
     * @return mixed
     */
    public function __get($name)
    {
        if ($name === 'loader') {
            $this->loader = $this->container->get($this->loaderId);

            return $this->loader;
        }
        throw new \RuntimeException('Not implemented');
    }
}
