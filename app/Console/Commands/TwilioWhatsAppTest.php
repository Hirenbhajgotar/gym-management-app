<?php

namespace App\Console\Commands;

use App\Services\TwilioWhatsAppService;
use Illuminate\Console\Command;

class TwilioWhatsAppTest extends Command
{
    protected $signature = 'twilio:test {phone} {--message=}';

    protected $description = 'Send a test Twilio WhatsApp message';

    public function handle()
    {
        $phone = $this->argument('phone');
        $message = $this->option('message') ?? 'Hello! This is a test message from your gym app.';

        $this->info("Sending test message to: {$phone}");

        try {
            // Use a test tenant ID or your actual tenant
            $service = new TwilioWhatsAppService('2'); // Fitness-5 Tenant ID

            $result = $service->sendMessage($phone, $message);

            if ($result['success']) {
                $this->info('✅ Message sent successfully!');
                $this->info("Message ID: {$result['message_id']}");
                $this->info("Status: {$result['status']}");
            } else {
                $this->error('❌ Failed to send message');
                $this->error("Error: {$result['error']}");
            }
        } catch (\Exception $e) {
            $this->error('❌ Exception: ' . $e->getMessage());
        }
    }
}
