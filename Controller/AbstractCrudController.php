<?php

namespace Modera\ServerCrudBundle\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Modera\DirectBundle\Annotation\Remote;
use Modera\DirectBundle\Controller\ControllerTrait;
use Modera\ServerCrudBundle\DataMapping\DataMapperInterface;
use Modera\ServerCrudBundle\DependencyInjection\ModeraServerCrudExtension;
use Modera\ServerCrudBundle\EntityFactory\EntityFactoryInterface;
use Modera\ServerCrudBundle\ExceptionHandling\ExceptionHandlerInterface;
use Modera\ServerCrudBundle\Exceptions\BadConfigException;
use Modera\ServerCrudBundle\Exceptions\BadRequestException;
use Modera\ServerCrudBundle\Exceptions\MoreThanOneResultException;
use Modera\ServerCrudBundle\Exceptions\NothingFoundException;
use Modera\ServerCrudBundle\Hydration\HydrationService;
use Modera\ServerCrudBundle\Intercepting\InterceptorsManager;
use Modera\ServerCrudBundle\NewValuesFactory\NewValuesFactoryInterface;
use Modera\ServerCrudBundle\Persistence\ModelManagerInterface;
use Modera\ServerCrudBundle\Persistence\OperationResult;
use Modera\ServerCrudBundle\Persistence\PersistenceHandlerInterface;
use Modera\ServerCrudBundle\Util\Toolkit;
use Modera\ServerCrudBundle\Validation\EntityValidatorInterface;
use Modera\ServerCrudBundle\Validation\ValidationResult;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class provides tools for fulfilling CRUD operations.
 *
 * When you create a subclass of AbstractCrudController you must implement `getConfig` method which must
 * contain at least two configuration properties:
 *
 * - entity -- Fully qualified class name of entity this controller will be responsible for
 * - hydration -- Data hydration rules
 *
 * For more details on other available configuration properties and general how-tos please refer to the bundle's
 * README.md file.
 *
 * @author    Sergei Lissovski <sergei.lissovski@modera.org>
 * @copyright 2013 Modera Foundation
 */
abstract class AbstractCrudController extends AbstractController implements CrudControllerInterface
{
    use ControllerTrait;

    /**
     * @return array<string, mixed>
     */
    abstract public function getConfig(): array;

    protected function getContainer(): ContainerInterface
    {
        /** @var ContainerInterface $container */
        $container = $this->container;

        return $container;
    }

    protected function em(): EntityManagerInterface
    {
        @\trigger_error(\sprintf(
            'The "%s()" method is deprecated, inject an instance of ManagerRegistry in your controller instead.',
            __METHOD__
        ), \E_USER_DEPRECATED);

        /** @var EntityManagerInterface $em */
        $em = $this->getContainer()->get('doctrine.orm.entity_manager');

        return $em;
    }

    /**
     * @return array<string, mixed>
     */
    public function getPreparedConfig(): array
    {
        $me = $this;

        $defaultConfig = [
            'create_entity' => function (array $params, array $config, EntityFactoryInterface $defaultFactory, ContainerInterface $container) {
                return $defaultFactory->create($params, $config);
            },
            'map_data_on_create' => function (array $params, $entity, DataMapperInterface $defaultMapper, ContainerInterface $container) {
                $defaultMapper->mapData($params, $entity);
            },
            'map_data_on_update' => function (array $params, $entity, DataMapperInterface $defaultMapper, ContainerInterface $container) {
                $defaultMapper->mapData($params, $entity);
            },
            'new_entity_validator' => function (array $params, $mappedEntity, EntityValidatorInterface $defaultValidator, array $config, ContainerInterface $container) {
                return $defaultValidator->validate($mappedEntity, $config);
            },
            'updated_entity_validator' => function (array $params, $mappedEntity, EntityValidatorInterface $defaultValidator, array $config, ContainerInterface $container) {
                return $defaultValidator->validate($mappedEntity, $config);
            },
            'save_entity_handler' => function ($entity, array $params, PersistenceHandlerInterface $defaultHandler, ContainerInterface $container) {
                return $defaultHandler->save($entity);
            },
            'update_entity_handler' => function ($entity, array $params, PersistenceHandlerInterface $defaultHandler, ContainerInterface $container) {
                return $defaultHandler->update($entity);
            },
            'batch_update_entities_handler' => function (array $entities, array $params, PersistenceHandlerInterface $defaultHandler, ContainerInterface $container) {
                return $defaultHandler->updateBatch($entities);
            },
            'remove_entities_handler' => function (array $entities, array $params, PersistenceHandlerInterface $defaultHandler, ContainerInterface $container) {
                return $defaultHandler->remove($entities);
            },
            'exception_handler' => function (\Exception $e, $operation, ExceptionHandlerInterface $defaultHandler, ContainerInterface $container) {
                return $defaultHandler->createResponse($e, $operation);
            },
            'format_new_entity_values' => function (array $params, array $config, NewValuesFactoryInterface $defaultImpl, ContainerInterface $container) {
                return $defaultImpl->getValues($params, $config);
            },
            // allows to override default data mapper used by this specific controller
            'create_default_data_mapper' => function (ContainerInterface $container) use ($me) {
                return $me->getConfiguredService('data_mapper');
            },
            // optional
            'ignore_standard_validator' => false,
            // optional
            'entity_validation_method' => 'validate',
        ];

        $config = \array_merge($defaultConfig, $this->getConfig());

        if (!isset($config['entity'])) {
            throw new \RuntimeException("'entity' configuration property is not defined.");
        }
        if (!isset($config['hydration'])) {
            throw new \RuntimeException("'hydration' configuration property is not defined.");
        }

        return $config;
    }

    /**
     * @param array<string, mixed> $params
     */
    private function createEntity(array $params): object
    {
        /** @var array{'create_entity': callable} $config */
        $config = $this->getPreparedConfig();

        $defaultFactory = $this->getEntityFactory();

        /** @var ?object $entity */
        $entity = \call_user_func_array($config['create_entity'], [$params, $config, $defaultFactory, $this->getContainer()]);
        if (!$entity) {
            throw new \RuntimeException("Configured factory didn't create an object.");
        }

        return $entity;
    }

    private function getConfiguredService(string $serviceType): object
    {
        /** @var array<string, string> $config */
        $config = $this->getContainer()->getParameter(ModeraServerCrudExtension::CONFIG_KEY);

        try {
            /** @var object $service */
            $service = $this->getContainer()->get(isset($config[$serviceType]) ? $config[$serviceType] : '');

            return $service;
        } catch (\Exception $e) {
            throw BadConfigException::create($serviceType, $config, $e);
        }
    }

    protected function getPersistenceHandler(): PersistenceHandlerInterface
    {
        /** @var PersistenceHandlerInterface $persistenceHandler */
        $persistenceHandler = $this->getConfiguredService('persistence_handler');

        return $persistenceHandler;
    }

    private function getModelManager(): ModelManagerInterface
    {
        /** @var ModelManagerInterface $modelManager */
        $modelManager = $this->getConfiguredService('model_manager');

        return $modelManager;
    }

    private function getEntityValidator(): EntityValidatorInterface
    {
        /** @var EntityValidatorInterface $entityValidator */
        $entityValidator = $this->getConfiguredService('entity_validator');

        return $entityValidator;
    }

    private function getDataMapper(): DataMapperInterface
    {
        /** @var array{'create_default_data_mapper': callable} $config */
        $config = $this->getPreparedConfig();

        /** @var DataMapperInterface $dataMapper */
        $dataMapper = \call_user_func($config['create_default_data_mapper'], $this->getContainer());

        return $dataMapper;
    }

    private function getEntityFactory(): EntityFactoryInterface
    {
        /** @var EntityFactoryInterface $entityFactory */
        $entityFactory = $this->getConfiguredService('entity_factory');

        return $entityFactory;
    }

    private function getExceptionHandler(): ExceptionHandlerInterface
    {
        /** @var ExceptionHandlerInterface $exceptionHandler */
        $exceptionHandler = $this->getConfiguredService('exception_handler');

        return $exceptionHandler;
    }

    private function getHydrator(): HydrationService
    {
        /** @var HydrationService $hydrationService */
        $hydrationService = $this->getConfiguredService('hydrator');

        return $hydrationService;
    }

    private function getNewValuesFactory(): NewValuesFactoryInterface
    {
        /** @var NewValuesFactoryInterface $newValuesFactory */
        $newValuesFactory = $this->getConfiguredService('new_values_factory');

        return $newValuesFactory;
    }

    /**
     * @param array<string, mixed>|object $entity
     * @param array<string, mixed>        $params
     *
     * @return array<string, mixed>
     */
    final protected function hydrate(/* array|object */ $entity, array $params): array
    {
        /** @var array{'hydration': array{'profile'?: 'string', 'group'?: string}} $params */
        if (!isset($params['hydration']['profile'])) {
            $e = new BadRequestException('Hydration profile is not specified.');
            $e->setPath('/hydration/profile');
            $e->setParams($params);

            throw $e;
        }

        $profile = $params['hydration']['profile'];
        $groups = $params['hydration']['group'] ?? null;
        if (\is_string($groups)) {
            $groups = [$groups];
        }

        /** @var array<string, mixed> $config */
        $config = $this->getPreparedConfig();
        /** @var array<string, mixed> $hydrationConfig */
        $hydrationConfig = $config['hydration'];

        return $this->getHydrator()->hydrate($entity, $hydrationConfig, $profile, $groups);
    }

    /**
     * @return array<string, mixed>
     */
    final protected function createExceptionResponse(\Exception $e, string $operation): array
    {
        /** @var array{'exception_handler': callable} $config */
        $config = $this->getPreparedConfig();

        $exceptionHandler = $config['exception_handler'];

        return $exceptionHandler($e, $operation, $this->getExceptionHandler(), $this->getContainer());
    }

    /**
     * @Remote
     */
    public function createAction(array $params): array
    {
        try {
            $this->interceptAction('create', $params);

            if (!isset($params['record'])) {
                $e = new BadRequestException("'/record' hasn't been provided");
                $e->setParams($params);
                $e->setPath('/record');

                throw $e;
            }

            $entity = $this->createEntity($params);

            return $this->saveOrUpdateEntityAndCreateResponse($params, $entity, 'create');
        } catch (\Exception $e) {
            return $this->createExceptionResponse($e, ExceptionHandlerInterface::OPERATION_CREATE);
        }
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    private function saveOrUpdateEntityAndCreateResponse(array $params, object $entity, string $operationType): array
    {
        $config = $this->getPreparedConfig();

        /** @var ?callable $dataMapper */
        $dataMapper = $config['map_data_on_'.$operationType];
        /** @var callable $persistenceHandler */
        $persistenceHandler = $config[('create' === $operationType ? 'save' : 'update').'_entity_handler'];
        /** @var ?callable $validator */
        $validator = $config[('create' === $operationType ? 'new' : 'updated').'_entity_validator'];

        if ($dataMapper) {
            $dataMapper($params['record'], $entity, $this->getDataMapper(), $this->getContainer());
        }

        if ($validator) {
            /** @var ValidationResult $validationResult */
            $validationResult = $validator($params, $entity, $this->getEntityValidator(), $config, $this->getContainer());
            if ($validationResult->hasErrors()) {
                return \array_merge($validationResult->toArray(), [
                    'success' => false,
                ]);
            }
        }

        /** @var OperationResult $operationResult */
        $operationResult = $persistenceHandler($entity, $params, $this->getPersistenceHandler(), $this->getContainer());

        $response = [
            'success' => true,
        ];

        $response = \array_merge($response, $operationResult->toArray($this->getModelManager()));

        if (isset($params['hydration'])) {
            $response = \array_merge($response, ['result' => $this->hydrate($entity, $params)]);
        }

        return $response;
    }

    /**
     * Validates that result form query has exactly one value.
     *
     * @param object[]             $entities
     * @param array<string, mixed> $params
     *
     * @throws NothingFoundException
     * @throws MoreThanOneResultException
     */
    private function validateResultHasExactlyOneEntity(array $entities, array $params): void
    {
        if (\count($entities) > 1) {
            throw new MoreThanOneResultException(\sprintf('Query must return exactly one result, but %d were returned', \count($entities)));
        }

        if (0 === \count($entities)) {
            throw new NothingFoundException('Query must return exactly one result, but nothing was returned');
        }
    }

    /**
     * @param array<string, mixed> $params
     */
    private function interceptAction(string $actionName, array $params): void
    {
        /** @var InterceptorsManager $mgr */
        $mgr = $this->getContainer()->get('modera_server_crud.intercepting.interceptors_manager');

        $mgr->intercept($actionName, $params, $this);
    }

    /**
     * @Remote
     */
    public function getAction(array $params): array
    {
        /** @var array{'entity': string} $config */
        $config = $this->getPreparedConfig();

        try {
            $this->interceptAction('get', $params);

            $entities = $this->getPersistenceHandler()->query($config['entity'], $params);

            $this->validateResultHasExactlyOneEntity($entities, $params);

            $hydratedEntity = $this->hydrate($entities[0], $params);

            return [
                'success' => true,
                'result' => $hydratedEntity,
            ];
        } catch (\Exception $e) {
            return $this->createExceptionResponse($e, ExceptionHandlerInterface::OPERATION_GET);
        }
    }

    /**
     * @Remote
     */
    public function listAction(array $params): array
    {
        /** @var array{'entity': string} $config */
        $config = $this->getPreparedConfig();

        try {
            $this->interceptAction('list', $params);

            $total = $this->getPersistenceHandler()->getCount($config['entity'], $params);

            $hydratedItems = [];
            foreach ($this->getPersistenceHandler()->query($config['entity'], $params) as $entity) {
                $hydratedItems[] = $this->hydrate($entity, $params);
            }

            return [
                'success' => true,
                'items' => $hydratedItems,
                'total' => $total,
            ];
        } catch (\Exception $e) {
            return $this->createExceptionResponse($e, ExceptionHandlerInterface::OPERATION_LIST);
        }
    }

    /**
     * @Remote
     */
    public function removeAction(array $params): array
    {
        /** @var array{'entity': string, 'remove_entities_handler': callable} $config */
        $config = $this->getPreparedConfig();

        try {
            $this->interceptAction('remove', $params);

            if (!isset($params['filter'])) {
                $e = new BadRequestException("'/filter' parameter hasn't been provided");
                $e->setParams($params);
                $e->setPath('/filter');

                throw $e;
            }

            $persistenceHandler = $config['remove_entities_handler'];

            $entities = $this->getPersistenceHandler()->query($config['entity'], $params);

            /** @var OperationResult $operationResult */
            $operationResult = $persistenceHandler($entities, $params, $this->getPersistenceHandler(), $this->getContainer());

            return \array_merge(
                ['success' => true],
                $operationResult->toArray($this->getModelManager())
            );
        } catch (\Exception $e) {
            return $this->createExceptionResponse($e, ExceptionHandlerInterface::OPERATION_REMOVE);
        }
    }

    /**
     * @Remote
     */
    public function getNewRecordValuesAction(array $params): array
    {
        /** @var array{'format_new_entity_values': callable} $config */
        $config = $this->getPreparedConfig();

        try {
            $this->interceptAction('getNewRecordValues', $params);

            $newValuesFactory = $config['format_new_entity_values'];

            return $newValuesFactory($params, $config, $this->getNewValuesFactory(), $this->getContainer());
        } catch (\Exception $e) {
            return $this->createExceptionResponse($e, ExceptionHandlerInterface::OPERATION_GET_NEW_RECORD_VALUES);
        }
    }

    /**
     * @Remote
     */
    public function updateAction(array $params): array
    {
        /** @var array{'entity': string} $config */
        $config = $this->getPreparedConfig();

        try {
            $this->interceptAction('update', $params);

            if (!isset($params['record'])) {
                $e = new BadRequestException("'/record' hasn't been provided");
                $e->setParams($params);
                $e->setPath('/record');

                throw $e;
            }

            /** @var array<string, int|string> $recordParams */
            $recordParams = $params['record'];

            $missingPkFields = [];
            $query = [];
            foreach ($this->getPersistenceHandler()->resolveEntityPrimaryKeyFields($config['entity']) as $fieldName) {
                if (isset($recordParams[$fieldName])) {
                    $query[] = [
                        'property' => $fieldName,
                        'value' => 'eq:'.$recordParams[$fieldName],
                    ];
                } else {
                    $missingPkFields[] = $fieldName;
                }
            }
            if (\count($missingPkFields)) {
                $e = new BadRequestException('These primary key fields were not provided: '.\implode(', ', $missingPkFields));
                $e->setParams($params);
                $e->setPath('/');

                throw $e;
            }

            $entities = $this->getPersistenceHandler()->query($config['entity'], ['filter' => $query]);

            $this->validateResultHasExactlyOneEntity($entities, $params);

            return $this->saveOrUpdateEntityAndCreateResponse($params, $entities[0], 'update');
        } catch (\Exception $e) {
            return $this->createExceptionResponse($e, ExceptionHandlerInterface::OPERATION_UPDATE);
        }
    }

    /**
     * @Remote
     */
    public function batchUpdateAction(array $params): array
    {
        $config = $this->getPreparedConfig();

        try {
            /** @var callable $persistenceHandler */
            $persistenceHandler = $config['batch_update_entities_handler'];
            /** @var callable $dataMapper */
            $dataMapper = $config['map_data_on_update'];
            /** @var ?callable $validator */
            $validator = $config['updated_entity_validator'];

            /** @var array{'entity': string} $config */
            $config = $config;

            $this->interceptAction('batchUpdate', $params);

            if (\is_array($params['queries'] ?? null) && \is_array($params['record'] ?? null)) {
                $entities = [];
                foreach ($params['queries'] as $query) {
                    $entities = \array_merge($entities, $this->getPersistenceHandler()->query($config['entity'], $query));
                }

                $errors = [];
                foreach ($entities as $entity) {
                    $dataMapper($params['record'], $entity, $this->getDataMapper(), $this->getContainer());

                    if ($validator) {
                        /** @var ValidationResult $validationResult */
                        $validationResult = $validator($params, $entity, $this->getEntityValidator(), $config, $this->getContainer());
                        if ($validationResult->hasErrors()) {
                            $pkFields = $this->getPersistenceHandler()->resolveEntityPrimaryKeyFields($config['entity']);

                            $ids = [];
                            foreach ($pkFields as $fieldName) {
                                $ids[$fieldName] = Toolkit::getObjectPropertyValue($entity, $fieldName);
                            }

                            $errors[] = [
                                'id' => $ids,
                                'errors' => $validationResult->toArray(),
                            ];
                        }
                    }
                }

                if (0 === \count($errors)) {
                    $operationResult = $persistenceHandler($entities, $params, $this->getPersistenceHandler(), $this->getContainer());

                    return \array_merge($operationResult->toArray($this->getModelManager()), [
                        'success' => true,
                    ]);
                } else {
                    return [
                        'success' => false,
                        'errors' => $errors,
                    ];
                }
            } elseif (isset($params['records']) && \is_array($params['records'])) {
                $entities = [];
                $errors = [];
                foreach ($params['records'] as $recordParams) {
                    $missingPkFields = [];
                    $query = [];
                    foreach ($this->getPersistenceHandler()->resolveEntityPrimaryKeyFields($config['entity']) as $fieldName) {
                        if (isset($recordParams[$fieldName])) {
                            $query[] = [
                                'property' => $fieldName,
                                'value' => 'eq:'.$recordParams[$fieldName],
                            ];
                        } else {
                            $missingPkFields[] = $fieldName;
                        }
                    }

                    if (0 === \count($missingPkFields)) {
                        $entity = $this->getPersistenceHandler()->query($config['entity'], ['filter' => $query]);
                        $this->validateResultHasExactlyOneEntity($entity, $params);
                        $entity = $entity[0];

                        $entities[] = $entity;

                        $dataMapper($recordParams, $entity, $this->getDataMapper(), $this->getContainer());

                        if ($validator) {
                            $_params = $params;
                            $_params['record'] = $recordParams;
                            unset($_params['records']);

                            /** @var ValidationResult $validationResult */
                            $validationResult = $validator($_params, $entity, $this->getEntityValidator(), $config, $this->getContainer());
                            if ($validationResult->hasErrors()) {
                                $pkFields = $this->getPersistenceHandler()->resolveEntityPrimaryKeyFields($config['entity']);

                                $ids = [];
                                foreach ($pkFields as $fieldName) {
                                    $ids[$fieldName] = Toolkit::getObjectPropertyValue($entity, $fieldName);
                                }

                                $errors[] = [
                                    'id' => $ids,
                                    'errors' => $validationResult->toArray(),
                                ];
                            }
                        }
                    }
                }

                if (0 === \count($errors)) {
                    $operationResult = $persistenceHandler($entities, $params, $this->getPersistenceHandler(), $this->getContainer());

                    return \array_merge($operationResult->toArray($this->getModelManager()), [
                        'success' => true,
                    ]);
                } else {
                    return [
                        'success' => false,
                        'errors' => $errors,
                    ];
                }
            } else {
                $e = new BadRequestException(
                    "Invalid request structure. Valid request would either contain 'queries' and 'record' or 'records' keys."
                );
                $e->setParams($params);
                $e->setPath('/');

                throw $e;
            }
        } catch (\Exception $e) {
            return $this->createExceptionResponse($e, ExceptionHandlerInterface::OPERATION_UPDATE);
        }
    }
}
