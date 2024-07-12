<?php

namespace Modera\ServerCrudBundle\NewValuesFactory;

use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Will try to find a static method 'formatNewValues' on an entity resolved by using $config parameter
 * passed to getValues() method, if the method is found the following values will be passed:
 * $params, $config and instance of ContainerInterface. The static method must return a serializable data
 * structure that eventually will be sent bank to client-side.
 *
 * @author    Sergei Lissovski <sergei.lissovski@modera.org>
 * @copyright 2014 Modera Foundation
 */
class DefaultNewValuesFactory implements NewValuesFactoryInterface
{
    private ContainerInterface $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function getValues(array $params, array $config): array
    {
        /** @var string $entityClass */
        $entityClass = $config['entity'];

        $methodName = 'formatNewValues';

        if (\method_exists($entityClass, $methodName)) {
            /** @var class-string $entityClass */
            $entityClass = $entityClass;
            $reflClass = new \ReflectionClass($entityClass);
            $reflMethod = $reflClass->getMethod($methodName);
            if ($reflMethod->isStatic()) {
                $result = $reflMethod->invokeArgs(null, [$params, $config, $this->container]);
                /** @var array<string, mixed> $result */
                if (\is_array($result)) {
                    return $result;
                }
            }
        }

        return [];
    }
}
