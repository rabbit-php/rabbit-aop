<?php


namespace rabbit\aop;

/**
 * Class FunctionProxy
 * @package rabbit\aop
 */
class FunctionProxy extends \Go\Proxy\FunctionProxy
{
    public function __toString()
    {
        $functionsCode = (
            $this->namespace->getDocComment() . "\n" . // Doc-comment for file
            'namespace ' . // 'namespace' keyword
            $this->namespace->getName() . // Name
            ";\n" . // End of namespace name
            implode("\n", $this->functionsCode) // Function definitions
        );

        return $functionsCode
            // Inject advices on call
            . PHP_EOL
            . '\\' . __CLASS__ . "::injectJoinPoints('"
            . $this->namespace->getName() . "',"
            . var_export($this->advices, true) . ');';
    }
}