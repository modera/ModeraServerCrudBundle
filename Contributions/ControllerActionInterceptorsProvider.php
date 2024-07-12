<?php

namespace Modera\ServerCrudBundle\Contributions;

use Modera\ExpanderBundle\Ext\ContributorInterface;
use Modera\ServerCrudBundle\Intercepting\ControllerActionsInterceptorInterface;
use Modera\ServerCrudBundle\Security\SecurityControllerActionsInterceptor;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * @author    Sergei Lissovski <sergei.lissovski@modera.org>
 * @copyright 2014 Modera Foundation
 */
class ControllerActionInterceptorsProvider implements ContributorInterface
{
    private AuthorizationCheckerInterface $authorizationChecker;

    /**
     * @var ?ControllerActionsInterceptorInterface[]
     */
    private ?array $items = null;

    public function __construct(ContainerInterface $container)
    {
        /** @var AuthorizationCheckerInterface $authorizationChecker */
        $authorizationChecker = $container->get('security.authorization_checker');
        $this->authorizationChecker = $authorizationChecker;
    }

    public function getItems(): array
    {
        if (!$this->items) {
            $this->items = [
                new SecurityControllerActionsInterceptor($this->authorizationChecker),
            ];
        }

        return $this->items;
    }
}
