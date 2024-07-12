<?php

namespace Modera\ServerCrudBundle\Intercepting;

use Modera\ExpanderBundle\Ext\ContributorInterface;
use Modera\ServerCrudBundle\Controller\AbstractCrudController;

/**
 * Handles interceptors invoking process.
 *
 * @author    Sergei Lissovski <sergei.lissovski@modera.org>
 * @copyright 2014 Modera Foundation
 */
class InterceptorsManager
{
    private ContributorInterface $interceptorsProvider;

    public function __construct(ContributorInterface $interceptorsProvider)
    {
        $this->interceptorsProvider = $interceptorsProvider;
    }

    /**
     * @param array<string, mixed> $params
     *
     * @throws InvalidInterceptorException
     * @throws \InvalidArgumentException   When bad $actionName is given
     */
    public function intercept(string $actionName, array $params, AbstractCrudController $controller): void
    {
        if (!\in_array($actionName, ['create', 'get', 'list', 'remove', 'update', 'getNewRecordValues', 'batchUpdate'])) {
            throw new \InvalidArgumentException(\sprintf('Action name can only be either of these: create, get, list or remove, update, getNewRecordValues, but "%s" given', $actionName));
        }

        foreach ($this->interceptorsProvider->getItems() as $interceptor) {
            /** @var object $interceptor */
            if (!($interceptor instanceof ControllerActionsInterceptorInterface)) {
                throw InvalidInterceptorException::create($interceptor);
            }
            $interceptor->{'on'.\ucfirst($actionName)}($params, $controller);
        }
    }
}
