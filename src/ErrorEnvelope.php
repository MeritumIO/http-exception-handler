<?php

namespace Meritum\HttpExceptionHandler;

use Meritum\Http\Exception\HttpExceptionInterface;
use Meritum\StructuredLogging\Exception\DomainException;

final class ErrorEnvelope implements \JsonSerializable
{
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

    public function jsonSerialize(): mixed
    {
        return [
            'code'   => $this->code,
            'status' => $this->status,
            'title'  => $this->title,
            'detail' => $this->detail,
        ];
    }
}
