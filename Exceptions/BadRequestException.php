<?php

namespace Modera\ServerCrudBundle\Exceptions;

/**
 * This exception can be thrown when an invalid request is received from client-side - when it doesn't have some mandatory
 * parameters, for instance.
 *
 * @author    Sergei Lissovski <sergei.lissovski@modera.org>
 * @copyright 2014 Modera Foundation
 */
class BadRequestException extends \RuntimeException
{
    /**
     * @var array<string, mixed>
     */
    private array $params = [];

    private string $path = '';

    /**
     * @param array<string, mixed> $params
     */
    public function setParams(array $params): void
    {
        $this->params = $params;
    }

    /**
     * @return array<string, mixed>
     */
    public function getParams(): array
    {
        return $this->params;
    }

    public function setPath(string $path): void
    {
        $this->path = $path;
    }

    public function getPath(): string
    {
        return $this->path;
    }
}
