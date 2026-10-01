# Upgrading from 1.x to 2.0

2.0 migrates `meritum/http-exception-handler` onto `georgeff/kernel` ^2.0, `meritum/http` ^2.0, and `meritum/structured-logging` ^2.0. The handler's behavior (the translate → report pipeline, `ErrorEnvelope`, the JSON response format, `HttpExceptionTranslationHandler`) is unchanged. **Read the upgrade guides for [`georgeff/kernel`](https://github.com/MikeGeorgeff/kernel/blob/main/UPGRADE-2.0.md), [`meritum/http`](https://github.com/MeritumIO/http/blob/main/UPGRADE-2.0.md), and [`meritum/structured-logging`](https://github.com/MeritumIO/structured-logging/blob/main/UPGRADE-2.0.md) first**; this guide only covers what's specific to `meritum/http-exception-handler`.

See `CHANGELOG.md` for the full list of changes.

## Requirements

- [ ] **`georgeff/kernel` ^2.0, `meritum/http` ^2.0, `meritum/structured-logging` ^2.0.** All three are required together; none of them can stay on 1.x.

## 1. Stop registering `StructuredLoggingModule` yourself

`ExceptionHandlerModule` is now an aggregate module that loads `StructuredLoggingModule` for you. With `georgeff/kernel` 2.0, a module registered both directly and through an aggregate throws at boot:

```
ModuleException: Module [Meritum\StructuredLogging\StructuredLoggingModule] has already been added
```

- [ ] Remove your own `StructuredLoggingModule` registration from any kernel that also adds `ExceptionHandlerModule`:

  ```php
  // Before
  $kernel->addModule(new StructuredLoggingModule());
  $kernel->addModule(new ExceptionHandlerModule());

  // After
  $kernel->addModule(new ExceptionHandlerModule());
  ```

- [ ] You still need a `LoggerInterface` definition, for example `meritum/logger`'s `LoggerModule`. The aggregate doesn't choose a logger for you.
- [ ] `ExceptionReporter`, `CorrelationId`, and the rest of `meritum/structured-logging`'s services are still available to the rest of your application exactly as before, because the aggregate registers the same module.

## 2. Replacing the exception handler needs `override()`

`ExceptionHandlerModule` now registers the handler through `HttpKernel::addExceptionHandler()`, a thin wrapper over kernel 2.0's `define()`, which throws `DefinitionException` when an id is already defined.

- [ ] If you replaced the handler by defining `ExceptionHandlerInterface` yourself, or by calling `addExceptionHandler()` alongside this module, switch to `override()`:

  ```php
  // Before
  $kernel->define(ExceptionHandlerInterface::class, fn($c) => new MyExceptionHandler(...));

  // After
  $kernel->override(ExceptionHandlerInterface::class, fn($c) => new MyExceptionHandler(...))->share();
  ```

## Not required, but worth adopting

- **`StructuredLoggingOption::TranslatorTag`** — use it instead of the `exception.translator.handlers` string when tagging your own translation handlers. The string value is unchanged, so existing registrations keep working.

## Verifying the upgrade

- [ ] `composer test` — full suite passes
- [ ] `composer analyze` — PHPStan clean at `level: max`
- [ ] Grep your own codebase for `new StructuredLoggingModule(` — remove it from any kernel that also adds `ExceptionHandlerModule` (section 1).
- [ ] Grep for `define(ExceptionHandlerInterface::class` and `addExceptionHandler(` — any match needs section 2.
- [ ] Grep for `Meritum\Http\Exception\ExceptionHandlerInterface` — it's now `Meritum\Http\Contract\ExceptionHandlerInterface` (see `meritum/http`'s upgrade guide).
