<?php

namespace Modera\ServerCrudBundle\Validation;

/**
 * Class should be used to report validation errors.
 *
 * @author    Sergei Lissovski <sergei.lissovski@modera.org>
 * @copyright 2013 Modera Foundation
 */
class ValidationResult
{
    /**
     * @var array<string, string[]>
     */
    private $fieldErrors = [];

    /**
     * @var string[]
     */
    private $generalErrors = [];

    /**
     * Adds a field related error.
     */
    public function addFieldError(string $fieldName, string $error): void
    {
        if (!isset($this->fieldErrors[$fieldName])) {
            $this->fieldErrors[$fieldName] = [];
        }

        $this->fieldErrors[$fieldName][] = $error;
    }

    /**
     * You can use this method to report some general error ( error that is associated with no fields or associated
     * to several ones at the same time, and you don't want to show same error message for several fields ).
     */
    public function addGeneralError(string $error): void
    {
        $this->generalErrors[] = $error;
    }

    /**
     * @return string[]
     */
    public function getFieldErrors(string $fieldName): array
    {
        return $this->fieldErrors[$fieldName] ?? [];
    }

    /**
     * @return string[]
     */
    public function getGeneralErrors(): array
    {
        return $this->generalErrors;
    }

    /**
     * @return array{
     *     'field_errors'?: array<string, string[]>,
     *     'general_errors'?: string[],
     * }
     */
    public function toArray(): array
    {
        $result = [];

        if (\count($this->fieldErrors)) {
            $result['field_errors'] = $this->fieldErrors;
        }
        if (\count($this->generalErrors)) {
            $result['general_errors'] = $this->generalErrors;
        }

        return $result;
    }

    public function hasErrors(): bool
    {
        $array = $this->toArray();

        return isset($array['field_errors']) || isset($array['general_errors']);
    }
}
