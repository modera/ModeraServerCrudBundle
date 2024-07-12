<?php

namespace Modera\ServerCrudBundle\QueryBuilder;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadataInfo;
use Doctrine\ORM\Query;
use Doctrine\ORM\Query\Expr;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Modera\ServerCrudBundle\DataMapping\EntityDataMapperService;
use Modera\ServerCrudBundle\QueryBuilder\Parsing\Expression;
use Modera\ServerCrudBundle\QueryBuilder\Parsing\Filter;
use Modera\ServerCrudBundle\QueryBuilder\Parsing\FilterInterface;
use Modera\ServerCrudBundle\QueryBuilder\Parsing\Filters;
use Modera\ServerCrudBundle\QueryBuilder\Parsing\OrderExpression;
use Modera\ServerCrudBundle\QueryBuilder\Parsing\OrFilter;
use Modera\ServerCrudBundle\QueryBuilder\ResolvingAssociatedModelSortingField\ChainSortingFieldResolver;
use Modera\ServerCrudBundle\QueryBuilder\ResolvingAssociatedModelSortingField\SortingFieldResolverInterface;

/**
 * @author Sergei Vizel <sergei.vizel@modera.org>
 * @copyright 2024 Modera Foundation
 */
class ArrayQueryBuilder
{
    private ManagerRegistry $doctrineRegistry;

    private EntityDataMapperService $mapper;

    private SortingFieldResolverInterface $sortingFieldResolver;

    public function __construct(
        ManagerRegistry $doctrineRegistry,
        EntityDataMapperService $mapper,
        SortingFieldResolverInterface $sortingFieldResolver
    ) {
        $this->doctrineRegistry = $doctrineRegistry;
        $this->mapper = $mapper;
        $this->sortingFieldResolver = $sortingFieldResolver;
    }

    /**
     * @param array<string, mixed> $arrayQuery
     */
    public function buildQuery(string $entityFqcn, array $arrayQuery): Query
    {
        return $this->buildQueryBuilder($entityFqcn, $arrayQuery)->getQuery();
    }

    /**
     * @param mixed $value Mixed value
     *
     * @return mixed Mixed value
     */
    protected function convertValue(ExpressionManager $expressionManager, string $expression, $value)
    {
        /** @var array{'type': string} $mapping */
        $mapping = $expressionManager->getMapping($expression);
        $value = $this->mapper->convertValue($value, $mapping['type']);

        // querying won't work properly if query "date" type field by using instance of \DateTimeInterface object
        // because the latter contains information about time which we don't really need for "date" fields
        if ('date' === $mapping['type'] && $value instanceof \DateTimeInterface) {
            return $value->format($this->resolveDateFormat());
        }

        return $value;
    }

    private function resolveDateFormat(?string $entityClass = null): string
    {
        /** @var EntityManagerInterface $em */
        $em = $this->doctrineRegistry->getManager();
        if ($entityClass) {
            /** @var class-string $className */
            $className = $entityClass;
            /** @var ?EntityManagerInterface $em */
            $em = $this->doctrineRegistry->getManagerForClass($className);
            if (!$em) {
                throw new \RuntimeException(\sprintf('Unable to resolve EntityManager for class "%s". Are you sure that the entity has been properly mapped ?', $entityClass));
            }
        }

        return $em->getConnection()->getDatabasePlatform()->getDateFormatString();
    }

    private function resolveExpression(
        string $entityFqcn,
        string $expression,
        SortingFieldResolverInterface $sortingFieldResolver,
        ExpressionManager $exprMgr
    ): string {
        if ($exprMgr->isAssociation($expression)) {
            /** @var array{'targetEntity': string} $mapping */
            $mapping = $exprMgr->getMapping($expression);

            $fieldResolverExpression = \explode('.', $expression);
            $fieldResolverExpression = \end($fieldResolverExpression);

            $expression = $this->resolveExpression(
                $mapping['targetEntity'],
                $expression.'.'.$sortingFieldResolver->resolve($entityFqcn, $fieldResolverExpression),
                $sortingFieldResolver,
                $exprMgr
            );
        }

        return $expression;
    }

    /**
     * @param mixed $value Mixed value
     */
    private function isUsefulInFilter(?string $comparator, $value): bool
    {
        // There's no point to create empty IN, NOT IN clause, even more - trying to use
        // empty IN, NOT IN will result in SQL error
        return !(
            \in_array($comparator, [Filter::COMPARATOR_IN, Filter::COMPARATOR_NOT_IN])
            && \is_array($value)
            && 0 === \count($value)
        );
    }

    /**
     * @param mixed $value Mixed value
     */
    private function isUsefulFilter(ExpressionManager $exprMgr, string $propertyName, $value): bool
    {
        // if this is association field, then sometimes there could be just 'no-value'
        // state which is conventionally marked as '-' value
        return !($exprMgr->isAssociation($propertyName) && '-' === $value);
    }

    private function processFilter(
        ExpressionManager $expressionManager,
        Expr\Composite $compositeExpr,
        QueryBuilder $qb,
        DoctrineQueryBuilderParametersBinder $binder,
        Filter $filter
    ): void {
        /** @var string $name */
        $name = $filter->getProperty();

        $fieldName = $expressionManager->getDqlPropertyName($name);

        if (\in_array($filter->getComparator(), [Filter::COMPARATOR_IS_NULL, Filter::COMPARATOR_IS_NOT_NULL])) { // these are sort of 'special case'
            $compositeExpr->add(
                $qb->expr()->{$filter->getComparator()}($fieldName)
            );
        } else {
            $value = $filter->getValue();
            $comparatorName = $filter->getComparator();

            if (!$this->isUsefulInFilter($comparatorName, $value)
                || !$this->isUsefulFilter($expressionManager, $name, $value)) {
                return;
            }

            // when "IN" is used in conjunction with TO_MANY type of relation,
            // then we will treat it in a special way and generate "MEMBER OF" queries
            // instead
            $isAdded = false;
            if ($expressionManager->isAssociation($name)) {
                /** @var array{'type': int} $mapping */
                $mapping = $expressionManager->getMapping($name);
                if (\in_array($comparatorName, [Filter::COMPARATOR_IN, Filter::COMPARATOR_NOT_IN])
                    && \in_array($mapping['type'], [ClassMetadataInfo::ONE_TO_MANY, ClassMetadataInfo::MANY_TO_MANY])) {
                    /** @var mixed[] $arr */
                    $arr = $value;

                    $statements = [];
                    foreach ($arr as $id) {
                        $statements[] = \sprintf(
                            (Filter::COMPARATOR_NOT_IN === $comparatorName ? 'NOT ' : '').'?%d MEMBER OF %s',
                            $binder->getNextIndex(),
                            $expressionManager->getDqlPropertyName($name)
                        );

                        $binder->bind($this->convertValue($expressionManager, $name, $id));
                    }

                    if (Filter::COMPARATOR_IN === $comparatorName) {
                        $compositeExpr->add(
                            \call_user_func_array([$qb->expr(), 'orX'], $statements)
                        );
                    } else {
                        $compositeExpr->addMultiple($statements);
                    }

                    $isAdded = true;
                }
            }

            if (!$isAdded) {
                if (\is_array($value) && \count($value) !== \count($value, \COUNT_RECURSIVE)) { // must be "OR-ed" ( multi-dimensional array )
                    $orStatements = [];
                    /** @var array{'comparator': string, 'value': mixed} $orFilter */
                    foreach ($value as $orFilter) {
                        if (!$this->isUsefulInFilter($orFilter['comparator'], $orFilter['value'])
                            || !$this->isUsefulFilter($expressionManager, $name, $orFilter['value'])) {
                            continue;
                        }

                        if (\in_array($orFilter['comparator'], [Filter::COMPARATOR_IN, Filter::COMPARATOR_NOT_IN])) {
                            $orStatements[] = $qb->expr()->{$orFilter['comparator']}($fieldName);
                        } else {
                            $orStatements[] = $qb->expr()->{$orFilter['comparator']}($fieldName, '?'.$binder->getNextIndex());
                        }

                        $binder->bind($orFilter['value']);
                    }

                    $compositeExpr->add(
                        \call_user_func_array([$qb->expr(), 'orX'], $orStatements)
                    );
                } else {
                    $compositeExpr->add(
                        $qb->expr()->$comparatorName($fieldName, '?'.$binder->getNextIndex())
                    );
                    $binder->bind($this->convertValue($expressionManager, $name, $value));
                }
            }
        }
    }

    /**
     * @param string               $entityFqcn Root fetch entity fully-qualified-class-name
     * @param array<string, mixed> $arrayQuery Parameters that were sent from client-side
     *
     * @throws \RuntimeException
     */
    public function buildQueryBuilder(string $entityFqcn, array $arrayQuery, ?SortingFieldResolverInterface $primarySortingFieldResolver = null): QueryBuilder
    {
        $sortingFieldResolver = new ChainSortingFieldResolver();
        if ($primarySortingFieldResolver) {
            $sortingFieldResolver->add($primarySortingFieldResolver);
        }
        $sortingFieldResolver->add($this->sortingFieldResolver);

        /** @var class-string $className */
        $className = $entityFqcn;
        /** @var ?EntityManagerInterface $em */
        $em = $this->doctrineRegistry->getManagerForClass($className);
        if (!$em) {
            throw new \RuntimeException(\sprintf('Unable to resolve EntityManager for class "%s". Are you sure that the entity has been properly mapped ?', $entityFqcn));
        }
        $qb = $em->createQueryBuilder();

        $expressionManager = new ExpressionManager($entityFqcn, $em);
        $dqlCompiler = new DqlCompiler($expressionManager);
        $binder = new DoctrineQueryBuilderParametersBinder($qb);

        $hasFetch = \is_array($arrayQuery['fetch'] ?? null) && \count($arrayQuery['fetch']) > 0;

        /** @var Expression[] $fetchExpressions */
        $fetchExpressions = [];
        if ($hasFetch) {
            /** @var mixed[] $fetch */
            $fetch = $arrayQuery['fetch'];
            /**
             * @var int|string $statement
             * @var string|array{
             *     'function': string,
             *     'args': array<string|array<string, mixed>>,
             *     'hidden'?: bool,
             * } $groupExpr
             */
            foreach ($fetch as $statement => $groupExpr) {
                $fetchExpressions[] = new Expression($groupExpr, \is_string($statement) ? $statement : null);
            }
        }

        $orderStmts = []; // contains ready DQL orderBy statement that later will be joined together
        if (\is_array($arrayQuery['sort'] ?? null)) {
            foreach ($arrayQuery['sort'] as $entry) { // sanitizing and filtering
                $orderExpression = new OrderExpression($entry);

                if (!$orderExpression->isValid()) {
                    continue;
                }

                $statement = null;

                // if expression cannot be directly resolved again the model we will check
                // if there's an alias introduced in "fetch" and then allow to use it
                if (!$expressionManager->isValidExpression($orderExpression->getProperty() ?? '')
                    && $hasFetch && \is_array($arrayQuery['fetch']) && isset($arrayQuery['fetch'][$orderExpression->getProperty()])) {
                    $statement = $orderExpression->getProperty();
                } elseif ($expressionManager->isValidExpression($orderExpression->getProperty() ?? '')) {
                    $statement = $expressionManager->getDqlPropertyName(
                        $this->resolveExpression($entityFqcn, $orderExpression->getProperty() ?? '', $sortingFieldResolver, $expressionManager)
                    );
                }

                if (null === $statement) {
                    continue;
                }

                $orderStmts[] = $statement.' '.\strtoupper($orderExpression->getDirection() ?? '');
            }
        }

        $hasGroupBy = \is_array($arrayQuery['groupBy'] ?? null) && \count($arrayQuery['groupBy']) > 0;

        /** @var Expression[] $groupByExpressions */
        $groupByExpressions = [];
        if ($hasGroupBy && \is_array($arrayQuery['groupBy'])) {
            /** @var array{'property': string, 'direction': string} $groupExpr */
            foreach ($arrayQuery['groupBy'] as $groupExpr) {
                $groupByExpressions[] = new Expression($groupExpr);
            }
        }

        $addRootFetch = (isset($arrayQuery['fetchRoot']) && true === $arrayQuery['fetchRoot']) || !isset($arrayQuery['fetchRoot']);
        if ($addRootFetch) {
            $qb->add('select', 'e');
        }

        foreach ($fetchExpressions as $expression) {
            if ($expression->getFunction() || $expression->getAlias()) {
                $qb->add('select', $dqlCompiler->compile($expression, $binder), true);
            } else {
                /** @var string $exp */
                $exp = $expression->getExpression();
                if (!$expressionManager->isAssociation($exp)) {
                    $qb->add('select', $expressionManager->getDqlPropertyName($exp), true);
                }
            }
        }

        $qb->add('from', $entityFqcn.' e');

        if (\is_int($arrayQuery['start'] ?? null)) {
            $start = $arrayQuery['start'];
            if (\is_int($arrayQuery['page'] ?? null) && \is_int($arrayQuery['limit'] ?? null)) {
                $start = ($arrayQuery['page'] - 1) * $arrayQuery['limit'];
            }
            $qb->setFirstResult($start);
        }

        if (\is_int($arrayQuery['limit'] ?? null)) {
            $qb->setMaxResults($arrayQuery['limit']);
        }

        if (\is_array($arrayQuery['filter'] ?? null)) {
            $andExpr = $qb->expr()->andX();

            /** @var FilterInterface $filter */
            foreach (new Filters($arrayQuery['filter']) as $filter) {
                if (!$filter->isValid()) {
                    continue;
                }

                if ($filter instanceof OrFilter) {
                    $orExpr = $qb->expr()->orX();
                    foreach ($filter->getFilters() as $filter) {
                        $this->processFilter($expressionManager, $orExpr, $qb, $binder, $filter);
                    }
                    $andExpr->add($orExpr);
                } else {
                    /** @var Filter $filter */
                    $filter = $filter;
                    $this->processFilter($expressionManager, $andExpr, $qb, $binder, $filter);
                }
            }

            if ($andExpr->count() > 0) {
                $qb->where($andExpr);
            }
        }

        if ($hasFetch) {
            $expressionManager->injectFetchSelects($qb, $fetchExpressions);
        } else {
            $expressionManager->injectJoins($qb, false);
        }

        if ($hasGroupBy) {
            foreach ($groupByExpressions as $groupExpr) {
                if ($groupExpr->getFunction()) {
                    $qb->addGroupBy($dqlCompiler->compile($groupExpr, $binder));
                } else {
                    $dqlExpression = null;
                    /** @var string $exp */
                    $exp = $groupExpr->getExpression();
                    if ($expressionManager->isValidExpression($exp)) {
                        $dqlExpression = $expressionManager->getDqlPropertyName($exp);
                    } else {
                        // we need to have something like this due to a limitation imposed by DQL. Basically,
                        // we cannot write a query which would look akin to this one:
                        // SELECT COUNT(e.id), DAY(e.createdAt) FROM FooEntity e GROUP BY DAY(e.createdAt)
                        // If you try to use a function call in a GROUP BY clause an exception will be thrown.
                        // To workaround this problem we need to introduce an alias, for example:
                        // SELECT COUNT(e.id), DAY(e.createdAt) AS datDay FROM FooEntity e GROUP BY datDay
                        foreach ($fetchExpressions as $fetchExpr) {
                            if ($exp === $fetchExpr->getAlias()) {
                                $dqlExpression = $fetchExpr->getAlias();
                            }
                        }
                    }

                    if (!$dqlExpression) {
                        throw new \RuntimeException(sprintf('Unable to resolve grouping expression "%s" for entity %s', $exp, $entityFqcn));
                    }

                    $qb->addGroupBy($dqlExpression);
                }
            }

            $expressionManager->injectJoins($qb, false);
        }

        if (\count($orderStmts) > 0) {
            $qb->add('orderBy', \implode(', ', $orderStmts));
        }

        $binder->injectParameters();

        return $qb;
    }

    /**
     * @param callable $hydrator            An instance of \Closure ( anonymous functions ) that will be used to hydrate fetched
     *                                      from database entities. Entity that needs to be hydrated will be passed as a first and
     *                                      only argument to the function.
     * @param ?string  $rootFetchEntityFqcn If your fetch query contains several SELECT entries, then you need
     *                                      to specify which entity we must use to build COUNT query with
     *
     * @return array{
     *     'success': bool,
     *     'total': int,
     *     'items': mixed[],
     * } Response that should be sent back to the client side. You need to have a properly
     *   configured proxy's reader for your store, it should be of json type with the following config:
     *   { type: 'json', root: 'items', totalProperty: 'total' }
     */
    public function buildResponseWithPagination(QueryBuilder $qb, callable $hydrator, ?string $rootFetchEntityFqcn = null): array
    {
        $countQueryBuilder = $this->buildCountQueryBuilder($qb, $rootFetchEntityFqcn);

        /** @var mixed[] $result */
        $result = $qb->getQuery()->getResult();

        $hydratedItems = [];
        foreach ($result as $item) {
            /** @var mixed $hydratedItem Mixed value */
            $hydratedItem = $hydrator($item);
            $hydratedItems[] = $hydratedItem;
        }

        /** @var string $total */
        $total = $countQueryBuilder->getQuery()->getSingleScalarResult();

        return [
            'success' => true,
            'total' => (int) $total,
            'items' => $hydratedItems,
        ];
    }

    /**
     * If you use Doctrine version 2.2 or higher, consider using {@class Doctrine\ORM\Tools\Pagination\Paginator}
     * instead. See http://docs.doctrine-project.org/en/latest/tutorials/pagination.html for more details
     * on that.
     *
     * @param QueryBuilder $queryBuilder        Fetch query-builder, in other words - instance of QueryBuilder
     *                                          that will be used to actually execute SELECT query for response
     *                                          you are going to send back
     * @param ?string      $rootFetchEntityFqcn If your fetch query contains several SELECT entries, then you need
     *                                          to specify which entity we must use to build COUNT query with
     *
     * @throws \RuntimeException
     */
    public function buildCountQueryBuilder(QueryBuilder $queryBuilder, ?string $rootFetchEntityFqcn = null): QueryBuilder
    {
        $countQueryBuilder = clone $queryBuilder;
        $countQueryBuilder->setFirstResult(null);
        $countQueryBuilder->setMaxResults(null);

        /** @var array{
         *     'select'?: string[],
         *     'from'?: string[],
         * } $parts
         */
        $parts = $countQueryBuilder->getDQLParts();

        if (!\is_array($parts['select'] ?? null) || 0 === \count($parts['select'])) {
            throw new \RuntimeException('Provided $queryBuilder doesn\'t contain SELECT part.');
        }

        if (!\is_array($parts['from'] ?? null)) {
            throw new \RuntimeException('Provided $queryBuilder doesn\'t contain FROM part.');
        }

        $aliases = $queryBuilder->getRootAliases();
        $rootAlias = $aliases[0] ?? null;
        if (!$rootAlias) {
            throw new \RuntimeException('No alias was set before invoking getRootAliases().');
        }

        if (\count($parts['select']) > 1) {
            foreach ($parts['from'] as $fromPart) {
                list($entityFqcn, $alias) = \explode(' ', $fromPart);
                if ($entityFqcn === $rootFetchEntityFqcn) {
                    $rootAlias = $alias;
                }
            }
        } else {
            $rootAlias = $parts['select'][0];

            $isFound = false;
            foreach ($parts['from'] as $fromPart) {
                list($entityFqcn, $alias) = \explode(' ', $fromPart);
                if ($alias === $rootAlias) {
                    $isFound = true;
                }
            }
            if (!$isFound) {
                throw new \RuntimeException("Unable to resolve fetch entity FQCN for alias '$rootAlias'. Do you have your SELECT and FROM parts properly built ?");
            }
        }
        if (null == $rootAlias) {
            throw new \RuntimeException("Unable to resolve alias for entity $rootFetchEntityFqcn");
        }

        // DISTINCT is needed when there are LEFT JOINs in your queries
        $countQueryBuilder->add('select', "COUNT (DISTINCT {$parts['select'][0]})");
        $countQueryBuilder->resetDQLPart('orderBy'); // for COUNT queries it is completely pointless

        return $countQueryBuilder;
    }

    /**
     * @param array<string, mixed> $arrayQuery
     *
     * @return mixed Mixed value
     */
    public function getResult(string $entityFqcn, array $arrayQuery)
    {
        /** @var mixed[] $result */
        $result = $this->buildQuery($entityFqcn, $arrayQuery)->getResult();

        return $result;
    }
}
