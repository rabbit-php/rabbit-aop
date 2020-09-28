<?php

declare(strict_types=1);

namespace Rabbit\Aop;

use PhpParser\Lexer;
use InvalidArgumentException;
use Go\Instrument\PathResolver;
use Go\ParserReflection\ReflectionEngine;
use Go\Instrument\Transformer\StreamMetaData as TransformerStreamMetaData;

class StreamMetaData extends TransformerStreamMetaData
{
    private static array $propertyMap = [
        'stream_type'  => 'streamType',
        'wrapper_type' => 'wrapperType',
        'wrapper_data' => 'wrapperData',
        'filters'      => 'filterList',
        'uri'          => 'uri',
    ];

    public function __construct($stream, $source = null)
    {
        if (!is_resource($stream)) {
            throw new InvalidArgumentException('Stream should be valid resource');
        }
        $metadata = stream_get_meta_data($stream);
        if (preg_match('/resource=(.+)$/', $metadata['uri'], $matches)) {
            $metadata['uri'] = PathResolver::realpath($matches[1]);
        }
        foreach ($metadata as $key => $value) {
            if (!isset(self::$propertyMap[$key])) {
                continue;
            }
            $mappedKey = self::$propertyMap[$key];
            $this->$mappedKey = $value;
        }
        $this->syntaxTree = ReflectionEngine::parseFile($this->uri, $source);
        $this->setSource($source);
    }

    private function setSource($newSource)
    {
        $lexer = new Lexer(['usedAttributes' => [
            'comments',
            'startLine',
            'endLine',
            'startTokenPos',
            'endTokenPos',
            'startFilePos',
            'endFilePos'
        ]]);
        $lexer->startLexing($newSource);
        $rawTokens = $lexer->getTokens();
        foreach ($rawTokens as $index => $rawToken) {
            $this->tokenStream[$index] = \is_array($rawToken) ? $rawToken : [T_STRING, $rawToken];
        }
    }
}
