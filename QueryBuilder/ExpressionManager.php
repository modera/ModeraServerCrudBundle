<?php

namespace Modera\ServerCrudBundle\QueryBuilder;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\Expr\Select;
use Doctrine\ORM\QueryBuilder;
use Modera\ServerCrudBundle\QueryBuilder\Parsing\Expression;

/**
 * @author Sergei Vizel <sergei.vizel@modera.org>
 * @copyright 2024 Modera Foundation
 */
class ExpressionManager
{
    private string $fqcn;

    private EntityManagerInterface $em;

    private string $rootAlias;

    /**
     * @var array<string, string>
     */
    private array $allocatedAliases = [];

    /**
     * @var array<string, bool>
     */
    private array $validatedExpressions = [];

    public function __construct(string $fqcn, EntityManagerInterface $em, string $rootAlias = 'e')
    {
        $this->fqcn = $fqcn;
        $this->em = $em;
        $this->rootAlias = $rootAlias;
    }

    /**
     * @internal Don't use nor rely on this method existence!
     *
     * @return array<string, string>
     */
    public function getAllocatedAliasMap(): array
    {
        return $this->allocatedAliases;
    }

    public function getRootAlias(): string
    {
        return $this->rootAlias;
    }

    public function isValidExpression(string $expression): bool
    {
        if (!isset($this->validatedExpressions[$expression])) {
            $this->validatedExpressions[$expression] = $this->doIsValidExpression($expression);
        }

        return $this->validatedExpressions[$expression];
    }

    protected function doIsValidExpression(string $expression): bool
    {
        /** @var class-string $fqcn */
        $fqcn = $this->fqcn;

        if (false !== \strpos($expression, '.')) {
            $parsed = \explode('.', $expression);

            /**
             * @var int    $index
             * @var string $propertyName
             */
            foreach ($parsed as $index => $propertyName) {
                $meta = $this->em->getClassMetadata($fqcn);
                if ($meta->hasAssociation($propertyName)) {
                    $mapping = $meta->getAssociationMapping($propertyName);
                    $fqcn = $mapping['targetEntity'];

                    if ($index === (\count($parsed) - 1)) { // association is the last segment
                        return true;
                    }
                } elseif ($meta->hasField($propertyName)) {
                    return true;
                }
            }

            return false;
        }

        $meta = $this->em->getClassMetadata($fqcn);

        return $meta->hasField($expression) || $meta->hasAssociation($expression);
    }

    private function validateExpression(string $expression): void
    {
        if (!$this->isValidExpression($expression)) {
            throw new \RuntimeException("'$expression' doesn't look to be a valid expression for entity {$this->fqcn}.");
        }
    }

    /**
     * Alias to given $expression.
     *
     * @throws \RuntimeException
     */
    public function allocateAlias(string $expression): string
    {
        $parsedExpression = \explode('.', $expression);

        /** @var class-string $fqcn */
        $fqcn = $this->fqcn;

        $meta = $this->em->getClassMetadata($fqcn);
        foreach ($parsedExpression as $index => $propertyName) {
            if (!$meta->hasAssociation($propertyName)) {
                throw new \RuntimeException(\sprintf("Error during parsing of '$expression' expression. Entity '%s' doesn't have association '%s'.", $meta->getName(), $propertyName));
            }

            $mapping = $meta->getAssociationMapping($propertyName);
            $meta = $this->em->getClassMetadata($mapping['targetEntity']);

            $currentExpression = \implode('.', \array_slice($parsedExpression, 0, $index + 1));
            if (!$this->resolveExpressionToAlias($expression) && !$this->resolveExpressionToAlias($currentExpression)) {
                $this->doAllocateAlias($currentExpression);
            }
        }

        /** @var string $alias */
        $alias = $this->resolveExpressionToAlias($expression);

        return $alias;
    }

    /**
     * Allocates a DQL join alias for a given $expression.
     */
    private function doAllocateAlias(string $expression): void
    {
        $alias = 'j'.\count($this->allocatedAliases);
        $this->allocatedAliases[$alias] = $expression;
    }

    /**
     * Expression for the provided $alias, if $alias is not found, NULL is returned.
     */
    public function resolveAliasToExpression(string $alias): ?string
    {
        return $this->allocatedAliases[$alias] ?? null;
    }

    /**
     * Alias for a given $expression. If expression is not found, then NULL is returned.
     */
    public function resolveExpressionToAlias(string $expression): ?string
    {
        $found = \array_search($expression, $this->allocatedAliases);

        return \is_string($found) ? $found : null;
    }

    /**
     * For a given $expression, will return a correct variable name with alias that you can use in your DQL query.
     */
    public function getDqlPropertyName(string $expression): string
    {
        $this->validateExpression($expression);

        if (false !== \strpos($expression, '.')) { // associative expression
            $parsedExpression = \explode('.', $expression);
            $propertyName = \array_pop($parsedExpression);

            return $this->allocateAlias(\implode('.', $parsedExpression)).'.'.$propertyName;
        } else {
            return $this->getRootAlias().'.'.$expression;
        }
    }

    /**
     * @return string[]
     */
    private function expandExpression(string $expression): array
    {
        $result = [];

        $explodedExpression = \explode('.', $expression);
        foreach ($explodedExpression as $i => $segment) {
            $result[] = \implode('.', \array_slice($explodedExpression, 0, $i + 1));
        }

        return $result;
    }

    /**
     * @param array<int|string, string> $expressions
     */
    private function doInjectJoins(QueryBuilder $qb, array $expressions): void
    {
        foreach (\array_values($expressions) as $i => $expression) {
            /** @var string $alias */
            $alias = $this->resolveExpressionToAlias($expression);

            $parsedExpression = \explode('.', $expression);

            if (0 === $i) {
                $qb->leftJoin($this->rootAlias.'.'.$expression, $alias);
            } elseif (1 === \count($parsedExpression)) {
                $qb->leftJoin($this->rootAlias.'.'.$parsedExpression[0], $alias);
            } else {
                $rootExpression = \implode('.', \array_slice($parsedExpression, 0, -1));
                $propertyName = \end($parsedExpression);

                $parentAlias = $this->resolveExpressionToAlias($rootExpression);
                $qb->leftJoin($parentAlias.'.'.$propertyName, $alias);
            }
        }
    }

    /**
     * @param bool $useFetchJoins If provided then joined entities will be fetched as well
     */
    public function injectJoins(QueryBuilder $qb, bool $useFetchJoins = true): void
    {
        if ($useFetchJoins) {
            $expressions = [];
            foreach ($this->allocatedAliases as $rawExpression) {
                $expressions[] = new Expression($rawExpression);
            }

            $this->injectFetchSelects($qb, $expressions);
        } else {
            $this->doInjectJoins($qb, $this->allocatedAliases);
        }
    }

    /**
     * When selects are injected then apparently the joins will be added to the query as well, so you either
     * use this method or injectJoins() but not both of them at the same time.
     *
     * @param Expression[] $expressions All expressions which were provided in "fetch". The method will filter
     *                                  "select" fetches by itself
     */
    public function injectFetchSelects(QueryBuilder $qb, array $expressions): void
    {
        $expandedExpressions = [];
        foreach ($expressions as $expression) {
            $isFetchOnly = !$expression->getAlias() && !$expression->getFunction();

            // we need to have only "fetch" expressions
            if ($isFetchOnly && \is_string($expression->getExpression()) && $this->isAssociation($expression->getExpression())) {
                $expandedExpressions = \array_merge($expandedExpressions, $this->expandExpression($expression->getExpression()));
            }
        }

        $expandedExpressions = \array_values(\array_unique($expandedExpressions));

        /** @var Select[] $parts */
        $parts = $qb->getDQLPart('select');

        $selects = [];
        foreach ($parts as $select) {
            $selects[] = \trim((string) $select);
        }

        $map = [];
        foreach ($expandedExpressions as $expression) {
            $this->allocateAlias($expression);

            $map[$this->resolveExpressionToAlias($expression)] = $expression;
        }

        foreach ($map as $alias => $expression) {
            if (!\in_array($alias, $selects)) {
                $qb->addSelect($alias);
            }
        }

        $this->doInjectJoins($qb, $expandedExpressions);
    }

    /**
     * @return mixed[] Doctrine field's mapping
     *
     * @throws \RuntimeException
     */
    public function getMapping(string $expression): array
    {
        $this->validateExpression($expression);

        /** @var class-string $fqcn */
        $fqcn = $this->fqcn;

        $meta = $this->em->getClassMetadata($fqcn);
        $parsedExpression = \explode('.', $expression);
        foreach ($parsedExpression as $index => $propertyName) {
            /** @var array{'targetEntity': class-string} $mapping */
            $mapping = $meta->hasAssociation($propertyName)
                     ? $meta->getAssociationMapping($propertyName)
                     : $meta->getFieldMapping($propertyName);

            if ($meta->hasAssociation($propertyName)) {
                $meta = $this->em->getClassMetadata($mapping['targetEntity']);
            }

            if ($index === (\count($parsedExpression) - 1)) {
                return $mapping;
            }
        }

        throw new \RuntimeException("Mapping for expression '$expression' not found.");
    }

    public function isAssociation(string $expression): bool
    {
        $this->validateExpression($expression);

        /** @var class-string $fqcn */
        $fqcn = $this->fqcn;

        $meta = $this->em->getClassMetadata($fqcn);
        $parsedExpression = \explode('.', $expression);
        foreach ($parsedExpression as $index => $propertyName) {
            /** @var array{'targetEntity': class-string} $mapping */
            $mapping = $meta->hasAssociation($propertyName)
                     ? $meta->getAssociationMapping($propertyName)
                     : $meta->getFieldMapping($propertyName);

            if ($meta->hasAssociation($propertyName)) {
                if ($index === (\count($parsedExpression) - 1)) {
                    return true;
                }

                $meta = $this->em->getClassMetadata($mapping['targetEntity']);
            }
        }

        return false;
    }
}
