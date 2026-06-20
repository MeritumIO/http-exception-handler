<?php

namespace Meritum\HttpExceptionHandler;

use Meritum\Http\Exception\HttpExceptionInterface;
use Meritum\StructuredLogging\Exception\DomainException;

final class ErrorEnvelope implements \JsonSerializable
{
    /**
     * @var array<int, array<string, mixed>>
     */
    public private(set) array $errors = [];

    public function __construct(
        public readonly string $code,
        public readonly int $status,
        public readonly string $title,
        public readonly ?string $detail = null
    ) {}

    public static function fromDomainException(DomainException $exception): self
    {
        $code   = $exception->getErrorCode();
        $detail = $exception->getMessage();

        $previous = $exception->getPrevious();

        if ($previous instanceof HttpExceptionInterface) {
            $status = $previous->getStatusCode();
            $title  = $previous->getTitle();
        } else {
            $status = 500;
            $title  = 'Unexpected Error';
        }

        return new self($code, $status, $title, $detail);
    }

    /**
     * @param array<int, array<string, mixed>> $errors
     */
    public function withErrors(array $errors): self
    {
        $this->errors = $errors;

        return $this;
    }

    public function jsonSerialize(): mixed
    {
        $data = [
            'code'   => $this->code,
            'status' => $this->status,
            'title'  => $this->title,
            'detail' => $this->detail,
        ];

        if ([] !== $this->errors) {
            $data['errors'] = $this->errors;
        }

        return $data;
    }
}
