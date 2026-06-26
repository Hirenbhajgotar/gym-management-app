<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use RuntimeException;

class ProcessLogo extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'logo:process {source? : The path to the source image (default: public/fluxfitness-new-main-logo.png)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Remove background from a logo and generate an SVG version';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        try {
            throw_unless(extension_loaded('gd'), RuntimeException::class, 'GD extension is required but not loaded.');

            $sourcePath = $this->resolveSourcePath();
            $this->info("Using source: {$sourcePath}");

            $imageInfo = $this->validateImage($sourcePath);
            $this->info("Source dimensions: {$imageInfo['width']}x{$imageInfo['height']}");

            // 1. Remove Background
            $this->info('Removing background using exact flood-fill from corners...');
            $transparentImagePath = $this->removeBackground($sourcePath, $imageInfo['mime']);
            $this->info("Generated transparent logo: {$transparentImagePath}");

            // 2. Generate SVG from the transparent image
            $this->info('Generating SVG wrapper...');
            $transparentContent = file_get_contents($transparentImagePath);
            throw_if($transparentContent === false, RuntimeException::class, "Failed to read transparent image file: {$transparentImagePath}");
            $this->generateSvg($transparentContent, $imageInfo['width'], $imageInfo['height'], 'image/png');

            $this->info('Logo processing completed successfully!');

            return self::SUCCESS;
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }

    protected function resolveSourcePath(): string
    {
        $source = $this->argument('source') ?? 'public/fluxfitness-new-main-logo.png';

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
        $info = @getimagesize($path);

        throw_unless($info, RuntimeException::class, 'Could not get image info.');

        $width = $info[0];
        $height = $info[1];
        $mime = $info['mime'];

        throw_unless(in_array($mime, ['image/png', 'image/jpeg']), RuntimeException::class, "Source file must be a PNG or JPEG image. Found: {$mime}");

        return ['width' => $width, 'height' => $height, 'mime' => $mime];
    }

    protected function removeBackground(string $sourcePath, string $mime): string
    {
        if ($mime === 'image/png') {
            $im = @imagecreatefrompng($sourcePath);
        } else {
            $im = @imagecreatefromjpeg($sourcePath);
        }

        throw_unless($im, RuntimeException::class, 'Failed to load image.');

        $width = imagesx($im);
        $height = imagesy($im);

        // Create a true color image with alpha channel
        $transparentImage = imagecreatetruecolor($width, $height);
        imagealphablending($transparentImage, false);
        imagesavealpha($transparentImage, true);

        // Copy original image
        imagecopy($transparentImage, $im, 0, 0, 0, 0, $width, $height);
        $bgColorTR = imagecolorat($transparentImage, $width - 1, 0);
        $bgColorBL = imagecolorat($transparentImage, 0, $height - 1);
        $bgColorBR = imagecolorat($transparentImage, $width - 1, $height - 1);

        // Color to fill with (fully transparent)
        $transparency = imagecolorallocatealpha($transparentImage, 0, 0, 0, 127);

        // Flood fill from corners to catch all exterior background
        imagefill($transparentImage, 0, 0, $transparency);

        // if other corners had slightly different matched background, fill them too
        // (usually the same color index in PNGs, but let's be safe)
        if (imagecolorat($transparentImage, $width - 1, 0) === $bgColorTR) {
            imagefill($transparentImage, $width - 1, 0, $transparency);
        }
        if (imagecolorat($transparentImage, 0, $height - 1) === $bgColorBL) {
            imagefill($transparentImage, 0, $height - 1, $transparency);
        }
        if (imagecolorat($transparentImage, $width - 1, $height - 1) === $bgColorBR) {
            imagefill($transparentImage, $width - 1, $height - 1, $transparency);
        }

        $destPath = public_path('logo-transparent.png');

        if (! imagepng($transparentImage, $destPath)) {
            imagedestroy($im);
            imagedestroy($transparentImage);
            throw new RuntimeException("Failed to save transparent image to {$destPath}");
        }

        imagedestroy($im);
        imagedestroy($transparentImage);

        return $destPath;
    }

    protected function generateSvg(string $content, int $width, int $height, string $mime): void
    {
        $dest = public_path('logo.svg');
        $base64 = base64_encode($content);

        $svg = <<<SVG
<svg version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" viewBox="0 0 {$width} {$height}">
<image width="100%" height="100%" href="data:{$mime};base64,{$base64}" />
</svg>
SVG;

        throw_if(file_put_contents($dest, $svg) === false, RuntimeException::class, "Failed to write SVG to {$dest}");

        $this->info("Generated {$dest}");
    }
}
