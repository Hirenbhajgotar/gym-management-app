<?php

namespace App\Console\Commands;

use App\Services\TwilioWhatsAppService;
use Illuminate\Console\Command;

class TestSystemWhatsApp extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'whatsapp:test-system {phone} {--message=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test WhatsApp messaging for system tenant (super-admin)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $phone = $this->argument('phone');
        $message = $this->option('message') ?? 'Hello! This is a test message from system tenant (super-admin).';

        $this->info('Testing WhatsApp message for system tenant...');
        $this->info("Phone: {$phone}");
        $this->info("Message: {$message}");
        $this->newLine();

        try {
            // Use system tenant ID
            $tenantId = config('app.system_tenant_id', 'system');

            $this->info("Using tenant ID: {$tenantId}");
            $this->info("Sandbox mode: " . (config('services.twilio.use_sandbox') ? 'Yes' : 'No'));
            $this->newLine();

            // Initialize service with system tenant
            $service = new TwilioWhatsAppService($tenantId);

            // Send message
            $result = $service->sendMessage($phone, $message);

            if ($result['success']) {
                $this->info('✅ Message sent successfully!');
                $this->newLine();

                $this->table(
                    ['Key', 'Value'],
                    [
                        ['Message ID', $result['message_id'] ?? 'N/A'],
                        ['Status', $result['status'] ?? 'N/A'],
                        ['From', $result['from'] ?? 'N/A'],
                        ['System Message', isset($result['system_message']) && $result['system_message'] ? 'Yes' : 'No'],
                    ]
                );

                if (config('services.twilio.use_sandbox')) {
                    $this->newLine();
                    $this->warn('⚠️  Sandbox Mode Active');
                    $this->line('Make sure the recipient has joined the Twilio sandbox by sending:');
                    $this->line('  "join [your-sandbox-keyword]"');
                    $this->line('  to: ' . config('services.twilio.sandbox_number'));
                }

                return Command::SUCCESS;
            } else {
                $this->error('❌ Message sending failed!');
                $this->newLine();
                $this->error('Error: ' . ($result['error'] ?? 'Unknown error'));

                if (isset($result['code'])) {
                    $this->error('Error Code: ' . $result['code']);
                }

                return Command::FAILURE;
            }
        } catch (\Exception $e) {
            $this->error('❌ Exception occurred!');
            $this->error($e->getMessage());
            $this->newLine();
            $this->error('Stack trace:');
            $this->line($e->getTraceAsString());

            return Command::FAILURE;
        }
    }
}
