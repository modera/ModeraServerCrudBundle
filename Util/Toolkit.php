<?php

namespace Modera\ServerCrudBundle\Util;

class Toolkit
{
    /**
     * @return \ReflectionProperty[]
     */
    public static function getObjectProperties(string $className): array
    {
        /** @var class-string $className */
        $className = $className;

        $refClass = new \ReflectionClass($className);

        $arr = [];
        foreach ($refClass->getProperties() as $refProperty) {
            $arr[$refProperty->getName()] = $refProperty;
        }
        if ($refClass->getParentClass()) {
            foreach (static::getObjectProperties($refClass->getParentClass()->getName()) as $propertyName => $refProperty) {
                if (!\array_key_exists($propertyName, $arr)) {
                    $arr[$propertyName] = $refProperty;
                }
            }
        }

        return $arr;
    }

    public static function getObjectProperty(string $className, string $propertyName): ?\ReflectionProperty
    {
        /** @var class-string $className */
        $className = $className;

        $refClass = new \ReflectionClass($className);

        try {
            return $refClass->getProperty($propertyName);
        } catch (\ReflectionException $e) {
            if ($refClass->getParentClass()) {
                return static::getObjectProperty($refClass->getParentClass()->getName(), $propertyName);
            }
        }

        return null;
    }

    /**
     * @return mixed Mixed value
     */
    public static function getObjectPropertyValue(object $obj, string $propertyName)
    {
        $className = \get_class($obj);
        $refProperty = static::getObjectProperty($className, $propertyName);

        if (!$refProperty) {
            throw new \RuntimeException("Unable to find a property '$propertyName' in '$className'.");
        }

        $refProperty->setAccessible(true);

        return $refProperty->getValue($obj);
    }
}
