<?php

declare(strict_types=1);

namespace App\Models;

use App\Biometrics\Services\BiometricLogRedactor;
use App\Enums\ErrorCategory;
use App\Enums\ErrorPriority;
use App\Enums\ErrorStatus;
use App\Observers\ErrorReportObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $user_id
 * @property string|null $error_type
 * @property string|null $error_message
 * @property string|null $error_hash
 * @property string|null $stack_trace
 * @property array<array-key, mixed>|null $context
 * @property ErrorCategory|null $category
 * @property ErrorStatus|null $status
 * @property string|null $user_description
 * @property ErrorPriority|null $priority
 * @property int $duplicate_count
 * @property Carbon|null $last_occurrence
 * @property Carbon $occurred_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $user
 *
 * @method static Builder<static>|ErrorReport autoReported()
 * @method static Builder<static>|ErrorReport frequent()
 * @method static Builder<static>|ErrorReport highPriority()
 * @method static Builder<static>|ErrorReport investigating()
 * @method static Builder<static>|ErrorReport newModelQuery()
 * @method static Builder<static>|ErrorReport newQuery()
 * @method static Builder<static>|ErrorReport pending()
 * @method static Builder<static>|ErrorReport query()
 * @method static Builder<static>|ErrorReport recent()
 * @method static Builder<static>|ErrorReport resolved()
 * @method static Builder<static>|ErrorReport userReported()
 * @method static Builder<static>|ErrorReport whereCategory($value)
 * @method static Builder<static>|ErrorReport whereContext($value)
 * @method static Builder<static>|ErrorReport whereCreatedAt($value)
 * @method static Builder<static>|ErrorReport whereDuplicateCount($value)
 * @method static Builder<static>|ErrorReport whereErrorHash($value)
 * @method static Builder<static>|ErrorReport whereErrorMessage($value)
 * @method static Builder<static>|ErrorReport whereErrorType($value)
 * @method static Builder<static>|ErrorReport whereId($value)
 * @method static Builder<static>|ErrorReport whereLastOccurrence($value)
 * @method static Builder<static>|ErrorReport whereOccurredAt($value)
 * @method static Builder<static>|ErrorReport wherePriority($value)
 * @method static Builder<static>|ErrorReport whereStackTrace($value)
 * @method static Builder<static>|ErrorReport whereStatus($value)
 * @method static Builder<static>|ErrorReport whereUpdatedAt($value)
 * @method static Builder<static>|ErrorReport whereUserDescription($value)
 * @method static Builder<static>|ErrorReport whereUserId($value)
 *
 * @mixin \Eloquent
 * @mixin IdeHelperErrorReport
 */
#[ObservedBy([ErrorReportObserver::class])]
class ErrorReport extends Model
{
    use HasFactory;

    public const NAVIGATION_BADGE_CACHE_KEY = 'error_report_navigation_badge_count';

    public const ERROR_REPORT_ALL_COUNT_CACHE_KEY = 'error_reports_all_count';

    public const ERROR_REPORT_PENDING_COUNT_CACHE_KEY = 'error_reports_pending_count';

    public const ERROR_REPORT_INVESTIGATING_COUNT_CACHE_KEY = 'error_reports_investigating_count';

    public const ERROR_REPORT_RESOLVED_COUNT_CACHE_KEY = 'error_reports_resolved_count';

    protected $fillable = [
        'user_id',
        'error_type',
        'error_message',
        'stack_trace',
        'context',
        'status',
        'user_description',
        'priority',
        'category',
        'occurred_at',
        'error_hash',
        'duplicate_count',
        'last_occurrence',
        'markdown_report',
        'origin_url',
        'livewire_component',
        'php_version',
        'laravel_version',
    ];

    /**
     * Evidence fields are never serialized by default. Filament's edit page
     * overrides the form fill state, but this is defense in depth for every
     * other `toArray()`/JSON path.
     *
     * @var list<string>
     */
    protected $hidden = [
        'context',
        'markdown_report',
        'stack_trace',
        'origin_url',
        'user_description',
        'error_message',
        'error_hash',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'context' => 'array',
            'occurred_at' => 'datetime',
            'last_occurrence' => 'datetime',
            'status' => ErrorStatus::class,
            'priority' => ErrorPriority::class,
            'category' => ErrorCategory::class,
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    #[Scope]
    protected function pending($query)
    {
        return $query->where('status', ErrorStatus::PENDING);
    }

    #[Scope]
    protected function resolved($query)
    {
        return $query->where('status', ErrorStatus::RESOLVED);
    }

    #[Scope]
    protected function investigating($query)
    {
        return $query->where('status', ErrorStatus::INVESTIGATING);
    }

    #[Scope]
    protected function highPriority($query)
    {
        return $query->whereIn('priority', [ErrorPriority::HIGH, ErrorPriority::CRITICAL]);
    }

    #[Scope]
    protected function autoReported($query)
    {
        return $query->where('error_type', 'auto_reported');
    }

    #[Scope]
    protected function userReported($query)
    {
        return $query->where('error_type', 'user_reported');
    }

    #[Scope]
    protected function frequent($query)
    {
        return $query->where('duplicate_count', '>', 5);
    }

    #[Scope]
    protected function recent($query)
    {
        return $query->where('occurred_at', '>', now()->subDay());
    }

    /**
     * The raw markdown, without read-time redaction. Private on purpose: use
     * {@see self::toRedactedMarkdown()} for anything that leaves the model.
     */
    private function toMarkdown(): string
    {
        if ($this->markdown_report) {
            return $this->markdown_report;
        }

        return $this->buildLegacyMarkdown();
    }

    /**
     * Markdown safe to copy out of the application.
     *
     * Historical rows may predate write-time redaction, and a row edited
     * through the Filament form may still contain operator-entered text. Both
     * copy actions use this method so they cannot diverge again.
     */
    public function toRedactedMarkdown(): string
    {
        return BiometricLogRedactor::redactText(
            $this->redactMarkdownJsonBlocks(
                $this->redactMarkdownHeaderBlocks($this->toMarkdown())
            )
        );
    }

    /**
     * Single read-time projection for every context sink (page, markdown, copy).
     *
     * @param  array<array-key, mixed>|null  $context
     * @return array<array-key, mixed>
     */
    public function redactedContextForDisplay(?array $context = null): array
    {
        $context ??= $this->context ?? [];

        if (isset($context['headers']) && is_array($context['headers'])) {
            /** @var array<string, mixed> $headers */
            $headers = $context['headers'];
            $context['headers'] = BiometricLogRedactor::redactHeaders($headers);
        }

        $requestData = null;

        if (isset($context['request_data']) && is_array($context['request_data'])) {
            $requestData = $context['request_data'];

            if (isset($requestData['headers']) && is_array($requestData['headers'])) {
                /** @var array<string, mixed> $headers */
                $headers = $requestData['headers'];
                $requestData['headers'] = BiometricLogRedactor::redactHeaders($headers);
            }

            $requestData = BiometricLogRedactor::redactStrings(
                BiometricLogRedactor::sanitizeRequestData($requestData)
            );
            unset($context['request_data']);
        }

        $redacted = BiometricLogRedactor::redactStrings(BiometricLogRedactor::redact($context));

        if ($requestData !== null) {
            $redacted['request_data'] = $requestData;
        }

        return $redacted;
    }

    public function redactedOriginUrl(): ?string
    {
        $originUrl = $this->origin_url;

        if (blank($originUrl)) {
            $context = $this->context;
            $originUrl = is_array($context)
                ? ($context['origin_url'] ?? $context['url'] ?? $context['requested_url'] ?? null)
                : null;
        }

        if (blank($originUrl)) {
            $context = $this->context;
            $requestData = is_array($context) ? ($context['request_data'] ?? null) : null;

            if (is_array($requestData)) {
                $originUrl = $requestData['origin_url'] ?? $requestData['url'] ?? $requestData['requested_url'] ?? null;
            }
        }

        if (blank($originUrl) || ! is_string($originUrl)) {
            return null;
        }

        return BiometricLogRedactor::redactText(BiometricLogRedactor::redactUrl($originUrl));
    }

    /**
     * Header blocks in historical stored markdown predate write-time
     * redaction. Parse them as headers so the same deny-by-default allow-list
     * used by the page applies to the export.
     *
     * Recognises `## Headers`, `### Headers`, `## Request Headers`, and
     * `### Request Headers` case-insensitively, fenced (` ``` ` or `~~~`) or
     * unfenced. Heading-like lines inside an unrelated fenced code block are
     * treated as content, not as a section start. An unfenced block ends at a
     * blank line, end of input, or the next markdown heading; lines before the
     * first header pair do not start it, so an intro line followed by a blank
     * line cannot end it early. Folded/continuation lines (leading space or
     * tab) are denied because they cannot be attributed to an allow-listed
     * name. Line endings are preserved so a CRLF report round-trips
     * byte-for-byte apart from the redacted values.
     */
    private function redactMarkdownHeaderBlocks(string $markdown): string
    {
        $parts = preg_split('/(\r\n|\n|\r)/', $markdown, -1, PREG_SPLIT_DELIM_CAPTURE);

        if (! is_array($parts)) {
            return '[REDACTION FAILED]';
        }

        $sawHeaders = false;
        $inHeaders = false;
        $inFence = false;
        $headerFence = false;
        $blockStarted = false;

        foreach ($parts as $index => $line) {
            // Odd indexes are the captured line separators, not content.
            if ($index % 2 === 1) {
                continue;
            }

            $isFence = preg_match('/^[ \t]*(?:`{3,}|~{3,})/', $line) === 1;

            if (! $inHeaders) {
                // Fence state is tracked even outside a header block so a
                // `### Headers` heading inside arbitrary code is not mistaken
                // for a real section heading.
                if ($isFence) {
                    $inFence = ! $inFence;

                    continue;
                }

                if (! $inFence
                    && preg_match('/^#{1,6}[ \t]+(?:request[ \t]+)?headers[ \t]*$/i', trim($line)) === 1) {
                    $sawHeaders = true;
                    $inHeaders = true;
                    $blockStarted = false;
                    $headerFence = false;
                }

                continue;
            }

            if ($isFence) {
                $inFence = ! $inFence;
                $blockStarted = true;

                if ($headerFence) {
                    // The fence that opened the header block has closed.
                    $inHeaders = false;
                    $headerFence = false;
                } else {
                    $headerFence = true;
                }

                continue;
            }

            if (trim($line) === '') {
                // A fenced block is governed by its closing fence, and the
                // blank line between a heading and its fence (or its first
                // header line) belongs to the block. Anything after the block
                // has started ends an unfenced block.
                if (! $headerFence && $blockStarted) {
                    $inHeaders = false;
                }

                continue;
            }

            if (preg_match('/^#{1,6}[ \t]/', $line) === 1) {
                if ($headerFence) {
                    $parts[$index] = '[REDACTED]';
                } else {
                    $inHeaders = false;
                }

                continue;
            }

            if (preg_match('/^[ \t]/', $line) === 1) {
                // Folded continuation line (obs-fold or an indented value).
                // It is not attributable to an allow-listed header name.
                $parts[$index] = '[REDACTED]';

                continue;
            }

            if (preg_match('/^(?P<name>[^:\r\n]+):[ \t]?(?P<value>.*)$/', $line, $header) !== 1) {
                // Deny by default. An arbitrary intro line does not start the
                // block, so a following blank line does not close it before
                // the real header lines are seen.
                $parts[$index] = '[REDACTED]';

                continue;
            }

            $blockStarted = true;

            $name = $header['name'];
            $headers = BiometricLogRedactor::redactHeaders([$name => [$header['value']]]);
            $values = $headers[$name] ?? ['[REDACTED]'];
            $parts[$index] = $name . ': ' . implode(', ', is_array($values) ? $values : [$values]);
        }

        return $sawHeaders ? implode('', $parts) : $markdown;
    }

    /**
     * Redact JSON code fences structurally. Free-text redaction only knows
     * credential names, while a context export also contains biometric PII
     * (`pin`, `card`, `photo`, `image`, `template_data`).
     *
     * Delegates to the redactor's structure-preserving JSON path so `{}` stays
     * an object, numeric-key objects stay objects, `1.0` keeps its fraction,
     * and a 20-digit integer does not become a float.
     */
    private function redactMarkdownJsonBlocks(string $markdown): string
    {
        $redacted = preg_replace_callback(
            '/(```json\s*)(.*?)(\s*```)/s',
            static function (array $matches): string {
                $redactedJson = BiometricLogRedactor::redactJsonText($matches[2]);

                if (! is_string($redactedJson) || $redactedJson === '') {
                    return $matches[0];
                }

                return $matches[1] . $redactedJson . $matches[3];
            },
            $markdown
        );

        return $redacted ?? '[REDACTION FAILED]';
    }

    private function buildLegacyMarkdown(): string
    {
        $markdown = "### Error: {$this->error_message}\n\n";
        $markdown .= "**Type:** {$this->error_type}\n";
        $markdown .= '**Status:** ' . ($this->status?->value ?? 'N/A') . "\n";
        $markdown .= '**Priority:** ' . ($this->priority?->value ?? 'N/A') . "\n";
        $markdown .= '**Occurred:** ' . ($this->occurred_at?->toDateTimeString() ?? 'N/A') . "\n\n";

        $originUrl = $this->redactedOriginUrl();

        if ($originUrl !== null) {
            $markdown .= "**Origin URL:** {$originUrl}\n";
        }

        $component = $this->livewire_component ?? ($this->context['livewire_component'] ?? null);
        if (filled($component) && is_string($component)) {
            $markdown .= "**Livewire/Filament Component:** `{$component}`\n";
        }

        $markdown .= "\n";

        if ($this->user) {
            $markdown .= "**User:** {$this->user->name} ({$this->user->email})\n\n";
        }

        if ($this->user_description) {
            $markdown .= "#### User Description\n{$this->user_description}\n\n";
        }

        if ($this->stack_trace) {
            $markdown .= "#### Stack Trace\n```php\n{$this->stack_trace}\n```\n\n";
        }

        if ($this->context) {
            $context = json_encode(
                $this->redactedContextForDisplay(),
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
            );
            $markdown .= "#### Context\n```json\n{$context}\n```\n";
        }

        $databaseError = $this->context['database_error'] ?? null;

        if (is_array($databaseError)) {
            $markdown .= "\n## Database Error\n\n";
            $markdown .= '- **SQLSTATE**: ' . $this->singleLineRedactedValue($databaseError['sqlstate'] ?? null) . "\n";
            $markdown .= '- **Driver code**: ' . $this->singleLineRedactedValue($databaseError['driver_code'] ?? null) . "\n";
            $markdown .= '- **Driver message**: ' . $this->singleLineRedactedValue($databaseError['driver_message'] ?? null) . "\n";
        }

        return $markdown;
    }

    /**
     * Render a scalar diagnostic on one markdown bullet line, redacted and
     * with folded line breaks collapsed.
     */
    private function singleLineRedactedValue(mixed $value): string
    {
        if (! is_scalar($value)) {
            return 'n/a';
        }

        $redacted = trim(str_replace(
            ["\r\n", "\r", "\n"],
            ' ',
            BiometricLogRedactor::redactText((string) $value)
        ));

        return $redacted !== '' ? $redacted : 'n/a';
    }
}
