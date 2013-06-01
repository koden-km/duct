<?php
namespace Icecave\Duct;

use Icecave\Duct\TypeCheck\TypeCheck;

/**
 * A JSON token produced by the lexer.
 */
class Token
{
    /**
     * @param TokenType $type  The type of this token.
     * @param mixed     $value The token's value.
     */
    public function __construct(TokenType $type, $value)
    {
        $this->typeCheck = TypeCheck::get(__CLASS__, func_get_args());

        $this->type = $type;
        $this->value = $value;
    }

    /**
     * Create a 'special' token.
     *
     * Special tokens are the meaningful JSON characters outside of primitive values, such as braces, brackets, etc.
     *
     * @param string $value The character of the special token.
     *
     * @return Token The resulting token.
     */
    public static function createSpecial($value)
    {
        TypeCheck::get(__CLASS__)->createSpecial(func_get_args());

        return new Token(TokenType::instanceByValue($value), $value);
    }

    /**
     * Create a token that represents a literal value.
     *
     * Literal values are the primitive values that may be expressed directly in the JSON grammar, such as strings, boolean, numbers, etc.
     *
     * @param mixed $value The literal value.
     *
     * @return Token The resulting token.
     */
    public static function createLiteral($value)
    {
        TypeCheck::get(__CLASS__)->createLiteral(func_get_args());

        if (is_integer($value) || is_float($value)) {
            return new Token(TokenType::NUMBER_LITERAL(), $value);
        } elseif (is_bool($value)) {
            return new Token(TokenType::BOOLEAN_LITERAL(), $value);
        } elseif (is_null($value)) {
            return new Token(TokenType::NULL_LITERAL(), null);
        } else {
            return new Token(TokenType::STRING_LITERAL(), strval($value));
        }
    }

    /**
     * Fetch the type of the token.
     *
     * @return TokenType The type of the token.
     */
    public function type()
    {
        $this->typeCheck->type(func_get_args());

        return $this->type;
    }

    /**
     * Fetch the value of the token.
     *
     * @return mixed The value of the token.
     */
    public function value()
    {
        $this->typeCheck->value(func_get_args());

        return $this->value;
    }

    private $typeCheck;
    private $type;
    private $value;
}
