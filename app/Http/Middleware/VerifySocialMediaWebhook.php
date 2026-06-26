<?php

namespace App\Http\Middleware;

use App\Services\FacebookLeadProcessor;
use App\Services\IndiaMartLeadProcessor;
use App\Services\InstagramLeadProcessor;
use App\Services\JustDialLeadProcessor;
use App\Services\SocialMediaLeadProcessor;
use App\Services\WhatsAppLeadProcessor;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class VerifySocialMediaWebhook
{
    /**
     * Handle an incoming request.
     *
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        try {
            $platform = $this->getPlatformFromRequest($request);

            if (! $platform) {
                Log::warning('Unknown social media platform in webhook request', [
                    'path' => $request->path(),
                    'headers' => $request->headers->all(),
                ]);

                return response()->json(['error' => 'Unknown platform'], 400);
            }

            // Get the appropriate processor for verification
            $processor = $this->getProcessor($platform);

            if (! $processor) {
                Log::warning('No processor available for platform', ['platform' => $platform]);

                return response()->json(['error' => 'Platform not supported'], 400);
            }

            // Extract data and headers for verification
            $data = $request->all();
            $headers = $request->headers->all();

            // Verify the webhook signature
            if (! $processor->verifyWebhook($data, $headers)) {
                Log::warning('Webhook verification failed', [
                    'platform' => $platform,
                    'path' => $request->path(),
                    'headers' => $headers,
                ]);

                return response()->json(['error' => 'Invalid signature'], 401);
            }

            // Add platform information to the request for later use
            $request->attributes->set('social_media_platform', $platform);

            return $next($request);
        } catch (\Exception $e) {
            Log::error('Webhook verification middleware error', [
                'error' => $e->getMessage(),
                'path' => $request->path(),
                'headers' => $request->headers->all(),
            ]);

            return response()->json(['error' => 'Verification failed'], 500);
        }
    }

    /**
     * Determine the social media platform from the request
     */
    private function getPlatformFromRequest(Request $request): ?string
    {
        $path = $request->path();
        $request->getHost();

        // Check path-based routing
        if (str_contains($path, 'facebook')) {
            return 'facebook';
        } elseif (str_contains($path, 'instagram')) {
            return 'instagram';
        } elseif (str_contains($path, 'whatsapp')) {
            return 'whatsapp';
        } elseif (str_contains($path, 'indiamart')) {
            return 'indiamart';
        } elseif (str_contains($path, 'justdial')) {
            return 'justdial';
        }

        // Check headers for platform-specific signatures
        $headers = $request->headers->all();

        // Facebook/Instagram webhook signature
        if (isset($headers['x-hub-signature-256'][0])) {
            return 'facebook'; // Instagram also uses Facebook's signature
        }

        // WhatsApp Business API signature
        if (isset($headers['x-hub-signature'][0]) && str_contains($path, 'whatsapp')) {
            return 'whatsapp';
        }

        // IndiaMart API key
        if (isset($headers['x-api-key'][0]) && str_contains($path, 'indiamart')) {
            return 'indiamart';
        }

        // JustDial API key
        if (isset($headers['x-api-key'][0]) && str_contains($path, 'justdial')) {
            return 'justdial';
        }

        // Check for platform-specific headers
        if (isset($headers['user-agent'][0])) {
            $userAgent = strtolower($headers['user-agent'][0]);

            if (str_contains($userAgent, 'facebook')) {
                return 'facebook';
            } elseif (str_contains($userAgent, 'instagram')) {
                return 'instagram';
            } elseif (str_contains($userAgent, 'whatsapp')) {
                return 'whatsapp';
            } elseif (str_contains($userAgent, 'indiamart')) {
                return 'indiamart';
            } elseif (str_contains($userAgent, 'justdial')) {
                return 'justdial';
            }
        }

        return null;
    }

    /**
     * Get the appropriate processor for the platform
     */
    private function getProcessor(string $platform): ?SocialMediaLeadProcessor
    {
        return match ($platform) {
            'facebook' => new FacebookLeadProcessor,
            'instagram' => new InstagramLeadProcessor,
            'whatsapp' => new WhatsAppLeadProcessor,
            'indiamart' => new IndiaMartLeadProcessor,
            'justdial' => new JustDialLeadProcessor,
            default => null,
        };
    }
}
