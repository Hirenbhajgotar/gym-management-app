<?php

namespace App\Services;

interface WhatsAppServiceInterface
{
    public function sendMessage(string $to, string $message): array;
}
