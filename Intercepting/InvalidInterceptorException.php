<?php

namespace Modera\ServerCrudBundle\Intercepting;

/**
 * @author    Sergei Lissovski <sergei.lissovski@modera.org>
 * @copyright 2014 Modera Foundation
 */
class InvalidInterceptorException extends \RuntimeException
{
    private ?object $interceptor = null;

    public static function create(object $interceptor): self
    {
        $message = \sprintf(
            "It is expected that all interceptors would implements %s interface but %s doesn't!",
            '\Modera\ServerCrudBundle\Intercepting\ControllerActionsInterceptorInterface',
            \get_class($interceptor)
        );

        $self = new self($message);
        $self->setInterceptor($interceptor);

        return $self;
    }

    public function setInterceptor(object $interceptor): void
    {
        $this->interceptor = $interceptor;
    }

    public function getInterceptor(): ?object
    {
        return $this->interceptor;
    }
}
