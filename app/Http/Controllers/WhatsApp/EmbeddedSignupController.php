<?php

declare(strict_types=1);

namespace App\Http\Controllers\WhatsApp;

use App\Http\Controllers\Controller;
use App\Http\Requests\ManualConnectRequest;
use App\Jobs\ImportWhatsAppTemplatesJob;
use App\Models\Gym;
use App\Models\WabaSetupSession;
use App\Providers\MetaWhatsAppProvider;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class EmbeddedSignupController extends Controller
{
    public function redirect(Request $request, string $tenant): RedirectResponse
    {
        $gym = Gym::query()->where('slug', $tenant)->first();

        if (! $gym) {
            $route = route('filament.admin.pages.whats-app-setup', ['tenant' => $tenant]);

            return redirect($route . '?setup_step=2')->with('error', 'Tenant not found');
        }

        // Authorization: the authenticated user must belong to this gym.
        abort_unless(Auth::user()?->gyms()->where('gyms.id', $gym->id)->exists() ?? false, 403, 'You do not have access to this gym.');

        // Create provider with gym ID - can't be auto-wired due to required $gymId parameter
        $metaWhatsAppProvider = new MetaWhatsAppProvider($gym->id);

        if (! $metaWhatsAppProvider->isEmbeddedSignupEnabled()) {
            $route = route('filament.admin.pages.whats-app-setup', ['tenant' => $tenant]);

            return redirect($route . '?setup_step=2')->with('error', 'Embedded Signup is not enabled');
        }

        session(['embedded_signup_tenant_id' => $gym->id]);
        session(['embedded_signup_tenant_slug' => $gym->slug]);

        $loginUrl = $metaWhatsAppProvider->startEmbeddedSignup($gym->id);

        return redirect()->away($loginUrl);
    }

    public function callback(Request $request): RedirectResponse
    {
        $code = $request->input('code');
        $state = $request->input('state');
        $error = $request->input('error');
        $errorDescription = $request->input('error_description');

        $tenantSlug = session('embedded_signup_tenant_slug');
        $tenantId = session('embedded_signup_tenant_id');

        session()->forget(['embedded_signup_tenant_id', 'embedded_signup_tenant_slug']);

        if (! is_int($tenantId)) {
            return redirect(url('/admin'))->with('error', 'Invalid session data');
        }

        $redirectUrl = $tenantSlug
            ? route('filament.admin.pages.whats-app-setup', ['tenant' => $tenantSlug])
            : url('/admin');

        if ($error) {
            return redirect($redirectUrl . '?setup_step=2')
                ->with('error', $errorDescription ?? $error);
        }

        if (! $code || ! $state) {
            return redirect($redirectUrl . '?setup_step=2')
                ->with('error', 'Invalid callback parameters');
        }

        // Bind the OAuth state to the tenant stored in the session to prevent a
        // forged/foreign state from completing signup for the wrong gym. The
        // state is a WabaSetupSession id that already records the originating gym.
        // Session is already marked as failed or completed in MetaEmbeddedSignupService::handleEmbeddedSignupCallback()
        $setupSession = WabaSetupSession::query()->where('session_id', $state)->first();

        if (! $setupSession || $tenantId !== $setupSession->gym_id) {
            Log::warning('Embedded Signup callback: state/tenant mismatch', [
                'session_tenant_id' => $setupSession?->gym_id,
                'session_tenant_id_from_request' => $tenantId,
            ]);

            return redirect($redirectUrl . '?setup_step=2')
                ->with('error', 'Invalid OAuth state');
        }

        // Reject a state that has already been consumed or has expired. This
        // closes the OAuth state-replay window: each WabaSetupSession may only
        // complete the signup flow once.
        if (! $setupSession->isConsumable()) {
            Log::warning('Embedded Signup callback: state not consumable (already used or expired)', [
                'session_id' => $state,
                'status' => $setupSession->status->value,
            ]);

            return redirect($redirectUrl . '?setup_step=2')
                ->with('error', 'Invalid OAuth state');
        }

        // Create provider for handling callback - use MetaEmbeddedSignupService directly for callback processing
        $metaWhatsAppProvider = new MetaWhatsAppProvider($tenantId);

        $result = $metaWhatsAppProvider->handleEmbeddedSignupCallback((string) $code, (string) $state);

        Log::info('Embedded Signup callback result', [
            'waba_id' => $result['waba_id'] ?? null,
            'success' => $result['success'],
            'has_phone_number_id' => filled($result['phone_number_id'] ?? null),
        ]);

        if (! $result['success']) {
            return redirect($redirectUrl . '?setup_step=2')
                ->with('error', $result['error']);
        }

        if ($tenantId && filled($result['waba_id'])) {
            // Use MetaWabaService to complete embedded signup (save newly created WABA)
            $metaWhatsAppProvider->completeEmbeddedSignup(
                $tenantId,
                $result['waba_id'],
                $result['access_token'],
                $result['phone_number_id'] ?? null
            );
        }

        return redirect($redirectUrl . '?setup_step=2')
            ->with('success', 'WABA connected successfully')
            ->with('waba_id', $result['waba_id'] ?? null);
    }

    public function manualConnect(ManualConnectRequest $request, string $tenant): RedirectResponse
    {
        $validated = $request->validated();
        $gym = $request->getGym();

        if ($gym === null) {
            return to_route('filament.admin.pages.whats-app-setup', ['tenant' => $tenant, 'setup_step' => 2])
                ->with('error', 'Gym not found');
        }

        // Create provider with gym ID
        $metaWhatsAppProvider = new MetaWhatsAppProvider($gym->id);

        $result = $metaWhatsAppProvider->connectManualWaba(
            $gym->id,
            $validated['waba_id'],
            $validated['manual_phone_number'],
            $validated['meta_access_token'] ?? null,
            $validated['waba_phone_number_id']
        );

        if (! $result['success']) {
            return to_route('filament.admin.pages.whats-app-setup', ['tenant' => $tenant, 'setup_step' => 2])
                ->with('error', $result['error']);
        }

        // Import existing Meta templates as DRAFT records. The job runs
        // asynchronously (and is idempotent via updateOrCreate + ShouldBeUnique),
        // so we must NOT land the user on the templates step yet — the templates
        // may not exist. Let the wizard's determineSetupStep() advance naturally
        // once the import completes.
        dispatch(new ImportWhatsAppTemplatesJob($gym->id));

        return to_route('filament.admin.pages.whats-app-setup', ['tenant' => $tenant, 'setup_step' => 2])
            ->with('success', 'WABA connected manually. Importing your templates…')
            ->with('templates_import_pending', true);
    }
}
