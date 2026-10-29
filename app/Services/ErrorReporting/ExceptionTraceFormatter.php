<?php

declare(strict_types=1);

namespace App\Services\ErrorReporting;

use Throwable;

/**
 * Renders a stack trace without argument values.
 *
 * `Throwable::getTraceAsString()` includes call arguments (for example
 * `f('123456')`), which can persist a communication key or bearer token in the
 * error-report tables. Frames are sufficient for diagnosis; arguments are not.
 */
class ExceptionTraceFormatter
{
    public function format(Throwable $exception): string
    {
        $frames = [];

        foreach ($exception->getTrace() as $index => $frame) {
            $file = $frame['file'] ?? '[internal function]';
            $line = $frame['line'] ?? 0;
            $class = $frame['class'] ?? '';
            $type = $frame['type'] ?? '';
            $function = $frame['function'];

            $frames[] = "#{$index} {$file}:{$line} — {$class}{$type}{$function}()";
        }

        return implode("\n", $frames);
    }
}
