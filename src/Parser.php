<?php
namespace Icecave\Duct;

use Icecave\Duct\Detail\Lexer;
use Icecave\Duct\Detail\TokenStreamParser;
use SplStack;
use stdClass;

/**
 * Streaming JSON parser.
 *
 * Converts incoming streams of JSON data into PHP values.
 */
class Parser extends AbstractParser
{
    /**
     * @param Lexer|null             $lexer  The lexer to use for tokenization, or NULL to use the default UTF-8 lexer.
     * @param TokenStreamParser|null $parser The token-stream parser to use for converting tokens into PHP values, or null to use the default.
     */
    public function __construct(Lexer $lexer = null, TokenStreamParser $parser = null)
    {
        parent::__construct($lexer, $parser);

        $this->values = array();
        $this->stack = new SplStack;
    }

    /**
     * Reset the parser, discarding any previously parsed input and values.
     */
    public function reset()
    {
        parent::reset();

        $this->values = array();
        $this->stack = new SplStack;
    }

    /**
     * Parse one or more complete JSON values.
     *
     * @param string $buffer The JSON data.
     *
     * @return array<mixed>                       The sequence of parsed JSON values.
     * @throws Exception\SyntaxExceptionInterface
     */
    public function parse($buffer)
    {
        parent::parse($buffer);

        return $this->values();
    }

    /**
     * Fetch the values produced by the parser so far and remove them from the internal value sequence.
     *
     * @return array<mixed> The sequence of parsed JSON values.
     */
    public function values()
    {
        $values = $this->values;
        $this->values = array();

        return $values;
    }

    /**
     * Called when the token stream parser emits a 'value' event.
     *
     * @param mixed $value The value emitted.
     */
    protected function onValue($value)
    {
        if ($this->stack->isEmpty()) {
            $this->values[] = $value;
        } else {
            $context = $this->stack->top();

            if (is_array($context->value)) {
                $context->value[] = $value;
            } else {
                $context->value->{$context->key} = $value;
                $context->key = null;
            }
        }
    }

    /**
     * Called when the token stream parser emits an 'array-open' event.
     */
    protected function onArrayOpen()
    {
        $this->push(array());
    }

    /**
     * Called when the token stream parser emits an 'array-close' event.
     */
    protected function onArrayClose()
    {
        $this->pop();
    }

    /**
     * Called when the token stream parser emits an 'object-open' event.
     */
    protected function onObjectOpen()
    {
        $this->push(new stdClass);
    }

    /**
     * Called when the token stream parser emits an 'object-close' event.
     */
    protected function onObjectClose()
    {
        $this->pop();
    }

    /**
     * Called when the token stream parser emits an 'object-key' event.
     *
     * @param string $value The key for the next object value.
     */
    protected function onObjectKey($value)
    {
        $this->stack->top()->key = $value;
    }

    /**
     * Push a value onto the object stack.
     *
     * @param array|stdClass $value
     */
    protected function push($value)
    {
        $context = new stdClass;
        $context->value = $value;
        $context->key = null;

        $this->stack->push($context);
    }

    /**
     * Pop a value from the object stack, emitting it if the stack is empty.
     */
    protected function pop()
    {
        $context = $this->stack->pop();

        $this->onValue($context->value);
    }

    private $values;
    private $stack;
}
