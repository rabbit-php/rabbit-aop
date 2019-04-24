<?php


namespace rabbit\aop;

/**
 * Class Aop
 * @package rabbit\aop
 */
class Aop
{
    /** @var AbstractAopKernel */
    private $kernel;

    /**
     * Aop constructor.
     * @param string $kernel
     * @param array $aspects
     * @param array $options
     */
    public function __construct(string $kernel, array $aspects, array $options)
    {
        /** @var AbstractAopKernel kernel */
        $this->kernel = $kernel::getInstance();
        $this->kernel->setAspects($aspects);
        $this->kernel->init($options);
    }

    /**
     * @return AbstractAopKernel
     */
    public function getKernel(): AbstractAopKernel
    {
        return $this->kernel;
    }
}