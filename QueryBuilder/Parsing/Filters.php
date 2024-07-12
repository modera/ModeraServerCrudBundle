<?php

namespace Modera\ServerCrudBundle\QueryBuilder\Parsing;

/**
 * @author Sergei Vizel <sergei.vizel@modera.org>
 * @copyright 2024 Modera Foundation
 */
class Filters implements \Iterator, \Countable, \ArrayAccess
{
    /**
     * @var array<Filter|OrFilter>
     */
    private array $filters = [];

    private int $iteratorIndex = 0;

    /**
     * @param array<mixed[]> $filters
     */
    public function __construct(array $filters)
    {
        foreach ($filters as $rawFilter) {
            if ($this->isOrFilterDefinition($rawFilter)) {
                /** @var array{'property'?: string, 'value'?: string|string[]}[] $orFilter */
                $orFilter = $rawFilter;
                $this->addOrFilter(new OrFilter($orFilter));
            } else {
                /** @var array{'property'?: string, 'value'?: string|string[]} $filter */
                $filter = $rawFilter;
                $this->addFilter(new Filter($filter));
            }
        }
    }

    /**
     * @param mixed[] $rawFilter
     */
    private function isOrFilterDefinition(array $rawFilter): bool
    {
        return !isset($rawFilter['property']) && !isset($rawFilter['value']);
    }

    /**
     * @return Filter[]
     */
    public function findByProperty(string $property): array
    {
        $result = [];

        foreach ($this->filters as $filter) {
            if ($filter instanceof Filter && $filter->getProperty() === $property) {
                $result[] = $filter;
            }
        }

        return $result;
    }

    public function findOneByPropertyAndComparator(string $property, string $comparator): ?Filter
    {
        foreach ($this->filters as $filter) {
            if ($filter instanceof Filter && $filter->getProperty() === $property && $filter->getComparator() === $comparator) {
                return $filter;
            }
        }

        return null;
    }

    /**
     * @throws \RuntimeException
     */
    public function findOneByProperty(string $property): ?Filter
    {
        $result = $this->findByProperty($property);

        if (\count($result) > 1) {
            throw new \RuntimeException(\sprintf("It was expected that property '%s' would have only one filter defined for it, but in fact it has %d.", $property, \count($result)));
        } elseif (1 === \count($result)) {
            return $result[0];
        }

        return null;
    }

    public function hasFilterForProperty(string $property): bool
    {
        return 0 !== \count($this->findByProperty($property));
    }

    public function addFilter(Filter $filter): bool
    {
        foreach ($this->filters as $currentFilter) {
            if ($filter == $currentFilter) {
                return false;
            }
        }

        $this->filters[] = $filter;

        return true;
    }

    public function removeFilter(Filter $filter): bool
    {
        /** @var int|false $found */
        $found = \array_search($filter, $this->filters);
        if (false === $found) {
            return false;
        }

        \array_splice($this->filters, $found, 1);

        return true;
    }

    public function addOrFilter(OrFilter $orFilter): bool
    {
        foreach ($this->filters as $currentFilter) {
            if ($orFilter == $currentFilter) {
                return false;
            }
        }

        $this->filters[] = $orFilter;

        return true;
    }

    public function removeOrFilter(OrFilter $orFilter): bool
    {
        /** @var int|false $found */
        $found = \array_search($orFilter, $this->filters);
        if (false === $found) {
            return false;
        }

        \array_splice($this->filters, $found, 1);

        return true;
    }

    /**
     * @return array<int, array{
     *     'property': string,
     *     'value': string,
     * }|array{
     *     'property': string,
     *     'value': string,
     * }[]>
     */
    public function compile(): array
    {
        $result = [];

        /** @var FilterInterface $filter */
        foreach ($this->filters as $filter) {
            $result[] = $filter->compile();
        }

        return $result;
    }

    // Iterator:

    /**
     * @return Filter|OrFilter
     */
    public function current()
    {
        return $this->filters[$this->iteratorIndex];
    }

    public function next(): void
    {
        ++$this->iteratorIndex;
    }

    public function key(): int
    {
        return $this->iteratorIndex;
    }

    public function valid(): bool
    {
        return isset($this->filters[$this->iteratorIndex]);
    }

    public function rewind(): void
    {
        $this->iteratorIndex = 0;
        \reset($this->filters);
    }

    // Countable:

    public function count(): int
    {
        return \count($this->filters);
    }

    // ArrayAccess

    public function offsetExists($offset): bool
    {
        return isset($this->filters[$offset]);
    }

    /**
     * @return Filter|OrFilter
     */
    public function offsetGet($offset)
    {
        return $this->filters[$offset];
    }

    /**
     * @param Filter|OrFilter $value
     */
    public function offsetSet($offset, $value): void
    {
        $this->filters[$offset] = $value;
    }

    public function offsetUnset($offset): void
    {
        unset($this->filters[$offset]);
    }
}
