<?php

namespace Modera\ServerCrudBundle\Persistence;

/**
 * @author    Sergei Lissovski <sergei.lissovski@modera.org>
 * @copyright 2016 Modera Foundation
 */
class DelegatePersistenceHandler implements PersistenceHandlerInterface
{
    /**
     * @var PersistenceHandlerInterface
     */
    protected $delegate;

    /**
     * @param PersistenceHandlerInterface $delegate
     */
    public function __construct(PersistenceHandlerInterface $delegate)
    {
        $this->delegate = $delegate;
    }

    /**
     * {@inheritdoc}
     */
    public function resolveEntityPrimaryKeyFields($entityClass)
    {
        return $this->delegate->resolveEntityPrimaryKeyFields($entityClass);
    }

    /**
     * {@inheritdoc}
     */
    public function save($entity)
    {
        return $this->delegate->save($entity);
    }

    /**
     * {@inheritdoc}
     */
    public function update($entity)
    {
        return $this->delegate->update($entity);
    }

    /**
     * {@inheritdoc}
     */
    public function updateBatch(array $entities)
    {
        return $this->delegate->updateBatch($entities);
    }

    /**
     * {@inheritdoc}
     */
    public function query($entityClass, array $params)
    {
        return $this->delegate->query($entityClass, $params);
    }

    /**
     * {@inheritdoc}
     */
    public function remove(array $entities)
    {
        return $this->delegate->remove($entities);
    }

    /**
     * {@inheritdoc}
     */
    public function getCount($entityClass, array $params)
    {
        return $this->delegate->getCount($entityClass, $params);
    }
}
