<?php

declare(strict_types=1);

namespace App\Services\ErrorReporting;

use App\Biometrics\Services\BiometricLogRedactor;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Throwable;

class ExceptionMarkdownFormatter
{
    private const CONTEXT_LINES = 6;

    public function __construct(
        private readonly ExceptionTraceFormatter $traceFormatter,
    ) {}

    /**
     * @param  array{requested_url: string, origin_url: string, livewire_component: string|null}  $origin
     */
    public function format(Throwable $exception, Request $request, array $origin): string
    {
        $lines = [
            '# ' . class_basename($exception) . ': ' . $this->singleLine($this->exceptionMessage($exception)),
            '',
            '- **Exception**: `' . get_class($exception) . '`',
            '- **Code**: ' . $exception->getCode(),
            '- **PHP**: ' . PHP_VERSION,
            '- **Laravel**: ' . app()->version(),
            '- **Environment**: ' . app()->environment(),
            '- **Occurred at**: ' . now()->toDateTimeString(),
            '',
            '## Origin',
            '',
            '- **File**: `' . $exception->getFile() . ':' . $exception->getLine() . '`',
            '',
            '```php',
            $this->codeSnippet($exception->getFile(), $exception->getLine()),
            '```',
            '',
            '## Stack Trace',
            '',
            $this->formatTrace($exception),
            '',
        ];

        if ($previous = $exception->getPrevious()) {
            $lines[] = '## Caused By';
            $lines[] = '';
            $lines[] = '**' . get_class($previous) . '**: ' . $this->singleLine($this->exceptionMessage($previous));
            $lines[] = '`' . $previous->getFile() . ':' . $previous->getLine() . '`';
            $lines[] = '';
        }

        $lines[] = '## Request';
        $lines[] = '';
        $lines[] = '- **Method**: ' . $request->method();
        $lines[] = '- **Requested endpoint**: ' . $this->singleLine($origin['requested_url']);
        $lines[] = '- **Origin URL**: ' . $this->singleLine($origin['origin_url']);

        if ($origin['livewire_component']) {
            $lines[] = '- **Livewire/Filament Component**: `' . $origin['livewire_component'] . '`';
        }

        $lines[] = '- **Route**: ' . ($request->route()?->getName() ?? 'n/a')
            . ' (' . ($request->route()?->getActionName() ?? 'n/a') . ')';
        $lines[] = '- **User**: ' . (Auth::check() ? '#' . Auth::id() . ' — ' . Auth::user()?->email : 'guest');
        $lines[] = '- **IP**: ' . $request->ip();
        $lines[] = '- **User Agent**: ' . $this->singleLine(BiometricLogRedactor::redactText($request->userAgent()));
        $lines[] = '';

        $lines[] = '### Headers';
        $lines[] = '';
        $lines[] = '```';
        foreach (BiometricLogRedactor::redactHeaders($request->headers->all()) as $key => $value) {
            /** @var list<string|null> $value */
            $lines[] = $key . ': ' . implode(', ', $value);
        }
        $lines[] = '```';
        $lines[] = '';

        $lines[] = '### Input';
        $lines[] = '';
        $lines[] = '```json';

        /** @var array<string, mixed> $input */
        $input = $request->all();

        $lines[] = json_encode(
            BiometricLogRedactor::sanitizeRequestData($input),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
        );
        $lines[] = '```';

        if ($queryException = $this->queryException($exception)) {
            $lines[] = '';
            $lines[] = '## Database Error';
            $lines[] = '';

            foreach ($this->databaseErrorDetails($queryException) as $detail) {
                $lines[] = $detail;
            }
        }

        return implode("\n", $lines);
    }

    /**
     * The QueryException in the exception chain, if any. A framework wrapper
     * may rethrow it, so checking only the outer type is not enough.
     */
    private function queryException(Throwable $exception): ?QueryException
    {
        $current = $exception;

        while ($current !== null) {
            if ($current instanceof QueryException) {
                return $current;
            }

            $current = $current->getPrevious();
        }

        return null;
    }

    /**
     * Binding-free database diagnostics for the markdown export.
     *
     * @return list<string>
     */
    private function databaseErrorDetails(QueryException $exception): array
    {
        $errorInfo = is_array($exception->errorInfo) ? $exception->errorInfo : [];

        $sqlState = (string) $exception->getCode();

        if (($sqlState === '' || $sqlState === '0') && isset($errorInfo[0]) && is_scalar($errorInfo[0])) {
            $sqlState = (string) $errorInfo[0];
        }

        $driverCode = isset($errorInfo[1]) && is_scalar($errorInfo[1]) ? (string) $errorInfo[1] : 'n/a';
        $driverMessage = isset($errorInfo[2]) && is_scalar($errorInfo[2]) ? (string) $errorInfo[2] : '';

        return [
            '- **SQLSTATE**: ' . ($sqlState !== '' ? $sqlState : 'n/a'),
            '- **Driver code**: ' . ($driverCode !== '' ? $driverCode : 'n/a'),
            '- **Driver message**: ' . $this->singleLine(BiometricLogRedactor::redactText($driverMessage)),
            '- **SQL**: ' . $this->singleLine(BiometricLogRedactor::redactText($exception->getSql())),
        ];
    }

    private function formatTrace(Throwable $exception): string
    {
        return "```\n" . $this->traceFormatter->format($exception) . "\n```";
    }

    private function singleLine(?string $value): string
    {
        return trim(str_replace(["\r", "\n"], ' ', (string) $value));
    }

    private function exceptionMessage(Throwable $exception): string
    {
        if ($exception instanceof QueryException) {
            // A raw literal in the SQL must not reach the title line; the
            // structured diagnostics below repeat the redacted SQL anyway.
            return BiometricLogRedactor::redactText($exception->getSql());
        }

        return BiometricLogRedactor::redactText($exception->getMessage());
    }

    private function codeSnippet(string $file, int $line): string
    {
        if (! is_file($file)) {
            return '// source not available';
        }

        $source = file($file);
        if ($source === false) {
            return '// source not available';
        }

        $start = max($line - self::CONTEXT_LINES, 1);
        $end = min($line + self::CONTEXT_LINES, count($source));

        $snippet = [];
        for ($i = $start; $i <= $end; $i++) {
            $marker = $i === $line ? '>' : ' ';
            $snippet[] = sprintf('%s %4d| %s', $marker, $i, rtrim($source[$i - 1] ?? ''));
        }

        return implode("\n", $snippet);
    }
}
