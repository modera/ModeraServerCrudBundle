<?php

namespace Modera\ServerCrudBundle\QueryBuilder\ResolvingAssociatedModelSortingField;

/**
 * @author Sergei Vizel <sergei.vizel@modera.org>
 * @copyright 2024 Modera Foundation
 */
interface SortingFieldResolverInterface
{
    public function resolve(string $entityFqcn, string $fieldName): ?string;
}
