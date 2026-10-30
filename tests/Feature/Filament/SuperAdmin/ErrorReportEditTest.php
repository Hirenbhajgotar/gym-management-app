<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\SuperAdmin;

use App\Filament\SuperAdmin\Resources\ErrorReportResource\Pages\ListErrorReports;
use App\Enums\DefaultRole;
use App\Enums\ErrorPriority;
use App\Enums\ErrorStatus;
use App\Filament\SuperAdmin\Resources\ErrorReportResource;
use App\Filament\SuperAdmin\Resources\ErrorReportResource\Pages\EditErrorReport;
use App\Models\ErrorReport;
use App\Models\Role;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ErrorReportEditTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdmin = User::factory()->create();
        $role = Role::findOrCreate(DefaultRole::SUPER_ADMIN->value, 'web');
        $this->superAdmin->assignRole($role);

        $this->actingAs($this->superAdmin);
        Filament::setCurrentPanel(Filament::getPanel('super-admin'));
    }

    public function test_status_only_edit_preserves_the_stored_markdown_and_stack_trace_byte_for_byte(): void
    {
        $markdown = "# Error: boom\n\n## Stack Trace\n\n```\nline one\nline two\n```\n";
        $trace = "#0 /app/foo.php(10): bar()\n#1 /app/baz.php(20): qux()\n";
        $description = "Error: timeout while saving\npushcommkey=749201\n\nuser note: retry later";

        $report = ErrorReport::query()->create([
            'error_type' => 'exception',
            'error_message' => 'boom',
            'markdown_report' => $markdown,
            'stack_trace' => $trace,
            'context' => ['route' => 'test.route', 'request_data' => ['safe' => 'value']],
            'status' => ErrorStatus::PENDING,
            'priority' => ErrorPriority::MEDIUM,
            'user_description' => $description,
            'occurred_at' => now(),
            'last_occurrence' => now(),
        ]);

        Livewire::test(EditErrorReport::class, ['record' => $report->getRouteKey()])
            ->fillForm(['status' => ErrorStatus::RESOLVED->value])
            ->call('save')
            ->assertHasNoFormErrors();

        $report->refresh();

        $this->assertSame($markdown, $report->markdown_report);
        $this->assertSame($trace, $report->stack_trace);
        $this->assertSame($description, $report->user_description);
        $this->assertSame('boom', $report->error_message);
        $this->assertSame(['route' => 'test.route', 'request_data' => ['safe' => 'value']], $report->context);
        $this->assertSame(ErrorStatus::RESOLVED, $report->status);
    }

    public function test_status_only_edit_preserves_a_null_user_description(): void
    {
        $report = ErrorReport::query()->create([
            'error_type' => 'exception',
            'error_message' => 'boom',
            'markdown_report' => "boom\n",
            'stack_trace' => null,
            'context' => [],
            'status' => ErrorStatus::PENDING,
            'priority' => ErrorPriority::MEDIUM,
            'user_description' => null,
            'occurred_at' => now(),
            'last_occurrence' => now(),
        ]);

        Livewire::test(EditErrorReport::class, ['record' => $report->getRouteKey()])
            ->fillForm(['status' => ErrorStatus::RESOLVED->value])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertNull($report->refresh()->user_description);
    }

    public function test_edit_page_redacts_historical_urls_headers_and_escaped_snapshots(): void
    {
        $commKey = '749201';
        $webhookToken = '0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef';

        $report = ErrorReport::query()->create([
            'error_type' => 'exception',
            'error_message' => 'boom',
            'markdown_report' => "\"snapshot\": \"{\\\"data\\\":{\\\"adms_comm_key\\\":\\\"{$commKey}\\\"}}\"\n"
                . "trace: verifyCommKey('{$commKey}')\n",
            'stack_trace' => "verifyCommKey('{$commKey}')\n"
                . "#0 /app/Http/Controllers/Api/BiometricWebhookController.php(42): BiometricWebhookController->handle(Object(Request), '{$webhookToken}')\n"
                . "#1 /app/Http/Middleware/AuthenticateAdmsDevice.php(30): AuthenticateAdmsDevice->handle('{$commKey}')\n",
            'origin_url' => "https://example.test/dashboard?pushcommkey={$commKey}",
            'context' => [
                'origin_url' => "https://example.test/dashboard?pushcommkey={$commKey}",
                'referer' => "https://example.test/api/v1/biometric/webhook/{$webhookToken}",
                'headers' => [
                    'user-agent' => ["client pushcommkey={$commKey}"],
                    'origin' => ["https://example.test/x?pushcommkey={$commKey}"],
                    'x-comm-key' => [$commKey],
                    'x-adms-comm-key' => [$commKey],
                    'x-signature' => ['signature-secret'],
                ],
                'request_data' => [
                    'pushcommkey' => $commKey,
                    'safe' => 'benign-sibling',
                ],
            ],
            'status' => ErrorStatus::PENDING,
            'priority' => ErrorPriority::MEDIUM,
            'occurred_at' => now(),
            'last_occurrence' => now(),
        ]);

        $component = Livewire::test(EditErrorReport::class, ['record' => $report->getRouteKey()])
            ->assertSuccessful();

        $component->assertDontSee($commKey);
        $component->assertDontSee($webhookToken);
        $component->assertDontSee('signature-secret');
        // The sanitized request_data container must not hide its benign siblings.
        $component->assertSee('benign-sibling');

        $html = $component->html();
        $this->assertStringNotContainsString($commKey, $html);
        $this->assertStringNotContainsString($webhookToken, $html);
        $this->assertStringNotContainsString('signature-secret', $html);

        $exported = $report->toRedactedMarkdown();

        $this->assertStringNotContainsString($commKey, $exported);
        $this->assertStringNotContainsString($webhookToken, $exported);
    }

    public function test_legacy_historical_markdown_projection_redacts_urls_and_keeps_benign_text(): void
    {
        $commKey = '749201';
        $webhookToken = '0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef';

        $report = ErrorReport::query()->create([
            'error_type' => 'exception',
            'error_message' => 'Error: timeout',
            'markdown_report' => null,
            'stack_trace' => null,
            'origin_url' => "https://example.test/x?pushcommkey={$commKey}",
            'context' => [
                'referer' => "https://example.test/api/v1/biometric/webhook/{$webhookToken}",
                'request_data' => ['safe' => 'benign-sibling'],
            ],
            'user_description' => "Error: timeout\npushcommkey={$commKey}",
            'status' => ErrorStatus::PENDING,
            'priority' => ErrorPriority::MEDIUM,
            'occurred_at' => now(),
            'last_occurrence' => now(),
        ]);

        $exported = $report->toRedactedMarkdown();

        $this->assertStringNotContainsString($commKey, $exported);
        $this->assertStringNotContainsString($webhookToken, $exported);
        $this->assertStringContainsString('Error: timeout', $exported);
        $this->assertStringContainsString('benign-sibling', $exported);
    }

    public function test_context_is_redacted_when_the_edit_page_renders(): void
    {
        $secret = '749201';

        $report = ErrorReport::query()->create([
            'error_type' => 'exception',
            'error_message' => 'boom',
            'markdown_report' => "boom\n",
            'stack_trace' => "trace\n",
            'context' => [
                'request_data' => [
                    'pushcommkey' => $secret,
                    'safe' => 'value',
                ],
            ],
            'status' => ErrorStatus::PENDING,
            'priority' => ErrorPriority::MEDIUM,
            'occurred_at' => now(),
            'last_occurrence' => now(),
        ]);

        Livewire::test(EditErrorReport::class, ['record' => $report->getRouteKey()])
            ->assertSuccessful()
            ->assertDontSee($secret)
            ->assertSee('***');
    }

    public function test_edit_page_form_state_and_snapshot_do_not_contain_raw_evidence(): void
    {
        $commKey = '749201';
        $report = ErrorReport::query()->create([
            'error_type' => 'exception',
            'error_message' => "raw error pushcommkey={$commKey}",
            'markdown_report' => "raw markdown pushcommkey={$commKey}\n",
            'stack_trace' => "raw trace verifyCommKey('{$commKey}')\n",
            'origin_url' => "https://example.test/x?pushcommkey={$commKey}",
            'context' => ['pushcommkey' => $commKey, 'safe' => 'benign-sibling'],
            'status' => ErrorStatus::PENDING,
            'priority' => ErrorPriority::MEDIUM,
            'user_description' => "raw description pushcommkey={$commKey}",
            'occurred_at' => now(),
            'last_occurrence' => now(),
        ]);

        $component = Livewire::test(EditErrorReport::class, ['record' => $report->getRouteKey()]);

        $data = $component->get('data');

        foreach (['context', 'markdown_report', 'stack_trace', 'origin_url', 'user_description', 'error_message', 'error_hash'] as $attribute) {
            $this->assertTrue(
                ! array_key_exists($attribute, $data) || $data[$attribute] === null,
                "Form state must not hydrate a raw [{$attribute}] value."
            );
        }

        $this->assertNotNull($data['status'] ?? null);

        $html = $component->html();
        $this->assertStringNotContainsString($commKey, $html, 'The edit-page snapshot leaked the raw error evidence.');
        $this->assertStringContainsString('benign-sibling', $html);
    }

    public function test_list_table_redacts_the_error_message_column(): void
    {
        $commKey = '749201';
        $report = ErrorReport::query()->create([
            'error_type' => 'exception',
            'error_message' => "failed for pushcommkey={$commKey}",
            'markdown_report' => "failed for pushcommkey={$commKey}\n",
            'context' => [],
            'status' => ErrorStatus::PENDING,
            'priority' => ErrorPriority::MEDIUM,
            'occurred_at' => now(),
            'last_occurrence' => now(),
        ]);

        $component = Livewire::test(ListErrorReports::class)->assertSuccessful();

        $component->assertCanSeeTableRecords([$report]);

        $this->assertStringNotContainsString($commKey, $component->html());
    }

    public function test_redacted_markdown_export_removes_historical_plaintext_key(): void
    {
        $report = ErrorReport::query()->create([
            'error_type' => 'exception',
            'error_message' => 'boom',
            'markdown_report' => "failed for pushcommkey=749201\nnext line\n",
            'stack_trace' => null,
            'context' => [],
            'status' => ErrorStatus::PENDING,
            'priority' => ErrorPriority::MEDIUM,
            'occurred_at' => now(),
            'last_occurrence' => now(),
        ]);

        $exported = $report->toRedactedMarkdown();

        $this->assertStringNotContainsString('749201', $exported);
        $this->assertStringContainsString("\nnext line", $exported);
    }

    public function test_stored_markdown_header_blocks_use_the_same_deny_by_default_projection_as_the_page(): void
    {
        $markdown = "# Error: boom\n\n"
            . "### Headers\n\n"
            . "```\n"
            . "accept: application/json\n"
            . "php-auth-pw: hunter2\n"
            . "x-custom-auth: custom-secret\n"
            . "```\n\n"
            . "### Input\n\n"
            . "```json\n{\"safe\":\"benign-sibling\"}\n```\n";

        $report = ErrorReport::query()->create([
            'error_type' => 'exception',
            'error_message' => 'boom',
            'markdown_report' => $markdown,
            'stack_trace' => null,
            'context' => [],
            'status' => ErrorStatus::PENDING,
            'priority' => ErrorPriority::MEDIUM,
            'occurred_at' => now(),
            'last_occurrence' => now(),
        ]);

        $component = Livewire::test(EditErrorReport::class, ['record' => $report->getRouteKey()])
            ->assertSuccessful();

        $this->assertStringNotContainsString('hunter2', $component->html());
        $this->assertStringNotContainsString('custom-secret', $component->html());

        $exported = $report->toRedactedMarkdown();
        $copyScript = ErrorReportResource::copyToClipboardScript($exported);

        foreach ([$exported, $copyScript] as $sink) {
            $this->assertStringNotContainsString('hunter2', $sink);
            $this->assertStringNotContainsString('custom-secret', $sink);
            $this->assertStringContainsString('application/json', $sink);
            $this->assertStringContainsString('benign-sibling', $sink);
        }
    }

    public function test_legacy_export_drops_livewire_snapshots_and_headers_like_the_page(): void
    {
        $report = ErrorReport::query()->create([
            'error_type' => 'exception',
            'error_message' => 'boom',
            'markdown_report' => null,
            'stack_trace' => null,
            'context' => [
                'headers' => [
                    'php-auth-pw' => ['hunter2'],
                    'x-custom-auth' => ['custom-secret'],
                    'accept' => ['application/json'],
                ],
                'request_data' => [
                    'headers' => [
                        'php-auth-pw' => ['hunter2'],
                        'x-custom-auth' => ['custom-secret'],
                        'accept' => ['application/json'],
                    ],
                    'components' => [[
                        'snapshot' => '{"data":{"safe":"benign-sibling"}}',
                    ]],
                    'updates' => [
                        'data.safe' => 'benign-sibling',
                    ],
                    'safe' => 'benign-sibling',
                ],
            ],
            'status' => ErrorStatus::PENDING,
            'priority' => ErrorPriority::MEDIUM,
            'occurred_at' => now(),
            'last_occurrence' => now(),
        ]);

        $component = Livewire::test(EditErrorReport::class, ['record' => $report->getRouteKey()])
            ->assertSuccessful();

        $pageHtml = $component->html();
        $exported = $report->toRedactedMarkdown();

        foreach ([$pageHtml, $exported] as $sink) {
            $this->assertStringNotContainsString('hunter2', $sink);
            $this->assertStringNotContainsString('custom-secret', $sink);
            $this->assertStringNotContainsString('components', $sink);
            $this->assertStringNotContainsString('updates', $sink);
            $this->assertStringContainsString('benign-sibling', $sink);
            $this->assertStringContainsString('application/json', $sink);
        }
    }
}
