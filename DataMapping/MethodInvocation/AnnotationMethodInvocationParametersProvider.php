<?php

namespace Modera\ServerCrudBundle\DataMapping\MethodInvocation;

use Doctrine\Common\Annotations\AnnotationReader;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @author Sergei Vizel <sergei.vizel@modera.org>
 * @copyright 2024 Modera Foundation
 */
class AnnotationMethodInvocationParametersProvider implements MethodInvocationParametersProviderInterface
{
    private AnnotationReader $annotationReader;

    private ContainerInterface $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $this->annotationReader = new AnnotationReader();
    }

    public function getParameters(string $fqcn, string $methodName): array
    {
        try {
            return $this->doGetParameters($fqcn, $methodName);
        } catch (\Exception $e) {
            throw new \RuntimeException("Unable to properly handle DataMapping\\MethodInvocation\\Params annotation on $fqcn::$methodName.", 0, $e);
        }
    }

    /**
     * @return array<?object>
     */
    protected function doGetParameters(string $fqcn, string $methodName): array
    {
        /** @var ?Params $annotation */
        $annotation = $this->annotationReader->getMethodAnnotation(new \ReflectionMethod($fqcn, $methodName), Params::class);
        if ($annotation) {
            if (!\is_array($annotation->value)) {
                throw new \RuntimeException('Value of the annotation must always be an array!');
            }

            $result = [];
            foreach ($annotation->value as $serviceName) {
                if ('*' === $serviceName[\strlen($serviceName) - 1]) { // optional service
                    $result[] = $this->container->get($serviceName, ContainerInterface::NULL_ON_INVALID_REFERENCE);
                } else {
                    $result[] = $this->container->get($serviceName, ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE);
                }
            }

            return $result;
        }

        return [];
    }
}
