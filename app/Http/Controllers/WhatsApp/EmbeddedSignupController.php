<?php

namespace App\Http\Controllers\WhatsApp;

use App\Http\Controllers\Controller;
use App\Jobs\ImportWhatsAppTemplatesJob;
use App\Models\Gym;
use App\Providers\MetaWhatsAppProvider;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class EmbeddedSignupController extends Controller
{
    public function redirect(Request $request, string $tenant): RedirectResponse
    {
        $gym = Gym::where('slug', $tenant)->first();

        if (! $gym) {
            $route = route('filament.admin.pages.whats-app-setup', ['tenant' => $tenant]);

            return redirect($route . '?step=2')->with('error', 'Tenant not found');
        }

        // Create provider with gym ID - can't be auto-wired due to required $gymId parameter
        $metaWhatsAppProvider = new MetaWhatsAppProvider($gym->id);

        if (! $metaWhatsAppProvider->isEmbeddedSignupEnabled()) {
            $route = route('filament.admin.pages.whats-app-setup', ['tenant' => $tenant]);

            return redirect($route . '?step=2')->with('error', 'Embedded Signup is not enabled');
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

        $redirectUrl = $tenantSlug
            ? route('filament.admin.pages.whats-app-setup', ['tenant' => $tenantSlug])
            : url('/admin');

        if ($error) {
            return redirect($redirectUrl . '?step=2')
                ->with('error', $errorDescription ?? $error);
        }

        if (! $code || ! $state) {
            return redirect($redirectUrl . '?step=2')
                ->with('error', 'Invalid callback parameters');
        }

        // Create provider for handling callback - use MetaEmbeddedSignupService directly for callback processing
        $metaWhatsAppProvider = new MetaWhatsAppProvider((int) $tenantId);

        $result = $metaWhatsAppProvider->handleEmbeddedSignupCallback((string) $code, (string) $state);

        Log::info('Embedded Signup callback result', [
            'result' => $result,
        ]);

        if (! $result['success']) {
            return redirect($redirectUrl . '?step=2')
                ->with('error', $result['error']);
        }

        if ($tenantId && ! empty($result['waba_id'])) {
            // Use MetaWabaService to complete embedded signup (save newly created WABA)
            $metaWhatsAppProvider->completeEmbeddedSignup(
                $tenantId,
                $result['waba_id'],
                $result['access_token'],
                $result['phone_number_id'] ?? null
            );
        }

        return redirect($redirectUrl . '?step=2')
            ->with('success', 'WABA connected successfully')
            ->with('waba_id', $result['waba_id'] ?? null);
    }

    public function manualConnect(Request $request, string $tenant): RedirectResponse
    {
        $gym = Gym::where('slug', $tenant)->first();

        if (! $gym) {
            return redirect()->back()->with('error', 'Tenant not found');
        }

        $validated = $request->validate([
            'waba_id' => ['required', 'string', 'max:100', 'regex:/^[A-Z0-9]+$/'],
            'manual_phone_number' => ['required', 'string', 'max:20', 'regex:/^\+?[1-9]\d{1,14}$/'],
            'waba_phone_number_id' => ['required', 'string', 'max:100'],
            'meta_access_token' => ['nullable', 'string', 'max:2000'],
        ]);

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
            return redirect()
                ->route('filament.admin.pages.whats-app-setup', ['tenant' => $tenant, 'step' => 2])
                ->with('error', $result['error']);
        }

        // Import existing Meta templates as DRAFT records
        $job = new ImportWhatsAppTemplatesJob($gym->id);
        $job->onQueue('whatsapp-templates');
        dispatch($job);

        return redirect()
            ->route('filament.admin.pages.whats-app-setup', ['tenant' => $tenant, 'step' => 3])
            ->with('success', 'WABA connected manually');
    }
}
