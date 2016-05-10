<?php

namespace Modera\ServerCrudBundle\Exceptions;

/**
 * This exception can be thrown when an invalid configuration found during executing.
 *
 * @author    Alex Plaksin <alex.plaksin@modera.org>
 * @copyright 2016 Modera Foundation
 */
class BadConfigException extends \RuntimeException
{
    /**
     * @var string
     */
    protected $serviceType;

    /**
     * @var array
     */
    protected $config;

    /**
     * @var \Exception
     */
    protected $exception;

    /**
     * @return string
     */
    public function getServiceType()
    {
        return $this->serviceType;
    }

    /**
     * @param string $serviceType
     */
    public function setServiceType($serviceType)
    {
        $this->serviceType = $serviceType;
    }

    /**
     * @return array
     */
    public function getConfig()
    {
        return $this->config;
    }

    /**
     * @param array $config
     */
    public function setConfig($config)
    {
        $this->config = $config;
    }

    /**
     * @return mixed
     */
    public function getException()
    {
        return $this->exception;
    }

    /**
     * @param mixed $exception
     */
    public function setException($exception)
    {
        $this->exception = $exception;
    }

    /**
     * @param $serviceType
     * @param array      $config
     * @param \Exception $exception
     *
     * @return BadConfigException
     */
    public static function create($serviceType, array $config, \Exception $exception)
    {
        $generatedException = new self();

        $generatedException->setServiceType($serviceType);
        $generatedException->setConfig($config);
        $generatedException->setException($exception);

        $parentMessage = $exception->getMessage();

        if (array_key_exists($serviceType, $config)) {
            $serviceId = $config[$serviceType];
            $message = sprintf(
                'An error occurred while getting a service for configuration property "%s" using DI service with ID "%s" - %s',
                $serviceType,
                $serviceId,
                $parentMessage
            );
        } else {
            $message = sprintf(
                'An error occurred while getting a configuration property "%s". No such property exists in config.',
                $serviceType
            );
        }

        $generatedException->message = $message;

        return $generatedException;
    }
}
