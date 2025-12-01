<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\WhatsAppLeadProcessor;

class WhatsAppLeadController extends Controller
{
    public function __construct(
        private WhatsAppLeadProcessor $processor
    ) {}
}
