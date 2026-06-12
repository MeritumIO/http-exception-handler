<?php

namespace Meritum\HttpExceptionHandler;

use Georgeff\Kernel\KernelInterface;
use Psr\Container\ContainerInterface;
use Georgeff\Kernel\Module\ModuleInterface;
use Meritum\StructuredLogging\ExceptionReporter;
use Meritum\Http\Exception\ExceptionHandlerInterface;

final class ExceptionHandlerModule implements ModuleInterface
{
    public function register(KernelInterface $kernel): void
    {
        $kernel->define(HttpExceptionTranslationHandler::class, fn() => new HttpExceptionTranslationHandler())
               ->tag('exception.translator.handlers');

        $factory = function (ContainerInterface $c): ExceptionHandlerInterface {
            return new ExceptionHandler($c->get(ExceptionReporter::class));
        };

        $kernel->define(ExceptionHandlerInterface::class, $factory);
    }
}
