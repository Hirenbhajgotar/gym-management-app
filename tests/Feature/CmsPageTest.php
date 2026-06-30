<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\CmsPage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CmsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_cms_page_can_be_viewed()
    {
        // Create a published CMS page
        CmsPage::factory()->create([
            'slug' => 'terms-of-service',
            'title' => 'Terms of Service',
            'content' => 'This is the content of the terms of service.',
            'is_published' => true,
        ]);

        // Visit the page
        $response = $this->get('/page/terms-of-service');

        // Assert the page loads successfully
        $response->assertOk();
        $response->assertSee('Terms of Service');
        $response->assertSee('This is the content of the terms of service.');
    }

    public function test_unpublished_cms_page_cannot_be_viewed()
    {
        // Create an unpublished CMS page
        CmsPage::factory()->create([
            'slug' => 'private-page',
            'title' => 'Private Page',
            'content' => 'This is private.',
            'is_published' => false,
        ]);

        // Visit the page
        $response = $this->get('/page/private-page');

        // Assert 404
        $response->assertNotFound();
    }
}
