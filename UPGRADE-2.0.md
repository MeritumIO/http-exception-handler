# Upgrading from 1.x to 2.0

2.0 migrates `meritum/http-exception-handler` onto `georgeff/kernel` ^2.1, `meritum/http` ^2.0, and `meritum/structured-logging` ^2.0. The handler's behavior (the translate → report pipeline, `ErrorEnvelope`, the JSON response format, `HttpExceptionTranslationHandler`) is unchanged. **Read the upgrade guides for [`georgeff/kernel`](https://github.com/MikeGeorgeff/kernel/blob/main/UPGRADE-2.0.md), [`meritum/http`](https://github.com/MeritumIO/http/blob/main/UPGRADE-2.0.md), and [`meritum/structured-logging`](https://github.com/MeritumIO/structured-logging/blob/main/UPGRADE-2.0.md) first**; this guide only covers what's specific to `meritum/http-exception-handler`.

See `CHANGELOG.md` for the full list of changes.

## Requirements

- [ ] **`georgeff/kernel` ^2.1, `meritum/http` ^2.0, `meritum/structured-logging` ^2.0.** All three are required together; none of them can stay on 1.x.

## 1. Replacing the exception handler needs `override()`

`ExceptionHandlerModule` now registers the handler through `HttpKernel::addExceptionHandler()`, a thin wrapper over kernel 2.0's `define()`, which throws `DefinitionException` when an id is already defined.

- [ ] If you replaced the handler by defining `ExceptionHandlerInterface` yourself, switch to `override()`:

  ```php
  // Before
  $kernel->define(ExceptionHandlerInterface::class, fn($c) => new MyExceptionHandler(...));

  // After
  $kernel->override(ExceptionHandlerInterface::class, fn($c) => new MyExceptionHandler(...))->share();
  ```

## Not required, but worth adopting

- **`StructuredLoggingModule` registration is now optional.** `ExceptionHandlerModule` is an aggregate module that loads `StructuredLoggingModule` for you. If your kernel already registers `StructuredLoggingModule` directly, you can keep that line: it's registered once, and your instance is the one used. Keep it if your application uses `ExceptionReporter` or other structured-logging services itself, so that dependency stays explicit; otherwise you can remove it. You still need a `LoggerInterface` definition, for example `meritum/logger`'s `LoggerModule`.
- **`StructuredLoggingOption::TranslatorTag`** — use it instead of the `exception.translator.handlers` string when tagging your own translation handlers. The string value is unchanged, so existing registrations keep working.

## Verifying the upgrade

- [ ] `composer test` — full suite passes
- [ ] `composer analyze` — PHPStan clean at `level: max`
- [ ] Grep for `define(ExceptionHandlerInterface::class` — any match needs section 1.
- [ ] Grep for `Meritum\Http\Exception\ExceptionHandlerInterface` — it's now `Meritum\Http\Contract\ExceptionHandlerInterface` (see `meritum/http`'s upgrade guide).
