@extends('errors::minimal')

@section('title', __('Link Expired'))
@section('code', '410')
@section('message', __($exception->getMessage() ?: 'This link has expired or has been revoked.'))
