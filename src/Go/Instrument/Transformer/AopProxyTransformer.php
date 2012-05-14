<?php
/**
 * Go! OOP&AOP PHP framework
 *
 * @copyright     Copyright 2011, Lissachenko Alexander <lisachenko.it@gmail.com>
 * @license       http://www.opensource.org/licenses/mit-license.php The MIT License
 */

namespace Go\Instrument\Transformer;

/**
 * @package go
 */
class AopProxyTransformer implements \Go\Instrument\ClassFileTransformer
{
    /** Suffix, that will be added to all proxied class names */
    const AOP_PROXIED_SUFFIX = '__AopProxied';

    /**
     * This method may transform the supplied class file and return a new replacement class file
     *
     * @param string $className The name of the class to be transformed
     * @param array $classSourceTokens List of tokens for class
     * @return array Transformed list of tokens
     */
    public function transform($className, array $classSourceTokens)
    {
        echo "<pre>";
        $tokens = $classSourceTokens;
        $skeleton = $this->getClassSkeletonFromTokens($tokens);
        $tokens = array_filter($tokens, function($item) {
            return !(isset($item[1]) && $item[0] == T_FINAL);
        });
        $classTokenPositions = array_keys(array_filter($tokens, function($token) {
            return $token[0] == T_CLASS;
        }));
        $originalClassNames = array();
        foreach($classTokenPositions as $classTokenPosition) {
            $originalClassNames[] = $tokens[$classTokenPosition+2][1];
            $tokens[$classTokenPosition+2][1] = $tokens[$classTokenPosition+2][1] . self::AOP_PROXIED_SUFFIX;
        }
        //$this->data = $this->buildCodeFromTokens($tokens);
        //$this->data .= $this->addProxiesForClasses($originalClassNames, $skeleton);
        //echo htmlentities($this->data);
        print_r($skeleton);
        echo "</pre>";
        return $tokens;
    }

    /**
     * Returns code from tokens
     *
     * @param array $tokens List of tokens or code
     * @return string
     */
    private function buildCodeFromTokens(array $tokens)
    {
        $code = '';
        foreach ($tokens as $item) {
            $code .= is_array($item) ? $item[1] : $item;
        }
        return $code;
    }

    private function getClassSkeletonFromTokens(array $tokens)
    {
        $classes = $this->parseClassesFromTokens($tokens);
        foreach ($classes as &$classInfo) {
            $classInfo['methods'] = $this->parseMethodsFromTokens($classInfo['tokens']);
            $classInfo['definition'] = trim($this->buildCodeFromTokens($classInfo['definition']));
            unset($classInfo['tokens']);
            foreach($classInfo['methods'] as &$methodInfo) {
                $methodInfo['params'] = join(', ',$this->parseArgumentsFromMethod($methodInfo['definition']));
                $methodInfo['definition'] = trim($this->buildCodeFromTokens($methodInfo['definition']));
                unset($methodInfo['tokens']);
            }
        }
        return $classes;
    }

    private function parseClassesFromTokens(array $tokens)
    {
        static $beforeClassTokens = array(T_FINAL, T_ABSTRACT, T_WHITESPACE);
        return $this->getItemsFromTokens(T_CLASS, $tokens, $beforeClassTokens);
    }

    private function parseMethodsFromTokens(array $tokens)
    {
        static $beforeMethodTokens = array(
            T_FINAL, T_ABSTRACT, T_STATIC, T_PUBLIC, T_WHITESPACE,
            T_PUBLIC, T_PROTECTED, T_PRIVATE,
        );
        return $this->getItemsFromTokens(T_FUNCTION, $tokens, $beforeMethodTokens);
    }

    private function getItemsFromTokens($lookForToken, array $tokens, array $before, $withPrivateItems = false)
    {
        $items = array();
        $total = count($tokens);
        $isMethod = $lookForToken == T_FUNCTION;
        for ($index = 0; $index < $total; $index++) {
            $token = is_array($tokens[$index]) ? $tokens[$index] : array(-1, $tokens[$index]);
            list($tokenId) = $token;
            if ($tokenId == $lookForToken) {
                $start = $index;
                $end = $index + 1;
                $isAbstract = false; $isPrivate = false;
                while (--$start && is_array($tokens[$start]) && in_array($tokens[$start][0], $before)) {
                    switch ($tokens[$start][0]) {
                        case T_ABSTRACT:
                            $isAbstract = true;
                            break;

                        case T_PRIVATE:
                            $isPrivate = true;
                            break;
                    }
                };
                while ($tokens[++$end] != ( ($isAbstract && $isMethod) ? ';' : '{' )) ;
                $itemName = $tokens[$index + 2][1];
                $start++;
                $balancedCodeInBraces = ($isAbstract && $isMethod) ? array() : $this->getBalancedCodeInBraces($tokens, $end + 1);
                if (!$isPrivate || ($isPrivate && $withPrivateItems)) {
                    $items[$itemName] = array(
                        'definition' => array_slice($tokens, $start, $end - $start),
                        'tokens'     => $balancedCodeInBraces,
                    );
                }
                if ($balancedCodeInBraces) {
                    end($balancedCodeInBraces);
                    $index = key($balancedCodeInBraces) + $end;
                }
            }
        }
        return $items;
    }

    private function getBalancedCodeInBraces(array $tokens, $start)
    {
        $position = $start;
        $bracesCount = 1;
        do {
            $position++;
            $token = $tokens[$position];
            switch ($token) {
                case '{':
                    $bracesCount++; break;
                case '}':
                    $bracesCount--; break;
            }
        } while (isset($tokens[$position]) && $bracesCount > 0);
        return array_slice($tokens, $start, $position - $start);
    }

    private function parseArgumentsFromMethod(array $tokens)
    {
        $argumentTokens = array_filter($tokens, function($token) {
                return $token[0] === T_VARIABLE;
            });
        $arguments = array_map(function($token) {
                return $token[1];
            }, $argumentTokens);
        return $arguments;
    }

    private function addProxiesForClasses($originalClassNames, $skeleton)
    {
        $source = '';
        foreach ($originalClassNames as $originalClassName) {
            $source .= "\n class $originalClassName extends $originalClassName" . self::AOP_PROXIED_SUFFIX . "{}\n";
        }
        return $source;
    }
}
