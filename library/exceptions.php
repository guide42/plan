<?php declare(strict_types=1);

namespace plan;

use Throwable;
use Exception;
use ArrayIterator;
use IteratorAggregate;

/**
 * Base exception for errors thrown during assertion.
 */
class Invalid extends Exception
{
    /**
     * Message template.
     *
     * @var string
     */
    protected $template;

    /**
     * Parameters to message template.
     *
     * @var array<string, mixed>
     */
    protected $context;

    /**
     * Path from the root to the exception.
     *
     * @var array<string>
     */
    protected $path;

    /**
     * @param string    $template template for final message
     * @param array     $context  parameters to the template
     * @param array     $path     list of indexes/keys inside the tree
     * @param string    $code     error identity code
     * @param Throwable $previous previous exception
     */
    public function __construct(
        string $template,
        array $context = null,
        array $path = null,
        int $code = 0,
        Throwable $previous = null
    ) {
        if (empty($context)) {
            $message = $template;
        } else {
            $replace = array_combine(
                array_map(
                    function($k) {
                        return "{{$k}}";
                    },
                    array_keys($context)
                ),
                array_values($context)
            );
            $message = strtr($template, $replace);
        }

        if ($previous) {
            $message .= ': ' . $previous->getMessage();
        }

        parent::__construct($message, $code, $previous);

        $this->template = $template;
        $this->context = $context;
        $this->path = $path;
    }

    /**
     * Retrieve the depth of the exception in the schema tree.
     *
     * @return int
     */
    public function getDepth(): int
    {
        if ($this->getPath()) {
            return count($this->getPath());
        }
        return 0;
    }

    /**
     * Retrieve template.
     *
     * @return string
     */
    public function getTemplate(): string
    {
        return $this->template;
    }

    /**
     * Retrieve template parameters.
     *
     * @return array<string, mixed>
     */
    public function getContext(): ?array
    {
        return $this->context;
    }

    /**
     * Retrieve the path.
     *
     * @return array<string>
     */
    public function getPath(): ?array
    {
        return $this->path;
    }
}

/**
 * Collection of `Invalid` exceptions.
 */
class MultipleInvalid extends Invalid implements IteratorAggregate
{
    /**
     * List of exceptions.
     *
     * @var array<Invalid>
     */
    protected $errors;

    /**
     * List of messages.
     *
     * @var array<string>
     */
    protected $messages;

    /**
     * @param array     $errors   many `Invalid` exceptions
     * @param array     $path     list of indexes/keys inside the tree
     * @param string    $code     error identity code
     * @param Throwable $previous previous exception
     */
    public function __construct(
        array $errors,
        array $path = null,
        int $code = 0,
        Throwable $previous = null
    ) {
        /**
         * Extracts error message.
         *
         * @param Invalid $error the exception
         *
         * @return string
         */
        $extract = function(Invalid $error) {
            return $error->getMessage();
        };

        $this->errors = $errors;
        $this->messages = array_map($extract, $this->errors);

        $ctx = [
            'length' => count($this->errors),
            'messages' => implode(', ', $this->messages),
        ];

        if (util\is_sequence($this->errors)) {
            $template = '[ {messages} ]';
        } else {
            $template = '{ {messages} }';
        }

        parent::__construct($template, $ctx, $path, $code, $previous);
    }

    /**
     * Calculate the maximum depth between its errors.
     *
     * @return int
     */
    public function getDepth(): int
    {
        $depth = parent::getDepth();
        $paths = array_filter(array_map(
            /**
             * Extracts error path.
             *
             * @param Invalid $error the exception
             *
             * @return array|null
             */
            function(Invalid $error) {
                return $error->getPath();
            },
            $this->errors
        ));

        if ($paths && ($max = max(array_map('count', $paths))) > $depth) {
            $depth = $max;
        }

        return $depth;
    }

    /**
     * Retrieve a list of `Invalid` errors. The returning array will
     * have one level deep only.
     *
     * @return array<Invalid>
     */
    public function getFlatErrors(): array
    {
        /**
         * Reducer that flat the errors.
         *
         * @param array   $carry previous error list
         * @param Invalid $item  to append to $carry
         *
         * @return array<Invalid>
         */
        $reduce = function(array $carry, Invalid $item) use(&$reduce) {
            if ($item instanceof self) {
                $carry = array_merge($carry, $item->getFlatErrors());
            } else {
                $carry[] = $item;
            }
            if ($item->getPrevious()) {
                $carry = $reduce($carry, $item->getPrevious());
            }

            return $carry;
        };

        return array_reduce($this->errors, $reduce, []);
    }

    /**
     * Retrieve the raw list of exceptions.
     *
     * @var array<Invalid>
     */
    public function getErrors()
    {
        return $this->errors;
    }

    /**
     * Retrieve a list of error messages.
     *
     * @return array<string>
     */
    public function getMessages(): array
    {
        return $this->messages;
    }

    /**
     * (non-PHPdoc)
     * @see IteratorAggregate::getIterator()
     */
    public function getIterator()
    {
        return new ArrayIterator($this->errors);
    }
}
