<?php

namespace App\Services;

use Throwable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Session;

class ExceptionStorageService
{
    public function store(Throwable $exception): string
    {
        $exceptionId = 'exception_' . uniqid();

        $data = [
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'class' => get_class($exception),
            'trace' => $exception->getTraceAsString(),
            'code' => $exception->getCode(),
            'previous' => $exception->getPrevious() ? [
                'message' => $exception->getPrevious()->getMessage(),
                'class' => get_class($exception->getPrevious()),
            ] : null,
        ];

        session()->put($exceptionId, $data);

        return $exceptionId;
    }

    public function retrieve(string $id): ?array
    {
        $data = Session::get($id);

        return $data;
    }

    public function clearExceptionStorageData(?string $id = null): void
    {
        if ($id && Session::has($id)) {
            Session::forget($id); // Clean up after retrieval
        }
    }
}
