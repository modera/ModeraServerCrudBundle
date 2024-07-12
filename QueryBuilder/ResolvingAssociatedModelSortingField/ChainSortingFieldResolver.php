<?php

namespace Modera\ServerCrudBundle\QueryBuilder\ResolvingAssociatedModelSortingField;

/**
 * @author Sergei Vizel <sergei.vizel@modera.org>
 * @copyright 2024 Modera Foundation
 */
class ChainSortingFieldResolver implements SortingFieldResolverInterface
{
    /**
     * @var SortingFieldResolverInterface[]
     */
    private array $resolvers = [];

    public function add(SortingFieldResolverInterface $resolver): void
    {
        $this->resolvers[\spl_object_hash($resolver)] = $resolver;
    }

    /**
     * @return SortingFieldResolverInterface[]
     */
    public function all(): array
    {
        return \array_values($this->resolvers);
    }

    public function resolve(string $entityFqcn, string $fieldName): ?string
    {
        foreach ($this->resolvers as $resolver) {
            $result = $resolver->resolve($entityFqcn, $fieldName);
            if (null !== $result) {
                return $result;
            }
        }

        return null;
    }
}
