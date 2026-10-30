<?php

declare(strict_types=1);

namespace App\Services;

use App\Biometrics\Services\BiometricLogRedactor;
use App\Services\ErrorReporting\ExceptionTraceFormatter;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Session;
use Throwable;

class ExceptionStorageService
{
    public function __construct(
        private readonly ExceptionTraceFormatter $traceFormatter,
    ) {}

    public function store(Throwable $exception): string
    {
        $exceptionId = 'exception_' . uniqid();

        $previous = $exception->getPrevious();

        $data = [
            'message' => $exception instanceof QueryException
                ? BiometricLogRedactor::redactText($exception->getSql())
                : BiometricLogRedactor::redactText($exception->getMessage()),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'class' => get_class($exception),
            'trace' => $this->traceFormatter->format($exception),
            'code' => $exception->getCode(),
            'previous' => $previous ? [
                'message' => $previous instanceof QueryException
                    ? BiometricLogRedactor::redactText($previous->getSql())
                    : BiometricLogRedactor::redactText($previous->getMessage()),
                'class' => get_class($previous),
            ] : null,
        ];

        session()->put($exceptionId, $data);

        return $exceptionId;
    }

    public function retrieve(string $id): ?array
    {
        return Session::get($id);
    }

    public function clearExceptionStorageData(?string $id = null): void
    {
        if ($id && Session::has($id)) {
            Session::forget($id); // Clean up after retrieval
        }
    }
}
