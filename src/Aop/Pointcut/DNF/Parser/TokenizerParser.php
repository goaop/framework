<?php

namespace Go\Aop\Pointcut\DNF\Parser;

use Exception;
use Go\Aop\Pointcut\DNF\AST\Node;
use Go\Aop\Pointcut\DNF\AST\NodeType;
use PhpToken;

class TokenizerParser implements TokenizerParserInterface
{
    public function parse(string $input): Node
    {
        return $this->parseExpression($this->tokenize($input));
    }

    private function tokenize(string $input): TokenCollection
    {
        $input = sprintf('<?php %s%s', $input, chr(/* EOF */26));
        $tokens = new \ArrayIterator(PhpToken::tokenize($input));
        $arrayIntersect = array_intersect(
            (array)$tokens,
            ['string', 'bool', 'array', 'float', 'int', 'integer', 'null', 'resource']
        );
        if ($arrayIntersect !== []) {
            throw new Exception(sprintf('Tokens [%s] not allowed', implode(', ', $arrayIntersect)));
        }

        $tokens = new TokenCollection($tokens);
        //skip '<?php'
        $tokens->next();

        return $tokens;
    }

    private function parseSubExpression(
        TokenCollection $tokens,
        int $bindingPower,
        bool $insideParenthesis = false
    ): ?Node {
        [$token, $val] = $tokens->next();
        switch ($token) {
            case Token::LPAREN:
                $left = $this->parseSubexpression($tokens, 0, true);
                $tokens->expect(Token::RPAREN);
                break;
            case Token::IDENTIFIER:
                $left = new Node(NodeType::IDENTIFIER, $val);
                break;
            default:
                throw new Exception('Bad Token');
        }

        while (true) {
            [$token] = $tokens->peek(0);

            if ($token === Token::OR && $insideParenthesis) {
                throw new \Exception('Only intersections allowed in the group');
            }

            switch ($token) {
                case Token::OR:
                case Token::AND:
                    [$leftBP, $rightBP] = $this->getBindingPower($token);
                    if ($leftBP < $bindingPower) {
                        break 2;
                    }
                    $tokens->next();
                    $right = $this->parseSubexpression($tokens, $rightBP, $insideParenthesis);
                    $left = $this->operatorNode($token, $left, $right);
                    break;
                case Token::RPAREN:
                case Token::EOF:
                default:
                    break 2;
            }
        }

        return $left;
    }

    private function parseExpression(TokenCollection $tokens): ?Node
    {
        $sub = $this->parseSubExpression($tokens, 0);
        $tokens->expect(Token::EOF);

        return $sub;
    }

    private function operatorNode(Token $type, Node $left, ?Node $right): Node
    {
        return match ($type) {
            Token::OR => new Node(NodeType::OR, left: $left, right: $right),
            Token::AND => new Node(NodeType::AND, left: $left, right: $right),
            default => throw new Exception('invalid op')
        };
    }

    /**
     * @param Token $type
     *
     * @return array{0: int, 1: int}
     */
    private function getBindingPower(Token $type): array
    {
        return match ($type) {
            Token::OR => [1, 2],
            Token::AND => [3, 4],
            default => throw new Exception('Invalid operator')
        };
    }

}