<?php

namespace Modera\ServerCrudBundle\ExceptionHandling;

/**
 * Implementations are responsible for converting exception to a response that will be sent back to client-side.
 *
 * @author    Sergei Lissovski <sergei.lissovski@modera.org>
 * @copyright 2013 Modera Foundation
 */
interface ExceptionHandlerInterface
{
    public const OPERATION_CREATE = 'create';
    public const OPERATION_UPDATE = 'update';
    public const OPERATION_REMOVE = 'remove';
    public const OPERATION_LIST = 'list';
    public const OPERATION_GET = 'get';
    public const OPERATION_GET_NEW_RECORD_VALUES = 'get_new_record_values';

    /**
     * @return array<string, mixed>
     */
    public function createResponse(\Exception $e, string $operation): array;
}
