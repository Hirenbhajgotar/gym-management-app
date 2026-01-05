<?php

namespace App\Http\Controllers;

use App\Models\CmsPage;
use Illuminate\Http\Request;

class CmsPageController extends Controller
{
    public function show($slug)
    {
        $page = CmsPage::where('slug', $slug)
            ->where('is_published', true)
            ->firstOrFail();

        return view('cms.show', compact('page'));
    }
}
