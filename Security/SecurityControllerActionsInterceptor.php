<?php

namespace Modera\ServerCrudBundle\Security;

use Modera\ServerCrudBundle\Controller\AbstractCrudController;
use Modera\ServerCrudBundle\Intercepting\ControllerActionsInterceptorInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * Interceptor allows to add security enforcement logic to AbstractCrudController.
 *
 * @author    Sergei Lissovski <sergei.lissovski@modera.org>
 * @copyright 2014 Modera Foundation
 */
class SecurityControllerActionsInterceptor implements ControllerActionsInterceptorInterface
{
    private AuthorizationCheckerInterface $authorizationChecker;

    public function __construct(AuthorizationCheckerInterface $authorizationChecker)
    {
        $this->authorizationChecker = $authorizationChecker;
    }

    /**
     * @param callable|string $role
     */
    private function throwAccessDeniedException($role): void
    {
        $msg = \is_callable($role)
             ? 'You are not allowed to perform this action.'
             : "Security role '$role' is required to perform this action.";

        $e = new AccessDeniedHttpException($msg);
        if (!\is_callable($role)) {
            $e->setRole($role);
        }

        throw $e;
    }

    /**
     * @param array<string, mixed> $params
     */
    public function checkAccess(string $actionName, array $params, AbstractCrudController $controller): void
    {
        $config = $controller->getPreparedConfig();

        if (isset($config['security'])) {
            /** @var array<string, mixed> $security */
            $security = $config['security'];

            if (isset($security['role'])) {
                /** @var string $role */
                $role = $security['role'];

                if (!$this->authorizationChecker->isGranted($role)) {
                    $this->throwAccessDeniedException($role);
                }
            }

            if (\is_array($security['actions'] ?? null) && isset($security['actions'][$actionName])) {
                /** @var callable|string $role */
                $role = $security['actions'][$actionName];

                if (\is_callable($role)) {
                    if (!\call_user_func($role, $this->authorizationChecker, $params, $actionName)) {
                        $this->throwAccessDeniedException($role);
                    }
                } elseif (!$this->authorizationChecker->isGranted($role)) {
                    $this->throwAccessDeniedException($role);
                }
            }
        }
    }

    public function onCreate(array $params, AbstractCrudController $controller): void
    {
        $this->checkAccess('create', $params, $controller);
    }

    public function onUpdate(array $params, AbstractCrudController $controller): void
    {
        $this->checkAccess('update', $params, $controller);
    }

    public function onBatchUpdate(array $params, AbstractCrudController $controller): void
    {
        $this->checkAccess('batchUpdate', $params, $controller);
    }

    public function onGet(array $params, AbstractCrudController $controller): void
    {
        $this->checkAccess('get', $params, $controller);
    }

    public function onList(array $params, AbstractCrudController $controller): void
    {
        $this->checkAccess('list', $params, $controller);
    }

    public function onRemove(array $params, AbstractCrudController $controller): void
    {
        $this->checkAccess('remove', $params, $controller);
    }

    public function onGetNewRecordValues(array $params, AbstractCrudController $controller): void
    {
        $this->checkAccess('getNewRecordValues', $params, $controller);
    }
}
