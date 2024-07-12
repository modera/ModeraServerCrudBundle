<?php

namespace Modera\ServerCrudBundle\Util;

class JavaBeansObjectFieldsManager implements ObjectFieldsManagerInterface
{
    /**
     * @var array<string, \ReflectionClass>
     */
    private array $reflections = [];

    private string $isRegex = '/^is([A-Z0-9]+.*)$/';

    public function formatGetterName(string $key): string
    {
        if (\preg_match($this->isRegex, $key)) { // isBlah, isFoo, is111
            return $key;
        } else { // foo, bar, isotope
            return 'get'.\ucfirst($key);
        }
    }

    public function formatSetterName(string $key): string
    {
        if (\preg_match($this->isRegex, $key, $matches)) {
            return 'set'.$matches[1];
        } else {
            return 'set'.\ucfirst($key);
        }
    }

    private function getReflectionClass(object $object): \ReflectionClass
    {
        $index = \get_class($object);
        if (!isset($this->reflections[$index])) {
            $this->reflections[$index] = new \ReflectionClass($object);
        }

        return $this->reflections[$index];
    }

    public function get(object $object, string $key, array $args = [])
    {
        $methodName = $this->formatGetterName($key);
        $reflectionClass = $this->getReflectionClass($object);

        if ($reflectionClass->hasMethod($methodName) && $reflectionClass->getMethod($methodName)->isPublic()) {
            return $reflectionClass->getMethod($methodName)->invokeArgs($object, $args);
        }
    }

    public function set(object $object, string $key, array $args = [])
    {
        $methodName = $this->formatSetterName($key);
        $reflectionClass = $this->getReflectionClass($object);

        if ($reflectionClass->hasMethod($methodName) && $reflectionClass->getMethod($methodName)->isPublic()) {
            return $reflectionClass->getMethod($methodName)->invokeArgs($object, $args);
        }
    }
}
