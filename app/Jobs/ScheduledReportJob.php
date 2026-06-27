<?php

declare(strict_types=1);

namespace App\Jobs;

use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use App\Models\ScheduledReport;
use App\Services\ReportPdfGenerator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class ScheduledReportJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public ScheduledReport $scheduledReport
    ) {}

    /**
     * Execute the job.
     */
    public function handle(ReportPdfGenerator $pdfGenerator): void
    {
        if (! $this->scheduledReport->is_active) {
            return;
        }

        try {
            $pdfContent = $pdfGenerator->generate($this->scheduledReport);
            $filename = Str::slug($this->scheduledReport->name) . '-' . now()->format('Y-m-d') . '.pdf';

            // Send Email
            foreach ($this->scheduledReport->recipients as $recipient) {
                Mail::send([], [], function ($message) use ($recipient, $pdfContent, $filename) {
                    $message->to($recipient)
                        ->subject("Your Scheduled Report: {$this->scheduledReport->name}")
                        ->attachData($pdfContent, $filename, [
                            'mime' => 'application/pdf',
                        ])
                        ->html("Please find attached your scheduled report for <strong>{$this->scheduledReport->gym->name}</strong>.");
                });
            }

            // Update last run
            $this->scheduledReport->update(['last_run_at' => now()]);

        } catch (\Exception $e) {
            // Log failure
            Log::error('Scheduled report failed: ' . $e->getMessage());
            // Optionally notify admin or user
        }
    }
}
