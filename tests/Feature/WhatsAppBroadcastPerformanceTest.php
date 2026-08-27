<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\WhatsAppBroadcastRecipientStatusEnum;
use App\Enums\WhatsAppBroadcastStatusEnum;
use App\Jobs\SendBroadcastJob;
use App\Jobs\SendMessageChunkJob;
use App\Models\Gym;
use App\Models\GymMember;
use App\Models\WhatsAppBroadcast;
use App\Models\WhatsAppBroadcastRecipient;
use App\Models\WhatsAppTemplate;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Bus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class WhatsAppBroadcastPerformanceTest extends TestCase
{
    use DatabaseTransactions;

    private const CHUNK_SIZE = 50;

    private Gym $gym;

    private WhatsAppTemplate $template;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gym = Gym::factory()->create();

        $this->template = WhatsAppTemplate::factory()->create([
            'gym_id' => $this->gym->id,
            'name' => 'perf_test_template',
            'is_active' => true,
        ]);
    }

    #[Test]
    #[DataProvider('recipientSizeProvider')]
    public function it_orchestrates_time_scaling_acceptably(int $recipientCount, int $expectedChunks): void
    {
        $this->createBroadcastWithRecipients($recipientCount);

        Bus::fake();

        /** @var WhatsAppBroadcast $broadcast */
        $broadcast = WhatsAppBroadcast::query()->where('gym_id', $this->gym->id)->first();

        $start = microtime(true);

        $job = new SendBroadcastJob($broadcast->id, $broadcast->gym_id);
        $job->handle();

        $elapsed = (microtime(true) - $start) * 1000;

        if ($expectedChunks === 0) {
            Bus::assertBatchCount(0);
            $this->assertLessThan(
                5000,
                $elapsed,
                "Orchestration for {$recipientCount} recipients took {$elapsed}ms, expected < 5000ms",
            );

            return;
        }

        $capturedBatch = null;
        Bus::assertBatched(function ($batch) use (&$capturedBatch, $expectedChunks): bool {
            $capturedBatch = $batch;

            return $batch->jobs->count() === $expectedChunks;
        });

        Bus::assertBatchCount(1);

        $this->assertCount($expectedChunks, $capturedBatch?->jobs);
        $this->assertContainsOnlyInstancesOf(SendMessageChunkJob::class, $capturedBatch?->jobs);

        // Verify all recipients accounted for across chunks
        $totalRecipientsInChunks = collect($capturedBatch->jobs)
            ->sum(fn (SendMessageChunkJob $j) => count($j->recipientChunk));
        $this->assertSame($recipientCount, $totalRecipientsInChunks);

        $this->assertLessThan(
            5000,
            $elapsed,
            "Orchestration for {$recipientCount} recipients took {$elapsed}ms, expected < 5000ms",
        );
    }

    /**
     * @return array<string, array{int, int}>
     */
    public static function recipientSizeProvider(): array
    {
        return [
            'empty' => [0, 0],
            'single_recipient' => [1, 1],
            'exactly_one_chunk' => [self::CHUNK_SIZE, 1],
            'fifty_plus_one' => [51, 2],
            'three_chunks' => [150, 3],
            'ten_chunks' => [500, 10],
            'twenty_chunks' => [1000, 20],
        ];
    }

    #[Test]
    public function it_returns_instantly_when_already_processed(): void
    {
        $this->createBroadcastWithRecipients(100);
        Bus::fake();

        /** @var WhatsAppBroadcast $broadcast */
        $broadcast = WhatsAppBroadcast::query()->where('gym_id', $this->gym->id)->first();
        $broadcast->update(['status' => WhatsAppBroadcastStatusEnum::COMPLETED]);

        $start = microtime(true);
        (new SendBroadcastJob($broadcast->id, $broadcast->gym_id))->handle();
        $elapsed = (microtime(true) - $start) * 1000;

        Bus::assertBatchCount(0);
        $this->assertLessThan(1000, $elapsed, 'Already-processed early return took too long');
    }

    #[Test]
    public function it_measures_variable_resolution_overhead(): void
    {
        /** @var WhatsAppBroadcast $broadcast */
        $broadcast = WhatsAppBroadcast::factory()->create([
            'gym_id' => $this->gym->id,
            'whatsapp_template_id' => $this->template->id,
            'status' => WhatsAppBroadcastStatusEnum::SENDING,
            'sent_count' => 0,
            'failed_count' => 0,
        ]);

        $members = GymMember::factory()->count(100)
            ->sequence(fn (Sequence $sequence) => [
                'phone' => '+1' . str_pad((string) $sequence->index, 10, '0', STR_PAD_LEFT),
            ])
            ->create([
                'gym_id' => $this->gym->id,
                'name' => 'Benchmark Member',
            ]);

        $members->each(function (GymMember $member) use ($broadcast): void {
            WhatsAppBroadcastRecipient::factory()->create([
                'whatsapp_broadcast_id' => $broadcast->id,
                'member_id' => $member->id,
                'phone_number' => $member->phone,
                'member_name' => $member->name,
                'status' => WhatsAppBroadcastRecipientStatusEnum::PENDING,
            ]);
        });

        Bus::fake();

        $start = microtime(true);
        (new SendBroadcastJob($broadcast->id, $broadcast->gym_id))->handle();
        $elapsed = (microtime(true) - $start) * 1000;

        Bus::assertBatchCount(1);
        Bus::assertBatched(fn ($batch): bool => $batch->jobs->count() === 2);

        $this->assertLessThan(3000, $elapsed, 'Variable resolution overhead for 100 recipients exceeded 3s');
    }

    private function createBroadcastWithRecipients(int $count): void
    {
        /** @var WhatsAppBroadcast $broadcast */
        $broadcast = WhatsAppBroadcast::factory()->create([
            'gym_id' => $this->gym->id,
            'whatsapp_template_id' => $this->template->id,
            'status' => WhatsAppBroadcastStatusEnum::SENDING,
            'sent_count' => 0,
            'failed_count' => 0,
        ]);

        if ($count === 0) {
            return;
        }

        $members = GymMember::factory()->count($count)
            ->sequence(fn (Sequence $sequence) => [
                'phone' => '+1' . str_pad((string) $sequence->index, 10, '0', STR_PAD_LEFT),
            ])
            ->create([
                'gym_id' => $this->gym->id,
            ]);

        $members->each(function (GymMember $member) use ($broadcast): void {
            WhatsAppBroadcastRecipient::factory()->create([
                'whatsapp_broadcast_id' => $broadcast->id,
                'member_id' => $member->id,
                'phone_number' => $member->phone,
                'member_name' => $member->name,
                'status' => WhatsAppBroadcastRecipientStatusEnum::PENDING,
            ]);
        });
    }
}
