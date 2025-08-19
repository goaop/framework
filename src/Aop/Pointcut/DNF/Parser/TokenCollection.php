<?php

namespace Go\Aop\Pointcut\DNF\Parser;
class TokenCollection implements TokenCollectionInterface
{
    /**
     * @param \ArrayIterator<\PhpToken> $tokens
     */
    public function __construct(
        private readonly \ArrayIterator $tokens
    ) {
    }

    /**
     * @inheritDoc
     */
    public function expect(Token $token): void
    {
        /** @var Token $nextToken */
        [$nextToken] = $this->next();

        if ($nextToken !== $token) {
            throw new \Exception(sprintf('Expected %s, but got %s', $token->name, $nextToken->name));
        }
    }

    /**
     * @inheritDoc
     */
    public function next(): array
    {
        if (! $this->tokens->valid()) {
            return [Token::EOF, null];
        }

        $val = trim($this->tokens->current()->text);

        if ($val === '') {
            return $this->next();
        }

        $return = [$this->getToken($val), $val];
        $this->tokens->next();

        return $return;
    }

    /**
     * @inheritDoc
     */
    public function peek(int $i): array
    {
        $offset = $this->tokens->key() + $i;
        if (! $this->tokens->offsetExists($offset)) {
            return [Token::EOF, null];
        }

        $val = trim($this->tokens->offsetGet($offset)->text);

        return [$this->getToken($val), $val];
    }

    private function getToken(string $val): Token
    {
        return match ($val) {
            chr(26) => Token::EOF,
            '(' => Token::LPAREN,
            ')' => Token::RPAREN,
            '|' => Token::OR,
            '&' => Token::AND,
            default => Token::IDENTIFIER
        };
    }
}
