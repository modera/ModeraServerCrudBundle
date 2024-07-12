<?php

namespace Modera\ServerCrudBundle\Hydration;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

/**
 * Service is responsible for converting given entity/entities to something that can be sent back to client-side.
 *
 * @author    Sergei Lissovski <sergei.lissovski@modera.org>
 * @copyright 2013 Modera Foundation
 */
class HydrationService
{
    private ContainerInterface $container;

    private PropertyAccessorInterface $accessor;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $this->accessor = PropertyAccess::createPropertyAccessor();
    }

    /**
     * @param callable|string[]|array<string, string>|mixed $hydrator
     * @param array<string, mixed>|object                   $objectOrArray
     *
     * @return array<mixed>
     */
    private function invokeHydrator($hydrator, /* array|object */ $objectOrArray): array
    {
        if (\is_callable($hydrator)) {
            $result = $hydrator($objectOrArray, $this->container);
            if (\is_array($result)) {
                return $result;
            }
        } elseif (\is_array($hydrator)) {
            $result = [];

            foreach ($hydrator as $key => $propertyPath) {
                $key = \is_numeric($key) ? $propertyPath : $key;

                try {
                    $result[$key] = $this->accessor->getValue($objectOrArray, $propertyPath);
                } catch (\Exception $e) {
                    $message = \sprintf("Unable to resolve expression '%s'", $propertyPath);
                    if (\is_object($objectOrArray)) {
                        $message .= \sprintf(' on %s', \get_class($objectOrArray));
                    }

                    throw new \RuntimeException($message, 0, $e);
                }
            }

            return $result;
        }

        throw new \RuntimeException('Invalid hydrator definition');
    }

    /**
     * @param array<mixed> $currentResult
     * @param array<mixed> $hydratorResult
     *
     * @return array<mixed>
     */
    private function mergeHydrationResult(array $currentResult, array $hydratorResult, HydrationProfile $profile, string $groupName): array
    {
        if ($profile->isGroupingNeeded()) {
            $currentResult[$groupName] = $hydratorResult;
        } else {
            $currentResult = \array_merge($currentResult, $hydratorResult);
        }

        return $currentResult;
    }

    /**
     * @param array<string, mixed>|object $objectOrArray
     * @param array<string, mixed>        $config
     * @param ?string[]                   $groups
     *
     * @return array<mixed>
     */
    public function hydrate(/* array|object */ $objectOrArray, array $config, string $profileName, ?array $groups = null): array
    {
        $configAnalyzer = new ConfigAnalyzer($config);
        $profile = $configAnalyzer->getProfileDefinition($profileName);

        if (null === $groups) { // going to hydrate all groups if none are explicitly specified
            $result = [];

            foreach ($profile->getGroups() as $groupName) {
                $hydrator = $configAnalyzer->getGroupDefinition($groupName);

                $hydratorResult = $this->invokeHydrator($hydrator, $objectOrArray);

                $result = $this->mergeHydrationResult($result, $hydratorResult, $profile, $groupName);
            }

            return $result;
        } else {
            $groupsToUse = \array_values($groups);

            // if there's only one group given then no grouping is going to be used
            if (1 === \count($groupsToUse)) {
                $hydrator = $configAnalyzer->getGroupDefinition($groupsToUse[0]);

                return $this->invokeHydrator($hydrator, $objectOrArray);
            } else {
                $result = [];

                foreach ($groupsToUse as $groupName) {
                    $hydrator = $configAnalyzer->getGroupDefinition($groupName);

                    $hydratorResult = $this->invokeHydrator($hydrator, $objectOrArray);

                    $result = $this->mergeHydrationResult($result, $hydratorResult, $profile, $groupName);
                }

                return $result;
            }
        }
    }
}
