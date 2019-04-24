<?php


namespace rabbit\aop;


use Go\Core\AspectContainer;
use Go\Core\AspectKernel;

/**
 * Class AbstractAopKernel
 * @package rabbit\aop
 */
abstract class AbstractAopKernel extends AspectKernel
{
    /**
     * @var array
     */
    protected $aspects = [];

    /**
     * @param array $aspects
     * @return AbstractAopKernel
     */
    public function setAspects(array $aspects): self
    {
        $this->aspects = $aspects;
        return $this;
    }

    /**
     * @param AspectContainer $container
     */
    protected function configureAop(AspectContainer $container)
    {
        foreach ($this->aspects as $aspect) {
            $container->registerAspect($aspect);
        }
    }

}