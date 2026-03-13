<?php

namespace App\Interfaces;

interface WhatsAppServiceInterface
{
    public function sendMessage(string $to, string $message): array;
}
