<?php
namespace Icecave\Duct\Detail;

use Evenement\EventEmitter;
use SplStack;
use stdClass;

/**
 * Streaming token parser.
 *
 * Converts incoming streams of JSON tokens into PHP values.
 */
class TokenStreamParser extends EventEmitter
{
    public function __construct()
    {
        $this->reset();
    }

    /**
     * Reset the parser, discarding any previously parsed input and values.
     */
    public function reset()
    {
        $this->stack = new SplStack;
    }

    /**
     * Feed tokens to the parser.
     *
     * @param mixed<Token> $tokens The sequence of tokens.
     *
     * @throws Exception\ParserException
     */
    public function feed($tokens)
    {
        foreach ($tokens as $token) {
            $this->feedToken($token);
        }
    }

    /**
     * Finalize parsing.
     *
     * @throws Exception\ParserException Indicates that the token stream terminated midway through a JSON value.
     */
    public function finalize()
    {
        if (!$this->stack->isEmpty()) {
            throw new Exception\ParserException('Token stream ended unexpectedly.');
        }
    }

    /**
     * @param Token $token
     */
    public function feedToken(Token $token)
    {
        if (!$this->stack->isEmpty()) {
            switch ($this->stack->top()) {
                case ParserState::ARRAY_START:
                    return $this->doArrayStart($token);
                case ParserState::ARRAY_VALUE_SEPARATOR:
                    return $this->doArrayValueSeparator($token);
                case ParserState::OBJECT_START:
                    return $this->doObjectStart($token);
                case ParserState::OBJECT_KEY:
                    return $this->doObjectKey($token);
                case ParserState::OBJECT_KEY_SEPARATOR:
                    return $this->doObjectKeySeparator($token);
                case ParserState::OBJECT_VALUE_SEPARATOR:
                    return $this->doObjectValueSeparator($token);
            }
        }

        return $this->doValue($token);
    }

    /**
     * @param Token $token
     */
    private function doValue(Token $token)
    {
        switch ($token->type()->value()) {
            case TokenType::BRACE_OPEN:
                $this->push(ParserState::OBJECT_START);
                $this->emit('object-open');
                break;

            case TokenType::BRACKET_OPEN:
                $this->push(ParserState::ARRAY_START);
                $this->emit('array-open');
                break;

            case TokenType::STRING_LITERAL:
            case TokenType::BOOLEAN_LITERAL:
            case TokenType::NULL_LITERAL:
            case TokenType::NUMBER_LITERAL:
                $this->endValue();
                $this->emit('value', array($token->value()));
                break;

            default:
                throw $this->createUnexpectedTokenException($token);
        }
    }

    /**
     * @param Token $token
     */
    private function doObjectStart(Token $token)
    {
        if (TokenType::BRACE_CLOSE === $token->type()->value()) {
            $this->pop();
            $this->endValue();
            $this->emit('object-close');
        } else {
            $this->setState(ParserState::OBJECT_KEY);
            $this->feedToken($token);
        }
    }

    /**
     * @param Token $token
     */
    private function doObjectKey(Token $token)
    {
        if (TokenType::STRING_LITERAL !== $token->type()->value()) {
            throw $this->createUnexpectedTokenException($token);
        }

        $this->setState(ParserState::OBJECT_KEY_SEPARATOR);
        $this->emit('object-key', array($token->value()));
    }

    /**
     * @param Token $token
     */
    private function doObjectKeySeparator(Token $token)
    {
        if (TokenType::COLON !== $token->type()->value()) {
            throw $this->createUnexpectedTokenException($token);
        }

        $this->setState(ParserState::OBJECT_VALUE);
    }

    /**
     * @param Token $token
     */
    private function doObjectValueSeparator(Token $token)
    {
        if (TokenType::BRACE_CLOSE === $token->type()->value()) {
            $this->pop();
            $this->endValue();
            $this->emit('object-close');
        } elseif (TokenType::COMMA === $token->type()->value()) {
            $this->setState(ParserState::OBJECT_KEY);
        } else {
            throw $this->createUnexpectedTokenException($token);
        }
    }

    /**
     * @param Token $token
     */
    private function doArrayStart(Token $token)
    {
        if (TokenType::BRACKET_CLOSE === $token->type()->value()) {
            $this->pop();
            $this->endValue();
            $this->emit('array-close');
        } else {
            $this->setState(ParserState::ARRAY_VALUE);
            $this->feedToken($token);
        }
    }

    /**
     * @param Token $token
     */
    private function doArrayValueSeparator(Token $token)
    {
        if (TokenType::BRACKET_CLOSE === $token->type()->value()) {
            $this->pop();
            $this->endValue();
            $this->emit('array-close');
        } elseif (TokenType::COMMA === $token->type()->value()) {
            $this->setState(ParserState::ARRAY_VALUE);
        } else {
            throw $this->createUnexpectedTokenException($token);
        }
    }

    private function endValue()
    {
        if ($this->stack->isEmpty()) {
            return;
        } elseif (ParserState::ARRAY_VALUE === $this->stack->top()) {
            $this->setState(ParserState::ARRAY_VALUE_SEPARATOR);
        } elseif (ParserState::OBJECT_VALUE === $this->stack->top()) {
            $this->setState(ParserState::OBJECT_VALUE_SEPARATOR);
        }
    }

    /**
     * @param integer $state
     */
    private function setState($state)
    {
        $this->stack->pop();
        $this->stack->push($state);
    }

    /**
     * @param integer $state
     */
    private function push($state)
    {
        $this->stack->push($state);
    }

    /**
     * @return stdClass
     */
    private function pop()
    {
        $this->stack->pop();
    }

    /**
     * @param Token $token
     *
     * @return Exception\ParserException
     */
    private function createUnexpectedTokenException(Token $token)
    {
        if ($this->stack->isEmpty()) {
            return new Exception\ParserException('Unexpected token "' . $token->type() . '".');
        }

        return new Exception\ParserException(
            'Unexpected token "' . $token->type() . '" in state "' . ParserState::instanceByValue($this->stack->top()) . '".'
        );
    }

    private $stack;
}
