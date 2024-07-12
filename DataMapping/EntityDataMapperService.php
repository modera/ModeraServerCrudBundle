<?php

namespace Modera\ServerCrudBundle\DataMapping;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadataInfo;
use Doctrine\Persistence\ManagerRegistry;
use Modera\ExpanderBundle\Ext\ContributorInterface;
use Modera\ServerCrudBundle\DataMapping\MethodInvocation\MethodInvocationParametersProviderInterface;
use Modera\ServerCrudBundle\Util\JavaBeansObjectFieldsManager;
use Modera\ServerCrudBundle\Util\Toolkit;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * @author Sergei Vizel <sergei.vizel@modera.org>
 * @copyright 2024 Modera Foundation
 *
 * Service is responsible for inspect the data that usually comes from client-side and update the database. All
 * relation types supported by Doctrine are supported by this service as well - ONE_TO_ONE, ONE_TO_MANY,
 * MANY_TO_ONE, MANY_TO_MANY. Service is capable to properly update all relation types ( owning, inversed-side )
 * even when entity classes do not define them. Also, this service is smart enough to properly cast provided
 * values to the types are defined in doctrine mappings, that is - if string "10.2" is provided, but the field
 * it was provided for is mapped as "float", then the conversion to float value will be automatically done - this is
 * especially useful if your setter method have some logic not just assigning a new value to a class field.
 *
 * In order for this class to work, your security principal ( implementation of UserInterface ),
 * must implement {@class PreferencesAwareUserInterface}.
 */
class EntityDataMapperService
{
    private ManagerRegistry $doctrineRegistry;

    private TokenStorageInterface $tokenStorage;

    private JavaBeansObjectFieldsManager $fm;

    private MethodInvocationParametersProviderInterface $paramsProvider;

    private ContributorInterface $complexFiledValueConvertersProvider;

    public function __construct(
        ManagerRegistry $doctrineRegistry,
        TokenStorageInterface $tokenStorage,
        JavaBeansObjectFieldsManager $fm,
        MethodInvocationParametersProviderInterface $paramsProvider,
        ContributorInterface $complexFieldValueConvertersProvider
    ) {
        $this->doctrineRegistry = $doctrineRegistry;
        $this->tokenStorage = $tokenStorage;
        $this->fm = $fm;
        $this->paramsProvider = $paramsProvider;
        $this->complexFiledValueConvertersProvider = $complexFieldValueConvertersProvider;
    }

    private function getAuthenticatedUser(): ?UserInterface
    {
        $token = $this->tokenStorage->getToken();
        if (null === $token) {
            return null;
        }

        return $token->getUser();
    }

    /**
     * @return array<string, string>
     *
     * @throws \RuntimeException
     */
    protected function getUserPreferences(): array
    {
        $user = $this->getAuthenticatedUser();

        if (!$user) {
            throw new \RuntimeException('No authenticated user available in your session.');
        }

        if (!($user instanceof PreferencesAwareUserInterface)) {
            throw new \RuntimeException('Currently authenticated user must implement PreferencesAwareUserInterface!');
        }

        return $user->getPreferences();
    }

    protected function getPreferencesValue(string $keyName): string
    {
        $preferences = $this->getUserPreferences();
        if (!isset($preferences[$keyName])) {
            throw new \RuntimeException(\sprintf('User preferences must contain configuration for "%s"', $keyName));
        }

        return $preferences[$keyName];
    }

    /**
     * @param mixed $clientValue Mixed value
     */
    public function convertBoolean($clientValue): bool
    {
        return true === $clientValue || 1 === $clientValue || \in_array($clientValue, ['1', 'on', 'true'], true);
    }

    /**
     * @param mixed $clientValue Mixed value
     */
    public function convertDate($clientValue): ?\DateTimeInterface
    {
        if (\is_string($clientValue) && '' !== $clientValue) {
            $format = $this->getPreferencesValue(PreferencesAwareUserInterface::SETTINGS_DATE_FORMAT);

            $rawClientValue = $clientValue;
            $clientValue = \DateTime::createFromFormat($format, $clientValue);
            if (!$clientValue) {
                throw new \RuntimeException("Unable to map a date, unable to transform date-value of '$rawClientValue' to '$format' format.");
            }

            return $clientValue;
        }

        return null;
    }

    /**
     * @param mixed $clientValue Mixed value
     */
    public function convertDateTime($clientValue): ?\DateTimeInterface
    {
        if (\is_string($clientValue) && '' !== $clientValue) {
            $format = $this->getPreferencesValue(PreferencesAwareUserInterface::SETTINGS_DATETIME_FORMAT);

            $rawClientValue = $clientValue;
            $clientValue = \DateTime::createFromFormat($format, $clientValue);
            if (!$clientValue) {
                throw new \RuntimeException("Unable to map a datetime, unable to transform date-value of '$rawClientValue' to '$format' format.");
            }

            return $clientValue;
        }

        return null;
    }

    /**
     * @param mixed $clientValue Mixed value
     *
     * @return mixed Mixed value
     */
    public function convertValue($clientValue, string $fieldType)
    {
        switch ($fieldType) {
            case 'bool':
            case 'boolean':
                return $this->convertBoolean($clientValue);
            case 'date':
                return $this->convertDate($clientValue);
            case 'datetime':
                return $this->convertDateTime($clientValue);
        }

        return $clientValue;
    }

    /**
     * Be aware, that "id" property will never be mapped to you entities even if it is provided
     * in $params, we presume that it will always be generated automatically.
     *
     * @param array<string, mixed> $params        Data usually received from client-side
     * @param string[]             $allowedFields Fields names you want to allow to be mapped
     *
     * @throws \RuntimeException
     */
    public function mapEntity(object $entity, array $params, array $allowedFields): void
    {
        $em = $this->doctrineRegistry->getManagerForClass(\get_class($entity));
        if (!$em) {
            throw new \RuntimeException(\sprintf('Manager for class "%s" not found', \get_class($entity)));
        }

        $entityMethods = \get_class_methods($entity);

        /** @var ClassMetadataInfo $metadata */
        $metadata = $em->getClassMetadata(\get_class($entity));

        foreach ($metadata->getFieldNames() as $fieldName) {
            if (!\in_array($fieldName, $allowedFields) || 'id' === $fieldName) { // ID is always generated dynamically
                continue;
            }

            if (isset($params[$fieldName])) {
                $value = $params[$fieldName];
                $mapping = $metadata->getFieldMapping($fieldName);

                // if a field is number and at the same time its value was not provided,
                // then we are not touching it at all, if the model has specified
                // a default value for it - fine, everything's going to be fine, otherwise
                // Doctrine will look if this field isNullable etc ... and throw
                // an exception if needed
                if (!(
                    \in_array($mapping['type'], ['integer', 'smallint', 'bigint', 'decimal', 'float'])
                    && '' === $value
                )) {
                    try {
                        $methodParams = $this->paramsProvider->getParameters(\get_class($entity), $this->fm->formatSetterName($fieldName));

                        $convertedValue = null;
                        if (\is_object($value) || \is_array($value)) {
                            foreach ($this->complexFiledValueConvertersProvider->getItems() as $converter) {
                                if ($converter instanceof ComplexFieldValueConverterInterface) {
                                    if ($converter->isResponsible($value, $fieldName, $metadata)) {
                                        $convertedValue = $converter->convert($value, $fieldName, $metadata);
                                        break;
                                    }
                                }
                            }
                        }

                        if (null === $convertedValue) {
                            $convertedValue = $this->convertValue($value, $mapping['type']);
                        }

                        $methodParams = \array_merge([$convertedValue], $methodParams);
                        $this->fm->set($entity, $fieldName, $methodParams);
                    } catch (\Exception $e) {
                        throw new \RuntimeException("Something went wrong during mapping of a scalar field '$fieldName' - ".$e->getMessage(), 0, $e);
                    }
                }
            }
        }

        foreach ($metadata->getAssociationMappings() as $mapping) {
            $fieldName = $mapping['fieldName'];

            if (!\in_array($fieldName, $allowedFields)) {
                continue;
            }
            if (null !== ($params[$fieldName] ?? null)) {
                if (\in_array($mapping['type'], [ClassMetadataInfo::ONE_TO_ONE, ClassMetadataInfo::MANY_TO_ONE])) {
                    $rawValue = $params[$fieldName];

                    $methodParams = $this->paramsProvider->getParameters(\get_class($entity), $this->fm->formatSetterName($fieldName));

                    if ('-' === $rawValue) {
                        $this->fm->set($entity, $fieldName, \array_merge([null], $methodParams));
                    } else {
                        $value = $em->getRepository($mapping['targetEntity'])->find($rawValue);
                        if ($value) {
                            $this->fm->set($entity, $fieldName, \array_merge([$value], $methodParams));
                        }
                    }
                } else { // one_to_many, many_to_many
                    /** @var array<int|string>|string $rawValue */
                    $rawValue = $params[$fieldName];

                    /** @var ?Collection $col */
                    $col = $metadata->getFieldValue($entity, $fieldName);

                    // if it is a new entity ( you should remember, the entity's constructor is not invoked )
                    // it will have no collection initialized, because this usually happens in the constructor
                    if (!$col) {
                        $col = new ArrayCollection();
                        $this->fm->set($entity, $fieldName, [$col]);
                    }

                    $oldIds = $this->extractIds($col);
                    $newIds = \is_array($rawValue) ? $rawValue : \explode(', ', $rawValue);
                    $idsToDelete = \array_diff($oldIds, $newIds);
                    $idsToAdd = \array_diff($newIds, $oldIds);

                    $entitiesToDelete = $this->getEntitiesByIds($idsToDelete, $mapping['targetEntity']);
                    $entitiesToAdd = $this->getEntitiesByIds($idsToAdd, $mapping['targetEntity']);

                    /*
                     * At first it will be checked if removeXXX/addXXX methods exist, if they
                     * do, then they will be used, otherwise we will try to manage
                     * relation manually
                     */
                    $removeMethod = 'remove'.\ucfirst($this->singlifyWord($fieldName));
                    if (\in_array($removeMethod, $entityMethods) && \count($idsToDelete) > 0) {
                        foreach ($entitiesToDelete as $refEntity) {
                            $methodParams = \array_merge(
                                [$refEntity],
                                $this->paramsProvider->getParameters(\get_class($entity), $removeMethod)
                            );
                            /** @var callable $callback */
                            $callback = [$entity, $removeMethod];
                            \call_user_func_array($callback, $methodParams);
                        }
                    } else {
                        foreach ($entitiesToDelete as $refEntity) {
                            if ($col->contains($refEntity)) {
                                $col->removeElement($refEntity);

                                if (!$mapping['mappedBy']) {
                                    continue;
                                }

                                if (ClassMetadataInfo::MANY_TO_MANY === $mapping['type']) {
                                    /** @var ClassMetadataInfo $refMetadata */
                                    $refMetadata = $em->getClassMetadata(\get_class($refEntity));

                                    // bidirectional
                                    if ($refMetadata->hasAssociation($mapping['mappedBy'])) {
                                        $inversedCol = $refMetadata->getFieldValue($refEntity, $mapping['mappedBy']);
                                        if ($inversedCol instanceof Collection) {
                                            $inversedCol->removeElement($entity);
                                        }
                                    }
                                } else {
                                    // nulling the other side of relation
                                    $this->fm->set($refEntity, $mapping['mappedBy'], [null]);
                                }
                            }
                        }
                    }

                    $addMethod = 'add'.\ucfirst($this->singlifyWord($fieldName));
                    if (\in_array($addMethod, $entityMethods) && \count($idsToAdd) > 0) {
                        foreach ($entitiesToAdd as $refEntity) {
                            $methodParams = \array_merge(
                                [$refEntity],
                                $this->paramsProvider->getParameters(\get_class($entity), $addMethod)
                            );
                            /** @var callable $callback */
                            $callback = [$entity, $addMethod];
                            \call_user_func_array($callback, $methodParams);
                        }
                    } else {
                        foreach ($entitiesToAdd as $refEntity) {
                            if (!$col->contains($refEntity)) {
                                $col->add($refEntity);

                                if (!$mapping['mappedBy']) {
                                    continue;
                                }

                                if (ClassMetadataInfo::MANY_TO_MANY == $mapping['type']) {
                                    /** @var ClassMetadataInfo $refMetadata */
                                    $refMetadata = $em->getClassMetadata(\get_class($refEntity));

                                    // bidirectional
                                    if ($refMetadata->hasAssociation($mapping['mappedBy'])) {
                                        $inversedCol = $refMetadata->getFieldValue($refEntity, $mapping['mappedBy']);
                                        if ($inversedCol instanceof Collection) {
                                            $inversedCol->add($entity);
                                        }
                                    }
                                } else {
                                    $this->fm->set($refEntity, $mapping['mappedBy'], [$entity]);
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    private function singlifyWord(string $plural): string
    {
        if (\strlen($plural) > 4 && 'ies' === \substr($plural, -3)) {
            return \substr($plural, 0, -3);
        } elseif (\strlen($plural) > 3 && 'es' === \substr($plural, -2) && 'e' === $plural[strlen($plural) - 3] || 'e' === $plural[strlen($plural) - 2]) { // employEEs
            return \substr($plural, 0, -1);
        } elseif (\strlen($plural) > 3 && 'es' === \substr($plural, -2)) {
            return \substr($plural, 0, -2);
        }

        return \substr($plural, 0, -1); // just 's'
    }

    /**
     * @return array<int|string>
     */
    private function extractIds(?Collection $col, string $method = 'getId'): array
    {
        if (!$col) {
            return [];
        }

        $ids = [];
        /** @var object $item */
        foreach ($col as $item) {
            if (\in_array($method, \get_class_methods($item))) {
                $ids[] = $item->{$method}();
            } else {
                $ids[] = Toolkit::getObjectPropertyValue($item, 'id');
            }
        }

        /** @var array<int|string> $ids */
        $ids = $ids;

        return $ids;
    }

    /**
     * @param array<int|string> $ids
     *
     * @return object[]
     */
    private function getEntitiesByIds(array $ids, string $entityFqcn): array
    {
        if (0 === \count($ids)) {
            return [];
        }

        /** @var class-string $className */
        $className = $entityFqcn;

        /** @var ?EntityManagerInterface $em */
        $em = $this->doctrineRegistry->getManagerForClass($className);
        if (!$em) {
            throw new \RuntimeException(\sprintf('Manager for class "%s" not found', $className));
        }

        $qb = $em->createQueryBuilder();
        $qb->select('e')
            ->from($entityFqcn, 'e')
            ->where($qb->expr()->in('e.id', $ids))
        ;

        /** @var object[] $result */
        $result = $qb->getQuery()->getResult();

        return $result;
    }
}
