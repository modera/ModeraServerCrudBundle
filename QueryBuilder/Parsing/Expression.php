<?php

namespace Modera\ServerCrudBundle\QueryBuilder\Parsing;

/**
 * @author Sergei Vizel <sergei.vizel@modera.org>
 * @copyright 2024 Modera Foundation
 */
class Expression
{
    private ?string $alias = null;

    private ?string $function = null;

    /**
     * @var array<int, string|Expression>
     */
    private array $functionArgs = [];

    /**
     * @var string|array<string, ?string>[]|array{
     *     'function'?: string,
     *     'args'?: array<string|array<string, mixed>>,
     *     'hidden'?: bool,
     * }
     */
    private $expression;

    private bool $hidden;

    /**
     * @param string|array<string, ?string>[]|array{
     *     'function'?: string,
     *     'args'?: array<string|array<string, mixed>>,
     *     'hidden'?: bool,
     * } $expression
     * @param ?string $alias Alias should only be provided when expression is used in SELECT
     */
    public function __construct($expression, ?string $alias = null)
    {
        if (\is_array($expression) && \is_string($expression['function'] ?? null) && \strlen($expression['function']) > 0) {
            $this->validateFunctionName($expression['function']);
            $this->function = $expression['function'];

            if (\is_array($expression['args'] ?? null)) {
                /** @var string|array{
                 *     'function'?: string,
                 *     'args'?: array<string|array<string, mixed>>,
                 *     'hidden'?: bool,
                 * } $arg */
                foreach ($expression['args'] as $arg) {
                    if (\is_array($arg)) {
                        $this->functionArgs[] = new self($arg);
                    } else {
                        $this->functionArgs[] = $arg;
                    }
                }
            }
        }

        $this->hidden = \is_array($expression) && isset($expression['hidden']) && $expression['hidden'];

        $this->expression = $expression;

        if (\is_string($alias) && \strlen($alias) > 1
            && \preg_match('/^\w+$/', $alias[0]) && \preg_match('/^[\w0-9_]+$/', $alias)) {
            $this->alias = $alias;
        }
    }

    private function validateFunctionName(string $functionName): void
    {
        if (!\preg_match('/^[\w_0-9]+$/', $functionName)) {
            throw new \RuntimeException("'$functionName' is not a properly formatted function name!");
        }
    }

    /**
     * @return string|array<string, ?string>[]|array{
     *     'function'?: string,
     *     'args'?: array<string|array<string, mixed>>,
     *     'hidden'?: bool,
     * }
     */
    public function getExpression()
    {
        return $this->expression;
    }

    public function isReference(): bool
    {
        $exp = $this->getExpression();

        return \is_string($exp) && ':' === $exp[0];
    }

    public function isHidden(): bool
    {
        return $this->hidden;
    }

    public function getAlias(): ?string
    {
        return $this->alias;
    }

    public function getFunction(): ?string
    {
        return $this->function;
    }

    /**
     * @return array<int, string|Expression>
     */
    public function getFunctionArgs(): array
    {
        return $this->functionArgs;
    }
}
