<?php

namespace App\Http\Controllers\WhatsApp;

use App\Http\Controllers\Controller;
use App\Models\Gym;
use App\Services\EmbeddedSignupService;
use App\Services\WabaMigrationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class EmbeddedSignupController extends Controller
{
    public function __construct(
        private EmbeddedSignupService $embeddedSignupService,
        private WabaMigrationService $migrationService
    ) {}

    public function redirect(Request $request, string $tenant): RedirectResponse
    {
        $gym = Gym::where('slug', $tenant)->first();

        if (! $gym) {
            return redirect()->route('filament.admin.pages.whats-app-setup', ['tenant' => $tenant])
                ->with('error', 'Tenant not found');
        }

        if (! $this->embeddedSignupService->isEnabled()) {
            return redirect()->route('filament.admin.pages.whats-app-setup', ['tenant' => $tenant])
                ->with('error', 'Embedded Signup is not enabled');
        }

        session(['embedded_signup_tenant_id' => $gym->id]);
        session(['embedded_signup_tenant_slug' => $gym->slug]);

        $loginUrl = $this->embeddedSignupService->getLoginUrl($gym->id);

        return redirect()->away($loginUrl);
    }

    public function callback(Request $request): RedirectResponse
    {
        $code = $request->get('code');
        $state = $request->get('state');
        $error = $request->get('error');
        $errorDescription = $request->get('error_description');

        $tenantSlug = session('embedded_signup_tenant_slug');
        $tenantId = session('embedded_signup_tenant_id');

        session()->forget(['embedded_signup_tenant_id', 'embedded_signup_tenant_slug']);

        $redirectUrl = $tenantSlug
            ? route('filament.admin.pages.whats-app-setup', ['tenant' => $tenantSlug])
            : url('/admin');

        if ($error) {
            Log::error('Embedded Signup callback error', [
                'error' => $error,
                'description' => $errorDescription,
            ]);

            return redirect($redirectUrl)
                ->with('error', $errorDescription ?? $error);
        }

        if (! $code || ! $state) {
            return redirect($redirectUrl)
                ->with('error', 'Invalid callback parameters');
        }

        $result = $this->embeddedSignupService->handleCallback($code, $state);

        if (! $result['success']) {
            return redirect($redirectUrl)
                ->with('error', $result['error']);
        }

        if ($tenantId && ! empty($result['waba_id'])) {
            $phoneNumber = $result['phone_number_id'] ?? '';

            $this->embeddedSignupService->connectWabaToSubaccount(
                $tenantId,
                $result['waba_id'],
                $phoneNumber,
                $result['access_token']
            );
        }

        return redirect($redirectUrl)
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
            'waba_id' => ['required', 'string'],
            'phone_number' => ['required', 'string'],
            'meta_access_token' => ['nullable', 'string'],
        ]);

        $result = $this->embeddedSignupService->connectManualWaba(
            $gym->id,
            $validated['waba_id'],
            $validated['phone_number'],
            $validated['meta_access_token'] ?? null
        );

        if (! $result['success']) {
            return redirect()->back()->with('error', $result['error']);
        }

        return redirect()
            ->route('filament.admin.pages.whats-app-setup', ['tenant' => $tenant])
            ->with('success', 'WABA connected manually');
    }
}
