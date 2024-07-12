<?php

namespace Modera\ServerCrudBundle\QueryBuilder\Parsing;

/**
 * @author Sergei Vizel <sergei.vizel@modera.org>
 * @copyright 2024 Modera Foundation
 */
class OrderExpression
{
    /**
     * @var array{
     *     'property'?: string,
     *     'direction'?: string,
     * }
     */
    private array $input;

    /**
     * @var array{
     *     'property': ?string,
     *     'direction': ?string,
     * }
     */
    private array $parsedInput;

    /**
     * @param array{
     *     'property'?: string,
     *     'direction'?: string,
     * } $input
     */
    public function __construct(array $input)
    {
        $this->input = $input;
        $this->parsedInput = $this->parse($input);
    }

    public function isValid(): bool
    {
        return \strlen($this->parsedInput['property'] ?? '') > 0
            && \in_array(\strtoupper($this->parsedInput['direction'] ?? ''), ['ASC', 'DESC']);
    }

    /**
     * @param array{
     *     'property'?: string,
     *     'direction'?: string,
     * } $input
     *
     * @return array{
     *     'property': ?string,
     *     'direction': ?string,
     * }
     */
    private function parse(array $input): array
    {
        $parsed = [
            'property' => null,
            'direction' => null,
        ];

        if (isset($input['property'])) {
            $parsed['property'] = $input['property'];
        }

        if (isset($input['direction'])) {
            $parsed['direction'] = $input['direction'];
        }

        return $parsed;
    }

    public function getProperty(): ?string
    {
        return $this->parsedInput['property'] ?? null;
    }

    public function getDirection(): ?string
    {
        return $this->parsedInput['direction'] ?? null;
    }

    /**
     * @return array{
     *     'property'?: string,
     *     'direction'?: string,
     * }
     */
    public function getInput(): array
    {
        return $this->input;
    }
}
