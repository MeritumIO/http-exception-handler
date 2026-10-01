<?php

namespace Meritum\HttpExceptionHandler\Test;

use Georgeff\Kernel\DI\TagRegistryInterface;
use Georgeff\Kernel\Environment\Testing;
use Meritum\Http\Contract\ExceptionHandlerInterface;
use Meritum\Http\HttpKernel;
use Meritum\HttpExceptionHandler\ExceptionHandlerModule;
use Meritum\HttpExceptionHandler\HttpExceptionTranslationHandler;
use Meritum\StructuredLogging\ExceptionReporter;
use Meritum\StructuredLogging\StructuredLoggingModule;
use Meritum\StructuredLogging\TranslationHandler;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

final class ExceptionHandlerModuleTest extends TestCase
{
    private function makeKernel(): HttpKernel
    {
        $kernel = new HttpKernel(new Testing());
        $kernel->define(LoggerInterface::class, fn() => new NullLogger());
        $kernel->addModule(new ExceptionHandlerModule());
        $kernel->boot();

        return $kernel;
    }

    #[Test]
    public function test_exception_handler_resolves(): void
    {
        $container = $this->makeKernel()->getContainer();

        $this->assertInstanceOf(ExceptionHandlerInterface::class, $container->get(ExceptionHandlerInterface::class));
    }

    #[Test]
    public function test_exception_handler_is_shared(): void
    {
        $container = $this->makeKernel()->getContainer();

        $this->assertSame(
            $container->get(ExceptionHandlerInterface::class),
            $container->get(ExceptionHandlerInterface::class)
        );
    }

    #[Test]
    public function test_aggregates_structured_logging_module(): void
    {
        $kernel = $this->makeKernel();

        $this->assertContains(StructuredLoggingModule::class, $kernel->getModules());
        $this->assertInstanceOf(ExceptionReporter::class, $kernel->getContainer()->get(ExceptionReporter::class));
    }

    #[Test]
    public function test_registering_structured_logging_module_directly_as_well_is_allowed(): void
    {
        $kernel = new HttpKernel(new Testing());
        $kernel->define(LoggerInterface::class, fn() => new NullLogger());
        $kernel->addModule(new StructuredLoggingModule());
        $kernel->addModule(new ExceptionHandlerModule());
        $kernel->boot();

        $modules = array_count_values($kernel->getModules());

        $this->assertSame(1, $modules[StructuredLoggingModule::class]);
        $this->assertInstanceOf(ExceptionHandlerInterface::class, $kernel->getContainer()->get(ExceptionHandlerInterface::class));
    }

    #[Test]
    public function test_translation_handler_resolves(): void
    {
        $container = $this->makeKernel()->getContainer();

        $this->assertInstanceOf(HttpExceptionTranslationHandler::class, $container->get(HttpExceptionTranslationHandler::class));
    }

    #[Test]
    public function test_translation_handler_is_tagged(): void
    {
        $container = $this->makeKernel()->getContainer();
        $tags = $container->get(TagRegistryInterface::class);

        $handlers = $tags->getTagged('exception.translator.handlers');

        $this->assertNotEmpty($handlers);
        $this->assertContainsOnlyInstancesOf(TranslationHandler::class, $handlers);
    }

    #[Test]
    public function test_http_translation_handler_is_in_tagged_handlers(): void
    {
        $container = $this->makeKernel()->getContainer();
        $tags = $container->get(TagRegistryInterface::class);

        $handlers = $tags->getTagged('exception.translator.handlers');
        $types = array_map(fn($h) => $h::class, $handlers);

        $this->assertContains(HttpExceptionTranslationHandler::class, $types);
    }
}
