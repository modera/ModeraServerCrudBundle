<?php

namespace Modera\ServerCrudBundle\Intercepting;

use Modera\ServerCrudBundle\Controller\AbstractCrudController;

/**
 * You can use this class when you need to create an interceptor but want to spare yourself from writing empty
 * implementation for all methods that {@class ControllerActionsInterceptorInterface} interface defines.
 *
 * @author    Sergei Lissovski <sergei.lissovski@modera.org>
 * @copyright 2014 Modera Foundation
 */
class ControllerActionsInterceptor implements ControllerActionsInterceptorInterface
{
    public function onCreate(array $params, AbstractCrudController $controller): void
    {
    }

    public function onUpdate(array $params, AbstractCrudController $controller): void
    {
    }

    public function onBatchUpdate(array $params, AbstractCrudController $controller): void
    {
    }

    public function onGet(array $params, AbstractCrudController $controller): void
    {
    }

    public function onList(array $params, AbstractCrudController $controller): void
    {
    }

    public function onRemove(array $params, AbstractCrudController $controller): void
    {
    }

    public function onGetNewRecordValues(array $params, AbstractCrudController $controller): void
    {
    }
}
