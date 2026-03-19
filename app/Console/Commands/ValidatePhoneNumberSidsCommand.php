<?php

namespace App\Console\Commands;

use App\Models\WhatsAppPhoneNumber;
use Illuminate\Console\Command;

class ValidatePhoneNumberSidsCommand extends Command
{
    protected $signature = 'twilio:validate-phone-sids 
                            {--fix : Automatically fix incorrect SID assignments}
                            {--dry-run : Show what would be fixed without actually doing it}';

    protected $description = 'Validate WhatsApp phone number SIDs and fix incorrect assignments.
                             
                             This command checks:
                             - phone_number_sid: Should start with "PN" (IncomingPhoneNumber)
                             - channel_sender_sid: Should start with "CN" (Channel Sender)
                             
                             Common issues:
                             - Channel Sender SID stored in phone_number_sid field (starts with "CN")
                             - Missing channel_sender_sid for new registrations
                             - Legacy data with wrong SID types';

    public function handle(): int
    {
        $this->info('Validating WhatsApp phone number SIDs...');
        $this->line('');

        $phoneNumbers = WhatsAppPhoneNumber::with('account')->get();

        if ($phoneNumbers->isEmpty()) {
            $this->info('No WhatsApp phone numbers found.');

            return self::SUCCESS;
        }

        $this->info("Found {$phoneNumbers->count()} phone number(s)");
        $this->line('');

        $validRecords = [];
        $invalidRecords = [];
        $needsMigration = [];

        foreach ($phoneNumbers as $phoneNumber) {
            $issues = [];

            // Check phone_number_sid (should be PN... or null)
            if (! empty($phoneNumber->phone_number_sid)) {
                if (! $phoneNumber->isValidIncomingPhoneNumberSid()) {
                    if ($phoneNumber->hasLegacyChannelSenderSidInPhoneNumberField()) {
                        $issues[] = 'phone_number_sid contains Channel Sender SID (CN...)';
                        $needsMigration[] = $phoneNumber;
                    } else {
                        $issues[] = 'phone_number_sid has unknown format';
                    }
                }
            }

            // Check channel_sender_sid (should be CN... or null)
            if (! empty($phoneNumber->channel_sender_sid)) {
                if (! $phoneNumber->isValidChannelSenderSid()) {
                    $issues[] = 'channel_sender_sid has unknown format';
                }
            }

            // Report missing channel_sender_sid for non-BYOD numbers
            $hasIncomingNumberSid = $phoneNumber->isValidIncomingPhoneNumberSid();
            $hasChannelSenderSid = $phoneNumber->isValidChannelSenderSid();

            if ($hasIncomingNumberSid && ! $hasChannelSenderSid && empty($phoneNumber->channel_sender_sid)) {
                $issues[] = 'missing channel_sender_sid (purchased number without WhatsApp sender)';
            }

            if (empty($issues)) {
                $validRecords[] = $phoneNumber;
            } else {
                $invalidRecords[] = [
                    'phone_number' => $phoneNumber,
                    'issues' => $issues,
                ];
            }
        }

        // Display results
        $this->displayResults($validRecords, $invalidRecords, $needsMigration);

        // Fix if requested
        if ($this->option('fix') && ! empty($needsMigration)) {
            $this->fixIssues($needsMigration);
        } elseif ($this->option('dry-run') && ! empty($needsMigration)) {
            $this->info('');
            $this->info('Dry run mode - no changes were made.');
            $this->info('Run with --fix to automatically correct the issues.');
        }

        return self::SUCCESS;
    }

    protected function displayResults(array $validRecords, array $invalidRecords, array $needsMigration): void
    {
        // Valid records
        if (! empty($validRecords)) {
            $this->info('✅ Valid Records: ' . count($validRecords));
            foreach ($validRecords as $record) {
                $this->line("   {$record->phone_number} - PN: " . substr($record->phone_number_sid ?? 'N/A', 0, 20) . '... CN: ' . substr($record->channel_sender_sid ?? 'N/A', 0, 20) . '...');
            }
            $this->line('');
        }

        // Invalid records
        if (! empty($invalidRecords)) {
            $this->error('❌ Records with Issues: ' . count($invalidRecords));
            foreach ($invalidRecords as $item) {
                $phoneNumber = $item['phone_number'];
                $this->line("   {$phoneNumber->phone_number}");
                foreach ($item['issues'] as $issue) {
                    $this->line("      - {$issue}");
                }
                $this->line('      phone_number_sid: ' . ($phoneNumber->phone_number_sid ?? 'null'));
                $this->line('      channel_sender_sid: ' . ($phoneNumber->channel_sender_sid ?? 'null'));
            }
            $this->line('');
        }

        // Summary
        $this->info('Summary:');
        $this->line('   Total records: ' . count($validRecords) + count($invalidRecords));
        $this->line('   Valid: ' . count($validRecords));
        $this->line('   With issues: ' . count($invalidRecords));
        $this->line('   Needs migration: ' . count($needsMigration));
        $this->line('');

        if (! empty($needsMigration)) {
            $this->warn('⚠️  ' . count($needsMigration) . ' record(s) can be auto-fixed.');
            $this->line('   Run with --fix to correct the SID assignments.');
        }
    }

    protected function fixIssues(array $needsMigration): void
    {
        $this->info('');
        $this->info('Fixing issues...');

        foreach ($needsMigration as $phoneNumber) {
            // Move Channel Sender SID from phone_number_sid to channel_sender_sid
            $oldSid = $phoneNumber->phone_number_sid;
            $phoneNumber->channel_sender_sid = $oldSid;
            $phoneNumber->phone_number_sid = null; // Clear the wrong value
            $phoneNumber->save();

            $this->line("   ✅ Fixed {$phoneNumber->phone_number}");
            $this->line("      Moved {$oldSid} to channel_sender_sid");
            $this->line('      Cleared phone_number_sid (was storing Channel Sender SID)');
        }

        $this->info('');
        $this->info('Fix complete! ' . count($needsMigration) . ' record(s) corrected.');
    }
}
