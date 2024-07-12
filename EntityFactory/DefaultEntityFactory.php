<?php

namespace Modera\ServerCrudBundle\EntityFactory;

/**
 * @author    Sergei Lissovski <sergei.lissovski@modera.org>
 * @copyright 2013 Modera Foundation
 */
class DefaultEntityFactory implements EntityFactoryInterface
{
    public function create(array $params, array $config): object
    {
        /** @var string $entityClass */
        $entityClass = $config['entity'];

        // if __construct method doesn't have any mandatory parameters, we will use it
        $useConstructor = false;
        foreach ($this->getEntityMethods($entityClass) as $refMethod) {
            if ('__construct' === $refMethod->getName()
                && $refMethod->isPublic()) {
                if (0 === \count($refMethod->getParameters())) {
                    $useConstructor = true;
                } else { // if all parameters are optional we still can use constructor
                    $allParametersOptional = true;
                    foreach ($refMethod->getParameters() as $refParameter) {
                        /** @var \ReflectionParameter $refParameter */
                        if (!$refParameter->isOptional()) {
                            $allParametersOptional = false;
                        }
                    }
                    $useConstructor = $allParametersOptional;
                }
            }
        }

        if ($useConstructor) {
            $entity = new $entityClass();
        } else {
            $serialized = \sprintf('O:%u:"%s":0:{}', \strlen($entityClass), $entityClass);
            $entity = \unserialize($serialized);
        }

        /** @var object $entity */
        $entity = $entity;

        return $entity;
    }

    /**
     * @return array<string, \ReflectionMethod>
     */
    private function getEntityMethods(string $entityClass): array
    {
        /** @var class-string $className */
        $className = $entityClass;

        $refClass = new \ReflectionClass($className);

        $arr = [];
        foreach ($refClass->getMethods() as $refMethod) {
            $arr[$refMethod->getName()] = $refMethod;
        }
        if ($refClass->getParentClass()) {
            foreach ($this->getEntityMethods($refClass->getParentClass()->getName()) as $methodName => $refMethod) {
                if (!\array_key_exists($methodName, $arr)) {
                    $arr[$methodName] = $refMethod;
                }
            }
        }

        return $arr;
    }
}
