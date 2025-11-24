<?php

// app/Services/AvatarService.php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class AvatarService
{
    public static function generate($name, $size = 128, $background = '3498db', $color = 'ffffff')
    {
        try {
            // $initials = self::getInitials($name);
            $filename = md5($name . time()) . '.png';
            $path = 'avatars2/' . $filename;

            // Ensure the avatars directory exists
            if (! file_exists(public_path('avatars2'))) {
                mkdir(public_path('avatars2'), 0755, true);
            }

            // Generate the UI Avatars URL
            $url = 'https://ui-avatars.com/api/?name=' . urlencode($name) . "&size={$size}&background={$background}&color={$color}";

            // Fetch the image
            $response = Http::get($url);

            if ($response->successful()) {
                // Save the image to public/avatars/
                Storage::disk('public')->put($path, $response->body());

                return $path;
            }
        } catch (Exception $e) {
            Log::error('Error generating avatar: ', [
                'exception' => $e,
            ]);

            // Fallback if API fails
            return null;
        }

    }

    private static function getInitials($name)
    {
        $words = explode(' ', trim($name));
        $initials = '';

        foreach (array_slice($words, 0, 2) as $word) {
            $initials .= strtoupper(substr($word, 0, 1));
        }

        return $initials ?: 'NA'; // Fallback if name is empty
    }
}
