<?php

namespace Go\Aop\Pointcut\DNF;

use Go\Aop\Pointcut\DNF\AST\Node;
use Go\Aop\Pointcut\DNF\AST\NodeType;
use Go\ParserReflection\ReflectionFileNamespace;

class SemanticAnalyzer implements SemanticAnalyzerInterface
{
    public function verifyTree(?Node $tree, \ReflectionClass|ReflectionFileNamespace $val)
    {
        if ($tree !== null && $tree->type === NodeType::IDENTIFIER && $tree->identifier !== null) {
            $parentClasses = $this->getParentClasses($val);

            return in_array($tree->identifier, $parentClasses, true)
                || $val->implementsInterface($tree->identifier);
        }

        if ($tree?->type === NodeType::AND) {
            return $this->verifyTree($tree->left, $val) && $this->verifyTree($tree->right, $val);
        }

        if ($tree->type === NodeType::OR) {
            return $this->verifyTree($tree->left, $val) || $this->verifyTree($tree->right, $val);
        }
    }

    /**
     * @param ReflectionFileNamespace|\ReflectionClass $val
     *
     * @return array
     */
    public function getParentClasses(ReflectionFileNamespace|\ReflectionClass $val): array
    {
        $parentClasses = [];
        while ($val->getParentClass()) {
            $parentClasses[] = $val->getParentClass()->getName();
            $val = $val->getParentClass();
        }

        return $parentClasses;
    }
}