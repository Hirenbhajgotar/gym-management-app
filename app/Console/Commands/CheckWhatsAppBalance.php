<?php

namespace App\Console\Commands;

use App\Jobs\CheckWhatsAppBalanceJob;
use Illuminate\Console\Command;

class CheckWhatsAppBalance extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'whatsapp:check-balance {--queue : Run the job in queue}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check WhatsApp message balances and send low balance alerts';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Checking WhatsApp message balances...');

        if ($this->option('queue')) {
            CheckWhatsAppBalanceJob::dispatch();
            $this->info('Job dispatched to queue.');
        } else {
            (new CheckWhatsAppBalanceJob)->handle();
            $this->info('Balance check completed.');
        }

        return Command::SUCCESS;
    }
}
