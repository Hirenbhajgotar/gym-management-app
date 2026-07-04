<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\CmsPage;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Spatie\Sitemap\Sitemap;
use Spatie\Sitemap\SitemapIndex;
use Spatie\Sitemap\Tags\Url;
use Throwable;

class GenerateSitemap extends Command
{
    protected $signature = 'sitemap:generate';

    protected $description = 'Generate the sitemap.';

    public function handle(): int
    {
        try {
            $this->info('Generating sitemap...');

            // Main Sitemap — static pages
            Sitemap::create()
                ->add(Url::create('/'))
                // ->add(Url::create('/business-register'))
                ->add(Url::create('/request-demo'))
                ->writeToFile(public_path('sitemap_main.xml'));

            // CMS Sitemap — dynamic pages
            $this->buildIndex(CmsPage::query()->published()->get(), 'sitemap_cms.xml');

            // Sitemap Index
            SitemapIndex::create()
                ->add('/sitemap_main.xml')
                ->add('/sitemap_cms.xml')
                ->writeToFile(public_path('sitemap.xml'));

            $this->info('Sitemap generated successfully!');

            return Command::SUCCESS;
        } catch (Throwable $th) {
            Log::error('Sitemap generation failed: ', [
                'error' => $th->getMessage(),
                'trace' => $th->getTraceAsString(),
                'file' => $th->getFile(),
                'line' => $th->getLine(),
            ]);
            throw $th;
        }
    }

    /**
     * @param  Collection<int, CmsPage>  $pages
     */
    private function buildIndex(Collection $pages, string $path): string
    {
        Sitemap::create()
            ->add($pages)
            ->writeToFile(public_path($path));

        return $path;
    }
}
