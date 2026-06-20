# meritum/http-exception-handler

HTTP exception handler that translates exceptions into structured JSON error responses using the `meritum/structured-logging` pipeline.

## Requirements

- PHP 8.4+
- `georgeff/kernel` ^1.6
- `meritum/http` ^1.0
- `meritum/structured-logging` ^1.0

## Installation

```bash
composer require meritum/http-exception-handler
```

## Usage

### Module registration

Register `ExceptionHandlerModule` alongside `StructuredLoggingModule`. `StructuredLoggingModule` requires a `LoggerInterface` to already be registered:

```php
use Meritum\HttpExceptionHandler\ExceptionHandlerModule;
use Meritum\StructuredLogging\StructuredLoggingModule;

$kernel->define(LoggerInterface::class, fn() => new MyLogger());
$kernel->addModule(new StructuredLoggingModule());
$kernel->addModule(new ExceptionHandlerModule());
$kernel->boot();
```

`ExceptionHandlerModule` registers:

- `ExceptionHandlerInterface` — the handler `meritum/http` resolves when an exception reaches the kernel boundary
- `HttpExceptionTranslationHandler` — tagged as `exception.translator.handlers`, translates `HttpExceptionInterface` instances into structured domain exceptions

### How it works

When an exception reaches the HTTP kernel boundary:

1. `ExceptionHandlerInterface::handle()` is called with the exception and the current request
2. The exception is passed through the `meritum/structured-logging` translate→report pipeline — it is translated to a domain exception and logged
3. An `ErrorEnvelope` is built from the domain exception and returned as a JSON response

HTTP exceptions (implementing `HttpExceptionInterface`) are translated by `HttpExceptionTranslationHandler` into an `HttpDomainException`, which carries the request context and sets the appropriate log severity. Any other exception falls through to the structured-logging catch-all translator and produces a generic 500 response.

### Response format

All error responses use a consistent JSON envelope:

```json
{
  "code":   "HTTP_404",
  "status": 404,
  "title":  "Not Found",
  "detail": "The requested resource could not be found."
}
```

- `code` — the domain exception error code; `HTTP_` followed by the HTTP status code for HTTP exceptions
- `status` — the HTTP status code as an integer; also set as the response status
- `title` — the HTTP exception title; `"Unexpected Error"` for non-HTTP exceptions
- `detail` — the exception message; may be `null`
- `errors` — an optional array of additional error detail objects; omitted when empty

### Custom HTTP exceptions

Extend `HttpException` or implement `HttpExceptionInterface` directly to define domain-specific HTTP exceptions:

```php
use Meritum\Http\Exception\HttpException;

final class ResourceNotFoundException extends HttpException
{
    protected string $title = 'Not Found';
    protected int $status = 404;
}
```

Throw the exception from within the request pipeline:

```php
throw new ResourceNotFoundException($request, 'User 42 does not exist.');
```

`HttpExceptionTranslationHandler` matches any `HttpExceptionInterface`, so custom exceptions are handled automatically with no additional registration.

### Custom translation handlers

To produce a different domain exception for a specific HTTP exception type, implement `TranslationHandler` and register it at a higher priority than the default handler (`0`):

```php
use Throwable;
use Meritum\StructuredLogging\TranslationHandler;
use Meritum\StructuredLogging\Exception\DomainException;

final class ValidationExceptionTranslationHandler implements TranslationHandler
{
    public function matches(Throwable $exception): bool
    {
        return $exception instanceof ValidationHttpException;
    }

    public function handle(Throwable $exception): DomainException
    {
        assert($exception instanceof ValidationHttpException);

        return new ValidationDomainException($exception);
    }

    public function priority(): int
    {
        return 1;
    }
}
```

Register and tag it in your module:

```php
$kernel->define(ValidationExceptionTranslationHandler::class, fn() => new ValidationExceptionTranslationHandler())
       ->tag('exception.translator.handlers');
```

Because this handler runs at priority `1`, it matches before `HttpExceptionTranslationHandler` (`0`) for `ValidationHttpException` instances. All other HTTP exceptions continue to be handled by the default.

### Using `ErrorEnvelope` directly

`ErrorEnvelope` is public API. Use it to build consistent error responses anywhere in your application:

```php
use Meritum\HttpExceptionHandler\ErrorEnvelope;

// From a caught domain exception
$envelope = ErrorEnvelope::fromDomainException($domainException);

// With explicit values
$envelope = new ErrorEnvelope('HTTP_403', 403, 'Forbidden', 'You do not have permission.');

return new JsonResponse($envelope, $envelope->status);
```

### Attaching additional errors

Call `withErrors()` to attach a list of error detail objects to the envelope. The shape of each entry is up to the caller — `withErrors()` carries any `string`-keyed map:

```php
$envelope = ErrorEnvelope::fromDomainException($domainException)
    ->withErrors([
        ['field' => 'email', 'message' => 'Must be a valid email address.'],
        ['field' => 'age',   'message' => 'Must be an integer greater than 0.'],
    ]);

return new JsonResponse($envelope, $envelope->status);
```

This produces:

```json
{
  "code":   "HTTP_422",
  "status": 422,
  "title":  "Unprocessable Entity",
  "detail": "The request body contains validation errors.",
  "errors": [
    { "field": "email", "message": "Must be a valid email address." },
    { "field": "age",   "message": "Must be an integer greater than 0." }
  ]
}
```

`errors` is omitted from the response entirely when `withErrors()` is not called. `withErrors()` replaces any previously set errors — pass the full list in a single call.

## License

MIT
