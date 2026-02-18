<?php

namespace App\Jobs;

use App\Enums\WhatsAppTemplateStatusEnum;
use App\Models\Gym;
use App\Models\WhatsappTemplate;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class PushWhatsAppTemplateToGymJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 30;

    public int $timeout = 120;

    public function __construct(
        public int $templateId,
        public ?int $pusherUserId = null
    ) {}

    public function handle(): void
    {
        $template = WhatsappTemplate::find($this->templateId);

        if (! $template) {
            Log::warning('PushWhatsAppTemplateToGymJob: Template not found', [
                'template_id' => $this->templateId,
            ]);

            return;
        }

        $gyms = $this->getActiveGyms();

        if ($gyms->isEmpty()) {
            Log::info('PushWhatsAppTemplateToGymJob: No active gyms found');

            return;
        }

        $pushedCount = 0;

        foreach ($gyms as $gym) {
            $this->pushTemplateToGym($template, $gym);
            $pushedCount++;
        }

        Log::info('PushWhatsAppTemplateToGymJob: Template pushed to gyms', [
            'template_id' => $this->templateId,
            'gym_count' => $pushedCount,
            'template_name' => $template->name,
            'pushed_by' => $this->pusherUserId,
        ]);
    }

    protected function getActiveGyms(): \Illuminate\Database\Eloquent\Collection
    {
        return Gym::whereHas('users', function ($query) {
            $query->where('users.is_active', true);
        })->get();
    }

    protected function pushTemplateToGym(WhatsappTemplate $template, Gym $gym): void
    {
        $exists = WhatsappTemplate::where('gym_id', $gym->id)
            ->where('name', $template->name)
            ->exists();

        if ($exists) {
            Log::debug('PushWhatsAppTemplateToGymJob: Template already exists, skipping', [
                'template_id' => $this->templateId,
                'gym_id' => $gym->id,
                'template_name' => $template->name,
            ]);

            return;
        }

        WhatsappTemplate::create([
            'gym_id' => $gym->id,
            'name' => $template->name,
            'display_name' => $template->display_name,
            'body' => $template->body,
            'category' => $template->category,
            'variables' => $template->variables,
            'variable_count' => $template->variable_count,
            'status' => WhatsAppTemplateStatusEnum::DRAFT,
            'is_default' => false,
            'is_active' => true,
        ]);

        Log::info('PushWhatsAppTemplateToGymJob: Template pushed to gym', [
            'template_id' => $this->templateId,
            'gym_id' => $gym->id,
            'template_name' => $template->name,
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('PushWhatsAppTemplateToGymJob permanently failed', [
            'template_id' => $this->templateId,
            'error' => $exception->getMessage(),
        ]);
    }
}
