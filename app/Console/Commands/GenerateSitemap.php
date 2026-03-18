<?php

namespace App\Console\Commands;

use App\Models\CmsPage;
use Illuminate\Console\Command;
use Spatie\Sitemap\Sitemap;
use Spatie\Sitemap\Tags\Url;

class GenerateSitemap extends Command
{
    protected $signature = 'sitemap:generate';

    protected $description = 'Generate the sitemap.';

    public function handle(): int
    {
        $this->info('Generating sitemap...');

        Sitemap::create()
            ->add($this->buildIndex(CmsPage::query()->published()->get(), 'sitemap_cms.xml'))
            ->add(Url::create('/'))
            ->add(Url::create('/business-register'))
            ->add(Url::create('/request-demo'))
            ->add(Url::create('/thank-you'))
            ->writeToFile(public_path('sitemap.xml'));

        $this->info('Sitemap generated successfully!');

        return Command::SUCCESS;
    }

    private function buildIndex($model, string $path): string
    {
        Sitemap::create()
            ->add($model)
            ->writeToFile(public_path($path));

        return $path;
    }
}
