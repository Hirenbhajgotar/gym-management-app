<?php

declare(strict_types=1);

namespace App\Services\ErrorReporting;

use App\Biometrics\Services\BiometricLogRedactor;
use Illuminate\Http\Request;
use Throwable;

class RequestOriginResolver
{
    /**
     * Internal endpoints whose real page is hidden behind them.
     *
     * When an error is thrown here, the referer is the URL an operator needs.
     */
    private const INTERNAL_AJAX_PATTERNS = [
        'livewire/update',
        'livewire/upload-file',
    ];

    /**
     * @return array{requested_url: string, origin_url: string, is_livewire_request: bool, livewire_component: string|null}
     */
    public function resolve(Request $request): array
    {
        $isInternalAjax = $this->isInternalAjaxRequest($request);

        return [
            'requested_url' => $this->redactUrl($request->fullUrl()),
            'origin_url' => $this->redactUrl(
                $isInternalAjax
                    ? ($request->headers->get('referer') ?? $request->fullUrl())
                    : $request->fullUrl()
            ),
            'is_livewire_request' => $isInternalAjax,
            'livewire_component' => $isInternalAjax ? $this->extractLivewireComponent($request) : null,
        ];
    }

    public function redactUrl(?string $url): string
    {
        return BiometricLogRedactor::redactUrl($url);
    }

    private function isInternalAjaxRequest(Request $request): bool
    {
        foreach (self::INTERNAL_AJAX_PATTERNS as $pattern) {
            if ($request->is($pattern)) {
                return true;
            }
        }

        return $request->hasHeader('X-Livewire');
    }

    private function extractLivewireComponent(Request $request): ?string
    {
        try {
            $components = $request->array('components');

            $firstComponent = $components[0] ?? null;
            if (! is_array($firstComponent)) {
                return null;
            }

            $snapshotRaw = $firstComponent['snapshot'] ?? null;

            if (blank($snapshotRaw) || ! is_string($snapshotRaw)) {
                return null;
            }

            $snapshot = json_decode($snapshotRaw, true, 512, flags: JSON_THROW_ON_ERROR);

            if (! is_array($snapshot) || ! isset($snapshot['memo']) || ! is_array($snapshot['memo'])) {
                return null;
            }

            $name = $snapshot['memo']['name'] ?? null;
            if (is_string($name)) {
                return $name;
            }

            $class = $snapshot['memo']['class'] ?? null;
            if (is_string($class)) {
                return $class;
            }

            return null;
        } catch (Throwable) {
            return null;
        }
    }
}
