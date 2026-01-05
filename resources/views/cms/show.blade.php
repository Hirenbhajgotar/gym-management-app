@extends('layouts.app')

@section('title', $page->meta_title ?? $page->title)
@section('meta_description', $page->meta_description)

@section('content')
    <div class="max-w-4xl mx-auto px-4 py-8 sm:px-6 lg:px-8">
        <h1 class="text-3xl font-bold text-gray-900 dark:text-white mb-6">
            {{ $page->title }}
        </h1>

        <div class="prose dark:prose-invert max-w-none">
            {!! $page->content !!}
        </div>
    </div>
@endsection
