<?php declare(strict_types=1);

namespace plan;

use Throwable;
use Exception;

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