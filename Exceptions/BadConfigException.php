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
    protected ?string $serviceType = null;

    /**
     * @var array<string, mixed>
     */
    protected array $config = [];

    public function getServiceType(): ?string
    {
        return $this->serviceType;
    }

    /**
     * @return array<string, mixed>
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * @param array<string, mixed> $config
     */
    public static function create(string $serviceType, array $config, \Exception $exception): self
    {
        $parentMessage = $exception->getMessage();

        if (\array_key_exists($serviceType, $config)) {
            /** @var string $serviceId */
            $serviceId = $config[$serviceType];
            $message = \sprintf(
                'An error occurred while getting a service for configuration property "%s" using DI service with ID "%s" - %s',
                $serviceType,
                $serviceId,
                $parentMessage
            );
        } else {
            $message = \sprintf(
                'An error occurred while getting a configuration property "%s". No such property exists in config.',
                $serviceType
            );
        }

        $generatedException = new self($message, $exception->getCode(), $exception);

        $generatedException->serviceType = $serviceType;
        $generatedException->config = $config;

        return $generatedException;
    }
}
