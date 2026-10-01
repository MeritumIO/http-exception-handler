<?php

namespace Meritum\HttpExceptionHandler;

use Georgeff\Kernel\KernelInterface;
use Psr\Container\ContainerInterface;
use Meritum\Http\HttpKernelInterface;
use Meritum\StructuredLogging\ExceptionReporter;
use Georgeff\Kernel\Contract\EnvironmentInterface;
use Meritum\Http\Contract\ExceptionHandlerInterface;
use Georgeff\Kernel\Contract\AggregateModuleInterface;
use Meritum\StructuredLogging\StructuredLoggingOption;
use Meritum\StructuredLogging\StructuredLoggingModule;

final class ExceptionHandlerModule implements AggregateModuleInterface
{
    public function register(KernelInterface $kernel): void
    {
        assert($kernel instanceof HttpKernelInterface);

        $kernel->define(HttpExceptionTranslationHandler::class, fn() => new HttpExceptionTranslationHandler())
               ->tag(StructuredLoggingOption::TranslatorTag->value);

        $kernel->addExceptionHandler(function (ContainerInterface $c): ExceptionHandlerInterface {
            return new ExceptionHandler($c->get(ExceptionReporter::class));
        });
    }

    public function modules(EnvironmentInterface $env): array
    {
        return [
            new StructuredLoggingModule(),
        ];
    }
}
