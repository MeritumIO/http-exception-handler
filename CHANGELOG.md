# Changelog

All notable changes to `meritum/http-exception-handler` are documented here.

---

## [2.0.0] — 2026-10-01

2.0 migrates to `georgeff/kernel` ^2.1, `meritum/http` ^2.0, and `meritum/structured-logging` ^2.0.

### Changed
- **Breaking:** migrated to `georgeff/kernel` ^2.1, `meritum/http` ^2.0, and `meritum/structured-logging` ^2.0. `ExceptionHandler` implements `Meritum\Http\Contract\ExceptionHandlerInterface`, which moved from `Meritum\Http\Exception\` in `meritum/http` 2.0
- `ExceptionHandlerModule` is now a `Georgeff\Kernel\Contract\AggregateModuleInterface` that loads `StructuredLoggingModule` itself, so adding `ExceptionHandlerModule` is enough. Registering `StructuredLoggingModule` yourself as well still works: kernel 2.1 registers it once, and a directly added instance takes precedence over the aggregate's
- `ExceptionHandlerModule` registers the handler through `HttpKernel::addExceptionHandler()` instead of a raw `define()`, so it must be added to an `HttpKernel`. Replacing the handler requires `override(ExceptionHandlerInterface::class, ...)`: kernel 2.0's `define()` (which `addExceptionHandler()` wraps) throws `DefinitionException` for an id that's already defined
- `HttpExceptionTranslationHandler` is tagged via `StructuredLoggingOption::TranslatorTag` instead of the hardcoded `exception.translator.handlers` string. The tag value is unchanged
