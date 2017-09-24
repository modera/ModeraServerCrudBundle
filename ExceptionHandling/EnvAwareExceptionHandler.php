<?php

namespace Modera\ServerCrudBundle\ExceptionHandling;

use Sli\ExpanderBundle\Ext\ContributorInterface;
use Symfony\Component\HttpKernel\Kernel;

/**
 * @deprecated Since 2.56.0, to be removed/or heavily refactored in 3.0 (because
 * the format response is not compatible with ExtDirect specification -
 * https://docs.sencha.com/extjs/6.0.2/guides/backend_connectors/direct/specification.html).
 *
 * @author    Sergei Lissovski <sergei.lissovski@modera.org>
 * @copyright 2013 Modera Foundation
 */
class EnvAwareExceptionHandler implements ExceptionHandlerInterface
{
    private $kernel;
    private $handlersProvider;

    /**
     * @param ContributorInterface $handlersProvider
     * @param Kernel               $kernel
     */
    public function __construct(ContributorInterface $handlersProvider, Kernel $kernel)
    {
        $this->kernel = $kernel;
        $this->handlersProvider = $handlersProvider;
    }

    /**
     * {@inheritdoc}
     */
    public function createResponse(\Exception $e, $operation)
    {
        foreach ($this->handlersProvider->getItems() as $handler) {
            /* @var ExceptionHandlerInterface $handler */

            $result = $handler->createResponse($e, $operation);
            if (false !== $result) {
                return $result;
            }
        }

        if ($this->kernel->getEnvironment() == 'prod') {
            return array(
                'success' => false,
                'exception' => true,
            );
        } else {
            return array(
                'success' => false,
                'exception' => true,
                'exception_class' => get_class($e),
                'stack_trace' => $e->getTrace(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'message' => $e->getMessage(),
            );
        }
    }
}
