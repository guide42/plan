<?php

namespace plan;

use Exception;
use ArrayIterator;
use IteratorAggregate;

/**
 * Collection of Invalid exceptions.
 */
class InvalidList extends Exception implements IteratorAggregate
{
    /**
     * List of exceptions.
     *
     * @var array<Invalid|InvalidList>
     */
    protected $errors;

    /**
     * List of messages.
     *
     * @var array<string>
     */
    protected $messages;

    /**
     * @param array     $errors   are a list of `\plan\Invalid` exceptions
     * @param Exception $previous previous exception
     */
    public function __construct(array $errors, Exception $previous = null)
    {
        /**
         * Extracts error message.
         *
         * @param Invalid $error the exception
         *
         * @return string
         */
        $extract = function(Invalid $error)
        {
            return $error->getMessage();
        };

        $this->errors = $errors;
        $this->messages = array_map($extract, $this->errors);

        parent::__construct(implode(', ', $this->messages), null, $previous);
    }

    /**
     * Retrieve a list of Invalid errors. The returning array will have one
     * level deep only.
     *
     * @return array<Invalid>
     */
    public function getFlatErrors()
    {
        /**
         * Reducer that flat the errors.
         *
         * @param array   $carry previous error list
         * @param Invalid $item  to append to $carry
         *
         * @return array<Invalid>
         */
        $reduce = function(array $carry, Invalid $item)
        {
            if ($item instanceof InvalidList) {
                $carry = array_merge($carry, $item->getFlatErrors());
            } else {
                $carry[] = $item;
            }

            return $carry;
        };

        return iterator_to_array(array_reduce($this->errors, $reduce, []));
    }

    /**
     * Retrieve a list of error messages.
     *
     * @return array<string>
     */
    public function getMessages()
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
    protected $params = [];

    /**
     * Path from the root to the exception.
     *
     * @var array<string>
     */
    protected $path = [];

    /**
     * @param string    $template template for final message
     * @param array     $params   parameters to the template
     * @param string    $code     error identity code
     * @param Exception $previous previous exception
     * @param array     $path     list of indexes/keys inside the tree
     */
    public function __construct(
        string $template,
        array $params = null,
        string $code = null,
        Exception $previous = null,
        array $path = null
    ) {
        if (!empty($params) && !util\is_sequence($params)) {
            $message = strtr($template, $params);
        } else {
            $message = $template;
        }

        parent::__construct($message, $code, $previous);

        $this->template = $template;
        $this->params = is_null($params) ? [] : $params;
        $this->path = is_null($path) ? [] : $path;
    }

    /**
     * Retrieve the path.
     *
     * @return array<string>
     */
    public function getPath()
    {
        return array_values($this->path);
    }
}
