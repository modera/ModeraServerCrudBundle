<?php

namespace Modera\ServerCrudBundle\QueryBuilder\ResolvingAssociatedModelSortingField;

/**
 * @author Sergei Vizel <sergei.vizel@modera.org>
 * @copyright 2024 Modera Foundation
 */
class MutableSortingFieldResolver implements SortingFieldResolverInterface
{
    /**
     * @var array<string, array<string, string>>
     */
    private array $mapping = [];

    public function add(string $entityFqcn, string $fieldName, string $result): void
    {
        if (!isset($this->mapping[$entityFqcn])) {
            $this->mapping[$entityFqcn] = [];
        }

        $this->mapping[$entityFqcn][$fieldName] = $result;
    }

    public function resolve(string $entityFqcn, string $fieldName): ?string
    {
        if (\is_array($this->mapping[$entityFqcn] ?? null)) {
            return $this->mapping[$entityFqcn][$fieldName] ?? null;
        }

        return null;
    }
}
