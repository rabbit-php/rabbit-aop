<?php


namespace rabbit\aop;


use Go\Aop\Features;
use Go\Core\AspectContainer;
use Go\Core\AspectKernel;
use Go\Instrument\ClassLoading\SourceTransformingLoader;
use Go\Instrument\PathResolver;
use Go\Instrument\Transformer\CachingTransformer;
use Go\Instrument\Transformer\ConstructorExecutionTransformer;
use Go\Instrument\Transformer\FilterInjectorTransformer;
use Go\Instrument\Transformer\MagicConstantTransformer;
use Go\Instrument\Transformer\SelfValueTransformer;
use Go\Instrument\Transformer\WeavingTransformer;
use rabbit\aop\Transformers\MemCacheTransformer;
use rabbit\aop\Transformers\MemMagicConstantTransformer;
use rabbit\aop\Transformers\MemWeavingTransformer;
use rabbit\helper\ArrayHelper;

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

    /**
     * @param array $options
     * @return array
     */
    protected function normalizeOptions(array $options)
    {
        $options = array_replace($this->getDefaultOptions(), $options);

        if (ArrayHelper::getValue($options, 'cacheDir') !== null) {
            $options['cacheDir'] = PathResolver::realpath($options['cacheDir']);
            if (!$options['cacheDir']) {
                $options['excludePaths'][] = $options['cacheDir'];
            }
        }

        $options['excludePaths'][] = __DIR__ . '/../';
        $options['appDir'] = PathResolver::realpath($options['appDir']);
        $options['cacheFileMode'] = (int)$options['cacheFileMode'];
        $options['includePaths'] = PathResolver::realpath($options['includePaths']);
        $options['excludePaths'] = PathResolver::realpath($options['excludePaths']);

        return $options;
    }

    /**
     * @return array|\Closure|\Go\Instrument\Transformer\SourceTransformer[]
     */
    protected function registerTransformers()
    {
        $cacheManager = $this->getContainer()->get('aspect.cache.path.manager');
        $filterInjector = new FilterInjectorTransformer($this, SourceTransformingLoader::getId(), $cacheManager);
        $magicTransformer = !empty($this->options['cacheDir']) ? new MagicConstantTransformer($this) : new MemMagicConstantTransformer($this);
        $aspectKernel = $this;

        $sourceTransformers = function () use ($filterInjector, $magicTransformer, $aspectKernel, $cacheManager) {
            $transformers = [];
            if ($aspectKernel->hasFeature(Features::INTERCEPT_INITIALIZATIONS)) {
                $transformers[] = new ConstructorExecutionTransformer();
            }
            if ($aspectKernel->hasFeature(Features::INTERCEPT_INCLUDES)) {
                $transformers[] = $filterInjector;
            }
            $aspectContainer = $aspectKernel->getContainer();
            $transformers[] = new SelfValueTransformer($aspectKernel);
            $transformers[] = !empty($this->options['cacheDir']) ? new WeavingTransformer(
                $aspectKernel,
                $aspectContainer->get('aspect.advice_matcher'),
                $cacheManager,
                $aspectContainer->get('aspect.cached.loader')
            ) : new MemWeavingTransformer($aspectKernel,
                $aspectContainer->get('aspect.advice_matcher'),
                $aspectContainer->get('aspect.cached.loader'));
            $transformers[] = $magicTransformer;

            return $transformers;
        };

        return [
            AOP_CACHE_DIR ? new CachingTransformer($this, $sourceTransformers, $cacheManager) : new MemCacheTransformer($this, $sourceTransformers, $cacheManager)
        ];
    }
}