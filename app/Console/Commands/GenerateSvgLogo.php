<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use RuntimeException;

class GenerateSvgLogo extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'logo:generate-svg {source? : The path to the source image (default: public/fluxfitness-logo.png)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate an SVG version of a given image without removing the background';

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

            $this->info('Generating SVG wrapper...');
            $content = file_get_contents($sourcePath);
            throw_if($content === false, RuntimeException::class, "Failed to read original image file: {$sourcePath}");

            $this->generateSvg($content, $imageInfo['width'], $imageInfo['height'], $imageInfo['mime']);

            $this->info('SVG logo generated successfully!');

            return self::SUCCESS;
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }

    protected function resolveSourcePath(): string
    {
        $source = $this->argument('source') ?? 'public/fluxfitness-logo.png';

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

    protected function generateSvg(string $content, int $width, int $height, string $mime): void
    {
        // Name it logo-original.svg or just logo.svg?
        // We'll name it original-logo.svg to not overwrite the background-removed one, or logo.svg if they want it.
        // Let's name it exact-logo.svg
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
