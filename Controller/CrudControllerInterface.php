<?php

namespace Modera\ServerCrudBundle\Controller;

/**
 * Defines a set of methods that crud controllers must have. Expected structure of $params arguments is up to
 * implementations.
 *
 * @author    Sergei Lissovski <sergei.lissovski@modera.org>
 * @copyright 2014 Modera Foundation
 */
interface CrudControllerInterface
{
    /**
     * Method must return an array that can be used on client-side as a template for creating a new record.
     *
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    public function getNewRecordValuesAction(array $params): array;

    /**
     * Method is responsible for creating a new record and persisting it to database.
     *
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    public function createAction(array $params): array;

    /**
     * Method is responsible for updating already existing in persistent storage piece of data.
     *
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    public function updateAction(array $params): array;

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    public function batchUpdateAction(array $params): array;

    /**
     * Method must return hydrated instance of your record by querying database using query provided in
     * $params.
     *
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    public function getAction(array $params): array;

    /**
     * Method must return many hydrated records by querying database using query defined in $params.
     *
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    public function listAction(array $params): array;

    /**
     * Method is responsible for deleting one or many records by analyzing a query provided by $params.
     *
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    public function removeAction(array $params): array;
}
