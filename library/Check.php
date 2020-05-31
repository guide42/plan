<?php declare(strict_types=1);

namespace plan;

/**
 * Contract of the return value of `check` function.
 */
interface Check {
    /**
     * Return true when the check is valid, false otherwise.
     *
     * @return boolean
     */
    public function isValid(): bool;

    /**
     * Return check result when is valid, default otherwise.
     *
     * @return mixed
     */
    public function getResult($default = null);

    /**
     * Return a list of check errors.
     *
     * @return array<Invalid>
     */
    public function getErrors(): array;
}