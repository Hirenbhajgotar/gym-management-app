<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\TenantSocialConfig;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TenantSocialConfig
 */
class TenantSocialConfigResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'gym_id' => $this->gym_id,
            'platform' => $this->platform?->value,
            'page_id' => $this->page_id,
            'page_name' => $this->page_name,
            'form_id' => $this->form_id,
            'form_name' => $this->form_name,
            'phone_number_id' => $this->phone_number_id,
            'phone_number' => $this->phone_number,
            'app_id' => $this->app_id,
            'masked_token' => $this->masked_token,
            'is_active' => $this->is_active,
            'auto_reply_enabled' => (bool) $this->auto_reply_enabled,
            'sms_acknowledgment_enabled' => (bool) $this->sms_acknowledgment_enabled,
            'email_acknowledgment_enabled' => (bool) $this->email_acknowledgment_enabled,
            'token_expires_at' => $this->token_expires_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
