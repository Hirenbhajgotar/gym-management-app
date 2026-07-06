<?php

declare(strict_types=1);

namespace App\Services\ErrorReporting;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Throwable;

class ExceptionMarkdownFormatter
{
    private const CONTEXT_LINES = 6;

    private const SENSITIVE_INPUT_KEYS = [
        'password',
        'password_confirmation',
        'token',
        'api_key',
        'secret',
        'card_number',
        'cvv',
    ];

    private const BLOCKED_HEADERS = ['cookie', 'authorization', 'x-xsrf-token'];

    /**
     * @param  array{requested_url: string, origin_url: string, livewire_component: string|null}  $origin
     */
    public function format(Throwable $exception, Request $request, array $origin): string
    {
        $lines = [
            '# ' . class_basename($exception) . ': ' . $exception->getMessage(),
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
            $lines[] = '**' . get_class($previous) . '**: ' . $previous->getMessage();
            $lines[] = '`' . $previous->getFile() . ':' . $previous->getLine() . '`';
            $lines[] = '';
        }

        $lines[] = '## Request';
        $lines[] = '';
        $lines[] = '- **Method**: ' . $request->method();
        $lines[] = '- **Requested endpoint**: ' . $origin['requested_url'];
        $lines[] = '- **Origin URL (asli page)**: ' . $origin['origin_url'];

        if ($origin['livewire_component']) {
            $lines[] = '- **Livewire/Filament Component**: `' . $origin['livewire_component'] . '`';
        }

        $lines[] = '- **Route**: ' . ($request->route()?->getName() ?? 'n/a')
            . ' (' . ($request->route()?->getActionName() ?? 'n/a') . ')';
        $lines[] = '- **User**: ' . (Auth::check() ? '#' . Auth::id() . ' — ' . Auth::user()?->email : 'guest');
        $lines[] = '- **IP**: ' . $request->ip();
        $lines[] = '- **User Agent**: ' . $request->userAgent();
        $lines[] = '';

        $lines[] = '### Headers';
        $lines[] = '';
        $lines[] = '```';
        foreach ($this->sanitizeHeaders($request->headers->all()) as $key => $value) {
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
            $this->sanitizeInput($input),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
        );
        $lines[] = '```';

        return implode("\n", $lines);
    }

    private function formatTrace(Throwable $exception): string
    {
        $frames = [];

        foreach ($exception->getTrace() as $index => $frame) {
            $file = $frame['file'] ?? '[internal function]';
            $line = $frame['line'] ?? 0;
            $function = ($frame['class'] ?? '') . ($frame['type'] ?? '') . $frame['function'];

            $frames[] = "#{$index} {$file}:{$line} — {$function}()";
        }

        return "```\n" . implode("\n", $frames) . "\n```";
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

    /**
     * @param  array<string, list<string|null>>  $headers
     * @return array<string, list<string|null>>
     */
    private function sanitizeHeaders(array $headers): array
    {
        foreach (self::BLOCKED_HEADERS as $key) {
            unset($headers[$key]);
        }

        return $headers;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function sanitizeInput(array $data): array
    {
        array_walk_recursive($data, function (&$value, $key) {
            if (! is_string($key) && ! is_int($key)) {
                return;
            }

            if (in_array(strtolower((string) $key), self::SENSITIVE_INPUT_KEYS, true)) {
                $value = '***REDACTED***';
            }
        });

        return $data;
    }
}
