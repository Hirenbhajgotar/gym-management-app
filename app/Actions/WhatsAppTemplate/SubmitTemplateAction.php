<?php

declare(strict_types=1);

namespace App\Actions\WhatsAppTemplate;

use App\DTOs\WhatsAppTemplates\TemplateSubmissionResult;
use App\Enums\WhatsAppTemplateStatusEnum;
use App\Models\WhatsAppTemplate;
use App\Providers\WhatsAppProviderFactory;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;

/**
 * Submit a WhatsApp template for Meta approval.
 *
 * Resolves the appropriate provider (Twilio or Meta WABA) via
 * WhatsAppProviderFactory, submits the template, and returns
 * the submission result.
 */
class SubmitTemplateAction
{
    public function __invoke(WhatsAppTemplate $template): TemplateSubmissionResult
    {
        if ($template->status !== WhatsAppTemplateStatusEnum::DRAFT) {
            return TemplateSubmissionResult::fromError(
                'Only draft templates can be submitted. Current status: ' . ($template->status->value ?? 'unknown')
            );
        }

        if (blank($template->gym_id)) {
            return TemplateSubmissionResult::fromError('Template not associated with any gym');
        }

        try {
            $provider = WhatsAppProviderFactory::create($template->gym_id);

            return $provider->submitTemplate($template->fresh());
        } catch (InvalidArgumentException|RuntimeException $e) {
            Log::error('Template submission failed with exception', [
                'template_id' => $template->id,
                'error' => $e->getMessage(),
            ]);

            return TemplateSubmissionResult::fromError($e->getMessage());
        }
    }
}
