<?php

namespace Go\Aop\Pointcut\DNF\Parser;

interface TokenCollectionInterface
{
    /**
     * @throws \Exception
     */
    public function expect(Token $token): void;

    /**
     * @return array{0: Token, 1: string|null}
     */
    public function next(): array;

    /**
     * @return array{0: Token, 1: string|null}
     */
    public function peek(int $i): array;
}