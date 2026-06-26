<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use RuntimeException;

class GenerateFavicons extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'icons:generate {source? : The path to the source image (default: public/fluxfitness-icon.png)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate favicon.svg and favicon.ico from a source PNG image';

    protected const ICON_SIZE = 256;

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        try {
            $sourcePath = $this->resolveSourcePath();
            $this->info("Using source: {$sourcePath}");

            $imageInfo = $this->validateImage($sourcePath);
            $this->info("Source dimensions: {$imageInfo['width']}x{$imageInfo['height']}");

            $fileContent = file_get_contents($sourcePath);
            throw_if($fileContent === false, RuntimeException::class, "Failed to read source file: {$sourcePath}");

            $this->generateSvg($fileContent, $imageInfo['width'], $imageInfo['height'], $imageInfo['mime']);
            $this->generateIco($sourcePath, $fileContent, $imageInfo['width'], $imageInfo['height']);

            return self::SUCCESS;
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }

    protected function resolveSourcePath(): string
    {
        $source = $this->argument('source') ?? 'public/fluxfitness-icon.png';

        if (file_exists($source)) {
            return $source;
        }

        $absolutePath = base_path($source);
        if (file_exists($absolutePath)) {
            return $absolutePath;
        }

        throw new RuntimeException("Source file not found: {$source}");
    }

    /**
     * @return array{width: int, height: int, mime: string}
     */
    protected function validateImage(string $path): array
    {
        $info = getimagesize($path);

        throw_unless($info, RuntimeException::class, 'Could not get image info.');

        $width = $info[0];
        $height = $info[1];
        $mime = $info['mime'];

        throw_if($mime !== 'image/png', RuntimeException::class, "Source file must be a PNG image. Found: {$mime}");

        return ['width' => $width, 'height' => $height, 'mime' => $mime];
    }

    protected function generateSvg(string $content, int $width, int $height, string $mime): void
    {
        $dest = public_path('favicon.svg');
        $base64 = base64_encode($content);

        $svg = <<<SVG
<svg version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" viewBox="0 0 {$width} {$height}">
<image width="100%" height="100%" href="data:{$mime};base64,{$base64}" />
</svg>
SVG;

        throw_if(file_put_contents($dest, $svg) === false, RuntimeException::class, "Failed to write SVG to {$dest}");

        $this->info("Generated {$dest}");
    }

    protected function generateIco(string $sourcePath, string $originalContent, int $width, int $height): void
    {
        $dest = public_path('favicon.ico');
        $pngContent = $originalContent;
        $finalWidth = $width;
        $finalHeight = $height;

        if ($width > self::ICON_SIZE || $height > self::ICON_SIZE) {
            $this->info('Image > 256px, resizing for ICO...');
            $resizedPng = $this->createResizedImage($sourcePath);

            if ($resizedPng) {
                $pngContent = $resizedPng;
                $finalWidth = self::ICON_SIZE;
                $finalHeight = self::ICON_SIZE;
            } else {
                $this->warn('GD failed to load image, using original content (may be too large for some old ICO readers)');
            }
        }

        // ICO Header: Reserved (2), Type (2), Count (2)
        $icoHeader = pack('v3', 0, 1, 1);

        // Directory Entry: Width (1), Height (1), ColorCount (1), Reserved (1), Planes (2), BitCount (2), SizeInBytes (4), Offset (4)
        $wVal = ($finalWidth >= 256) ? 0 : $finalWidth;
        $hVal = ($finalHeight >= 256) ? 0 : $finalHeight;

        $entry = pack(
            'C4v2V2',
            $wVal,
            $hVal,
            0, // Color count (0 for >= 8bpp)
            0, // Reserved
            1, // Planes
            32, // BitCount (assuming 32 for PNG with alpha)
            strlen($pngContent),
            22 // Offset (6 header + 16 entry)
        );

        throw_if(file_put_contents($dest, $icoHeader . $entry . $pngContent) === false, RuntimeException::class, "Failed to write ICO to {$dest}");

        $this->info("Generated {$dest}");
    }

    protected function createResizedImage(string $sourcePath): ?string
    {
        $im = @imagecreatefrompng($sourcePath);
        if (! $im) {
            return null;
        }

        $resized = imagecreatetruecolor(self::ICON_SIZE, self::ICON_SIZE);
        if (! $resized) {
            imagedestroy($im);

            return null;
        }

        // Preserve transparency
        imagealphablending($resized, false);
        imagesavealpha($resized, true);
        $transparent = imagecolorallocatealpha($resized, 255, 255, 255, 127);
        imagefilledrectangle($resized, 0, 0, self::ICON_SIZE, self::ICON_SIZE, $transparent);

        $width = imagesx($im);
        $height = imagesy($im);

        imagecopyresampled($resized, $im, 0, 0, 0, 0, self::ICON_SIZE, self::ICON_SIZE, $width, $height);

        ob_start();
        imagepng($resized);
        $pngContent = ob_get_clean();

        imagedestroy($im);
        imagedestroy($resized);

        return $pngContent ?: null;
    }
}
