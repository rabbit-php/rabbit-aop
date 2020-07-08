<?php
declare(strict_types=1);

namespace Rabbit\Aop;

use Go\Instrument\Transformer\SourceTransformer;
use Go\Instrument\Transformer\StreamMetaData;
use RuntimeException;

/**
 * Class SourceTransformingLoader
 * @package rabbit\aop
 */
class SourceTransformingLoader
{
    /**
     * Php filter definition
     */
    const PHP_FILTER_READ = 'php://filter/read=';

    /**
     * Default PHP filter name for registration
     */
    const FILTER_IDENTIFIER = 'go.source.transforming.loader';

    /**
     * String buffer
     *
     * @var string
     */
    protected string $data = '';

    /**
     * List of transformers
     *
     * @var array|SourceTransformer[]
     */
    protected static array $transformers = [];

    /**
     * Identifier of filter
     *
     * @var string
     */
    protected static ?string $filterId = null;

    /**
     * Register current loader as stream filter in PHP
     *
     * @param string $filterId Identifier for the filter
     * @throws RuntimeException If registration was failed
     */
    public static function register(string $filterId = self::FILTER_IDENTIFIER): void
    {
        if (!empty(self::$filterId)) {
            throw new RuntimeException('Stream filter already registered');
        }
        self::$filterId = $filterId;
    }

    /**
     * Returns the name of registered filter
     *
     * @return string
     * @throws RuntimeException if filter was not registered
     */
    public static function getId(): string
    {
        if (empty(self::$filterId)) {
            throw new RuntimeException('Stream filter was not registered');
        }

        return self::$filterId;
    }

    /**
     * Adds a SourceTransformer to be applied by this LoadTimeWeaver.
     *
     * @param $transformer SourceTransformer Transformer for source code
     *
     * @return void
     */
    public static function addTransformer(SourceTransformer $transformer):void
    {
        self::$transformers[] = $transformer;
    }

    /**
     * Transforms source code by passing it through all transformers
     *
     * @param StreamMetaData|null $metadata Metadata from stream
     *
     * @return void
     */
    public static function transformCode(?StreamMetaData $metadata)
    {
        foreach (self::$transformers as $transformer) {
            $result = $transformer->transform($metadata);
            if ($result === SourceTransformer::RESULT_ABORTED) {
                break;
            }
        }
    }
}
