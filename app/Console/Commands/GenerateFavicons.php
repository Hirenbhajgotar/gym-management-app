<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

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

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $source = $this->argument('source') ?? 'public/fluxfitness-icon.png';
        
        // Ensure paths are relative to base path if not absolute
        if (!file_exists($source) && file_exists(base_path($source))) {
            $source = base_path($source);
        } elseif (!file_exists($source)) {
             $this->error("Source file not found: $source");
             return 1;
        }

        $svgDest = public_path('favicon.svg');
        $icoDest = public_path('favicon.ico');

        $this->info("Using source: $source");

        $info = getimagesize($source);
        if (!$info) {
            $this->error("Could not get image info.");
            return 1;
        }

        $width = $info[0];
        $height = $info[1];
        $mime = $info['mime'];

        $this->info("Source dimensions: {$width}x{$height}");

        // --- SVG Generation ---
        $content = file_get_contents($source);
        $base64 = base64_encode($content);

        $svg = <<<SVG
<svg version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" viewBox="0 0 {$width} {$height}">
<image width="100%" height="100%" href="data:{$mime};base64,{$base64}" />
</svg>
SVG;

        file_put_contents($svgDest, $svg);
        $this->info("Generated $svgDest");

        // --- ICO Generation ---
        $pngContent = $content;
        $finalWidth = $width;
        $finalHeight = $height;

        if ($width > 256 || $height > 256) {
            $this->info("Image > 256px, resizing for ICO...");
            $im = imagecreatefrompng($source);
            if ($im) {
                $newWidth = 256;
                $newHeight = 256;
                
                $resized = imagecreatetruecolor($newWidth, $newHeight);
                
                // Preserve transparency
                imagealphablending($resized, false);
                imagesavealpha($resized, true);
                $transparent = imagecolorallocatealpha($resized, 255, 255, 255, 127);
                imagefilledrectangle($resized, 0, 0, $newWidth, $newHeight, $transparent);
                
                imagecopyresampled($resized, $im, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
                
                ob_start();
                imagepng($resized);
                $pngContent = ob_get_clean();
                
                $finalWidth = $newWidth;
                $finalHeight = $newHeight;
                
                imagedestroy($im);
                imagedestroy($resized);
            } else {
                $this->warn("GD failed to load image, using original content (may be too large for some old ICO readers)");
            }
        }

        // ICO Header
        // Reserved (2), Type (2), Count (2)
        $icoHeader = pack('v3', 0, 1, 1);

        // Directory Entry
        // Width (1), Height (1), ColorCount (1), Reserved (1), Planes (2), BitCount (2), SizeInBytes (4), Offset (4)
        $wVal = ($finalWidth >= 256) ? 0 : $finalWidth;
        $hVal = ($finalHeight >= 256) ? 0 : $finalHeight;

        $entry = pack('C4v2V2', 
            $wVal, 
            $hVal, 
            0, // Color count (0 for >= 8bpp)
            0, // Reserved
            1, // Planes
            32, // BitCount (assuming 32 for PNG with alpha)
            strlen($pngContent), 
            22 // Offset (6 header + 16 entry)
        );

        file_put_contents($icoDest, $icoHeader . $entry . $pngContent);
        $this->info("Generated $icoDest");
        
        return 0;
    }
}
