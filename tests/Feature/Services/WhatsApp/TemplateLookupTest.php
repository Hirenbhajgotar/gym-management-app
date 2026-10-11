<?php

declare(strict_types=1);

namespace Tests\Feature\Services\WhatsApp;

use App\Enums\WhatsAppTemplateStatusEnum;
use App\Models\Gym;
use App\Models\WhatsAppTemplate;
use App\Services\WhatsApp\TemplateLookup;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The system-default fallback is opt-in, and these are the tests that make that
 * claim checkable rather than decorative.
 *
 * Before the fallback became an explicit parameter, every caller got it: a gym
 * that had never configured, say, a renewal-reminder template silently resolved
 * the platform's own row and dispatched its body under the gym's sender
 * identity. The first test here is the one that fails on that code.
 */
class TemplateLookupTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_refuses_a_system_default_template_for_a_gym_that_has_none_of_its_own(): void
    {
        $gym = Gym::factory()->create();

        $this->makeSystemTemplate();

        $resolved = TemplateLookup::applyScope(WhatsAppTemplate::query(), $gym->id)->get();

        $this->assertSame([], $resolved->all(), 'A gym must not inherit the platform\'s template for a notification type it never configured.');
    }

    #[Test]
    public function it_returns_a_system_default_template_to_a_caller_that_opts_in(): void
    {
        $gym = Gym::factory()->create();

        $system = $this->makeSystemTemplate();

        $resolved = TemplateLookup::applyScope(WhatsAppTemplate::query(), $gym->id, allowSystemDefault: true)->get();

        $this->assertSame([$system->id], $this->ids($resolved));
    }

    #[Test]
    public function it_prefers_the_gyms_own_template_over_the_system_default(): void
    {
        $gym = Gym::factory()->create();

        $system = $this->makeSystemTemplate();
        $own = $this->makeGymTemplate($gym, name: $system->name);

        $resolved = TemplateLookup::applyScope(WhatsAppTemplate::query(), $gym->id, allowSystemDefault: true)->first();

        $this->assertSame($own->id, $resolved?->id, 'The gym-scoped row must win over the platform default.');
    }

    #[Test]
    public function it_never_returns_another_gyms_template(): void
    {
        $mine = Gym::factory()->create();
        $theirs = Gym::factory()->create();

        $this->makeGymTemplate($mine, name: 'membership_expiry');
        $foreign = $this->makeGymTemplate($theirs, name: 'membership_expiry');
        $this->makeSystemTemplate();

        $resolved = TemplateLookup::applyScope(WhatsAppTemplate::query(), $mine->id, allowSystemDefault: true)->get();

        $this->assertNotContains($foreign->id, $this->ids($resolved));
    }

    #[Test]
    public function it_restricts_to_system_defaults_for_a_null_gym_id(): void
    {
        $gym = Gym::factory()->create();

        $system = $this->makeSystemTemplate();
        $scoped = $this->makeGymTemplate($gym, name: $system->name);

        $resolved = TemplateLookup::applyScope(WhatsAppTemplate::query(), null)->get();

        $this->assertSame([$system->id], $this->ids($resolved), 'A null gym id means the system tenant, whatever the opt-in flag says.');
        $this->assertNotContains($scoped->id, $this->ids($resolved));
    }

    #[Test]
    public function it_ignores_a_template_that_is_not_approved(): void
    {
        $gym = Gym::factory()->create();

        $this->makeGymTemplate($gym, name: 'membership_expiry', status: WhatsAppTemplateStatusEnum::PENDING);

        $this->assertSame([], TemplateLookup::applyScope(WhatsAppTemplate::query(), $gym->id)->get()->all());
    }

    #[Test]
    public function it_ignores_a_deactivated_template_but_keeps_one_that_was_never_configured(): void
    {
        $gym = Gym::factory()->create();

        $this->makeGymTemplate($gym, name: 'switched_off', isActive: false);
        $neverConfigured = $this->makeGymTemplate($gym, name: 'never_configured', isActive: null);
        $explicitlyOn = $this->makeGymTemplate($gym, name: 'explicitly_on', isActive: true);

        $resolved = TemplateLookup::applyScope(WhatsAppTemplate::query(), $gym->id)->get();

        $this->assertEqualsCanonicalizing(
            [$neverConfigured->id, $explicitlyOn->id],
            $this->ids($resolved),
            'Only is_active = false is a deliberate deactivation; NULL means never configured.'
        );
    }

    #[Test]
    public function it_ignores_a_soft_deleted_template(): void
    {
        $gym = Gym::factory()->create();

        $retired = $this->makeGymTemplate($gym, name: 'membership_expiry');
        $retired->delete();

        $this->assertSame(
            [],
            TemplateLookup::applyScope(WhatsAppTemplate::query(), $gym->id)->get()->all(),
            'Global scopes are dropped by this scope, so the soft-delete filter has to be explicit.'
        );
    }

    private function makeSystemTemplate(): WhatsAppTemplate
    {
        return $this->makeTemplate(null, 'membership_expiry');
    }

    private function makeGymTemplate(Gym $gym, string $name, WhatsAppTemplateStatusEnum $status = WhatsAppTemplateStatusEnum::APPROVED, ?bool $isActive = true): WhatsAppTemplate
    {
        return $this->makeTemplate($gym, $name, $status, $isActive);
    }

    private function makeTemplate(?Gym $gym, string $name, WhatsAppTemplateStatusEnum $status = WhatsAppTemplateStatusEnum::APPROVED, ?bool $isActive = true): WhatsAppTemplate
    {
        $template = WhatsAppTemplate::query()->create([
            'gym_id' => $gym?->id,
            'name' => $name,
            'display_name' => ucfirst(str_replace('_', ' ', $name)),
            'body' => 'Body for ' . $name,
            'status' => $status,
            'is_active' => $isActive,
        ]);

        // Non-fillable: assigned directly, as the real services do.
        $template->twilio_template_sid = 'HX' . substr(md5($name . ($gym?->id ?? 'system')), 0, 24);
        $template->save();

        return $template;
    }

    /**
     * @param  Collection<int, Model>  $models
     * @return array<int, int>
     */
    private function ids(Collection $models): array
    {
        return array_values(array_map(static fn (Model $model): int => (int) $model->getKey(), $models->all()));
    }
}
