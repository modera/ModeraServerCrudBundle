<?php

namespace Modera\ServerCrudBundle\QueryBuilder\Parsing;

/**
 * @author Sergei Vizel <sergei.vizel@modera.org>
 * @copyright 2024 Modera Foundation
 */
class OrFilter implements FilterInterface
{
    /**
     * @var array{'property'?: string, 'value'?: string|string[]}[]
     */
    private array $input;

    /**
     * @var ?Filter[]
     */
    private ?array $filters = null;

    private ?bool $isValid = null;

    /**
     * @param array{'property'?: string, 'value'?: string|string[]}[] $input
     */
    public function __construct(array $input)
    {
        $this->input = $input;
    }

    /**
     * @return Filter[]
     */
    public function getFilters(): array
    {
        if (null === $this->filters) {
            $this->filters = [];
            foreach ($this->input as $rawFilter) {
                $this->filters[] = new Filter($rawFilter);
            }
        }

        return $this->filters;
    }

    /**
     * @return bool Will return TRUE only if all aggregated filters are valid
     */
    public function isValid(): bool
    {
        if (null === $this->isValid) {
            $this->isValid = true;
            foreach ($this->getFilters() as $filter) {
                if (!$filter->isValid()) {
                    $this->isValid = false;
                }
            }
        }

        return $this->isValid;
    }

    /**
     * @return array{
     *     'property': string,
     *     'value': string,
     * }[] Only valid filters will be compiled
     */
    public function compile(): array
    {
        $result = [];
        foreach ($this->getFilters() as $filter) {
            if ($filter->isValid()) {
                $result[] = $filter->compile();
            }
        }

        return $result;
    }
}
