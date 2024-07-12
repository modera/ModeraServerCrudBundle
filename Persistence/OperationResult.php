<?php

namespace Modera\ServerCrudBundle\Persistence;

/**
 * @author    Sergei Lissovski <sergei.lissovski@modera.org>
 * @copyright 2013 Modera Foundation
 */
class OperationResult
{
    public const TYPE_ENTITY_CREATED = 'entity_created';
    public const TYPE_ENTITY_UPDATED = 'entity_updated';
    public const TYPE_ENTITY_REMOVED = 'entity_removed';

    /**
     * @var array{
     *     'entity_class': string,
     *     'operation': string,
     *     'id': int|string,
     * }[]
     */
    private array $entries = [];

    /**
     * @param int|string $id
     */
    public function reportEntity(string $entityClass, $id, string $operation): void
    {
        $this->entries[] = [
            'entity_class' => $entityClass,
            'operation' => $operation,
            'id' => $id,
        ];
    }

    /**
     * @return array<int, array{'entity_class': string, 'id': int|string}>
     */
    private function findEntriesByOperation(string $operationName): array
    {
        $result = [];

        foreach ($this->entries as $entry) {
            if ($entry['operation'] === $operationName) {
                $result[] = [
                    'entity_class' => $entry['entity_class'],
                    'id' => $entry['id'],
                ];
            }
        }

        return $result;
    }

    /**
     * @return array<int, array{'entity_class': string, 'id': int|string}>
     */
    public function getCreatedEntities(): array
    {
        return $this->findEntriesByOperation(self::TYPE_ENTITY_CREATED);
    }

    /**
     * @return array<int, array{'entity_class': string, 'id': int|string}>
     */
    public function getUpdatedEntities(): array
    {
        return $this->findEntriesByOperation(self::TYPE_ENTITY_UPDATED);
    }

    /**
     * @return array<int, array{'entity_class': string, 'id': int|string}>
     */
    public function getRemovedEntities(): array
    {
        return $this->findEntriesByOperation(self::TYPE_ENTITY_REMOVED);
    }

    /**
     * @return array<string, array<string, array<int|string>>>
     */
    public function toArray(ModelManagerInterface $modelMgr): array
    {
        $result = [];

        $mapping = [
            self::TYPE_ENTITY_CREATED => 'created_models',
            self::TYPE_ENTITY_UPDATED => 'updated_models',
            self::TYPE_ENTITY_REMOVED => 'removed_models',
        ];

        foreach ($this->entries as $entry) {
            $key = $mapping[$entry['operation']];

            if (!isset($result[$key])) {
                $result[$key] = [];
            }

            $modelName = $modelMgr->generateModelIdFromEntityClass($entry['entity_class']);

            if (!isset($result[$key][$modelName])) {
                $result[$key][$modelName] = [];
            }

            $result[$key][$modelName][] = $entry['id'];
        }

        return $result;
    }

    /**
     * A new instance of OperationResult is returned.
     */
    public function merge(OperationResult $result): self
    {
        $new = new self();
        foreach (\array_merge($this->getCreatedEntities(), $result->getCreatedEntities()) as $entry) {
            $new->reportEntity($entry['entity_class'], $entry['id'], self::TYPE_ENTITY_CREATED);
        }
        foreach (\array_merge($this->getUpdatedEntities(), $result->getUpdatedEntities()) as $entry) {
            $new->reportEntity($entry['entity_class'], $entry['id'], self::TYPE_ENTITY_UPDATED);
        }
        foreach (\array_merge($this->getRemovedEntities(), $result->getRemovedEntities()) as $entry) {
            $new->reportEntity($entry['entity_class'], $entry['id'], self::TYPE_ENTITY_REMOVED);
        }

        return $new;
    }
}
