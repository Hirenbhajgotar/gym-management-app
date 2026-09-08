<?php

declare(strict_types=1);

namespace Tests\Unit\Helpers;

use App\Helpers\WhatsAppSanitizer;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WhatsAppSanitizerTest extends TestCase
{
    #[Test]
    public function it_masks_meta_access_token(): void
    {
        $rawToken = 'EAACEdEose0cBA1234567890abcdefghijklmnopqrstuvwxyz';
        $input = "Meta API error: API access blocked with token {$rawToken} for developer.";
        $sanitized = WhatsAppSanitizer::sanitizeError($input);

        $this->assertStringContainsString('[TOKEN_MASKED]', $sanitized);
        $this->assertStringNotContainsString($rawToken, $sanitized);
    }

    #[Test]
    public function it_masks_bearer_token(): void
    {
        $rawToken = 'Bearer EAA_some_auth_token_here_123';
        $input = "Authorization header contains {$rawToken} which failed.";
        $sanitized = WhatsAppSanitizer::sanitizeError($input);

        $this->assertStringContainsString('Bearer [TOKEN_MASKED]', $sanitized);
        $this->assertStringNotContainsString($rawToken, $sanitized);
    }

    #[Test]
    public function it_masks_twilio_sid(): void
    {
        $accountSid = 'AC1234567890abcdef1234567890abcdef';
        $secretSid = 'SKabcdef1234567890abcdef1234567890';
        $input = "Twilio failed for account {$accountSid} and key {$secretSid}.";
        $sanitized = WhatsAppSanitizer::sanitizeError($input);

        $this->assertStringContainsString('[SID_MASKED]', $sanitized);
        $this->assertStringNotContainsString($accountSid, $sanitized);
        $this->assertStringNotContainsString($secretSid, $sanitized);
    }

    #[Test]
    public function it_masks_phone_numbers(): void
    {
        $phone1 = '+919876543210';
        $phone2 = '+1 (555) 123-4567';
        $phone3 = '+1-555-123-4567';
        $phone4 = '(555) 123-4567';

        $input = "Failed to send to {$phone1}, {$phone2}, {$phone3}, and {$phone4}.";
        $sanitized = WhatsAppSanitizer::sanitizeError($input);

        $this->assertStringContainsString('[PHONE_MASKED]', $sanitized);
        $this->assertStringNotContainsString($phone1, $sanitized);
        $this->assertStringNotContainsString($phone2, $sanitized);
        $this->assertStringNotContainsString($phone3, $sanitized);
        $this->assertStringNotContainsString($phone4, $sanitized);
    }

    #[Test]
    public function it_does_not_mask_short_alphanumeric_ids(): void
    {
        $orderId = 'req_9876';
        $errorCode = 'ERR_404';
        $input = "Processed request ID: {$orderId} with code: {$errorCode}.";
        $sanitized = WhatsAppSanitizer::sanitizeError($input);

        $this->assertStringContainsString($orderId, $sanitized);
        $this->assertStringContainsString($errorCode, $sanitized);
        $this->assertStringNotContainsString('[PHONE_MASKED]', $sanitized);
    }

    #[Test]
    public function it_masks_long_numeric_pii(): void
    {
        $pii = '1234567890123456';
        $input = "User identification card: {$pii}.";
        $sanitized = WhatsAppSanitizer::sanitizeError($input);

        $this->assertStringContainsString('[PII_MASKED]', $sanitized);
        $this->assertStringNotContainsString($pii, $sanitized);
    }

    #[Test]
    public function it_masks_high_entropy_secrets(): void
    {
        $hexSecret = 'abcdef0123456789abcdef0123456789';
        $alphaNumSecret = 'a1B2c3D4e5F6g7H8a1B2c3D4e5F6g7H8';
        $input = "Client secret was {$hexSecret} and token was {$alphaNumSecret}.";
        $sanitized = WhatsAppSanitizer::sanitizeError($input);

        $this->assertStringContainsString('[TOKEN_MASKED]', $sanitized);
        $this->assertStringNotContainsString($hexSecret, $sanitized);
        $this->assertStringNotContainsString($alphaNumSecret, $sanitized);
    }

    #[Test]
    public function it_strips_control_characters(): void
    {
        $input = "Error msg\x00\x1Fwith control characters.";
        $sanitized = WhatsAppSanitizer::sanitizeError($input);

        $this->assertStringNotContainsString("\x00", $sanitized);
        $this->assertStringNotContainsString("\x1F", $sanitized);
        $this->assertStringContainsString('Error msgwith control characters.', $sanitized);
    }

    #[Test]
    public function it_truncates_at_max_length(): void
    {
        $longError = str_repeat('a', 600);
        $sanitized = WhatsAppSanitizer::sanitizeError($longError);

        $this->assertSame(500, strlen($sanitized));
        $this->assertStringEndsWith('...', $sanitized);
    }

    #[Test]
    public function it_falls_back_on_uncleanable_errors(): void
    {
        $input = "\x00\x01\x02";
        $sanitized = WhatsAppSanitizer::sanitizeError($input);

        $this->assertSame('[UNSANITIZABLE_ERROR]', $sanitized);
    }

    #[Test]
    public function it_masks_custom_strings_like_names(): void
    {
        $input = 'Failed to send message: member John Doe was shifting.';
        $sanitized = WhatsAppSanitizer::sanitizeError($input, ['John Doe']);

        $this->assertStringContainsString('[PII_MASKED]', $sanitized);
        $this->assertStringNotContainsString('John Doe', $sanitized);
    }

    #[Test]
    public function it_masks_short_phone_numbers(): void
    {
        $shortPhone = '+1234567';
        $input = "Failed to send to {$shortPhone}.";
        $sanitized = WhatsAppSanitizer::sanitizeError($input);

        $this->assertStringContainsString('[PHONE_MASKED]', $sanitized);
        $this->assertStringNotContainsString($shortPhone, $sanitized);
    }

    #[Test]
    public function it_masks_twilio_message_sids(): void
    {
        $messageSid = 'SM1234567890abcdef1234567890abcdef';
        $mediaSid = 'MM1234567890abcdef1234567890abcdef';
        $input = "Sent Twilio message SID: {$messageSid} and media SID: {$mediaSid}.";
        $sanitized = WhatsAppSanitizer::sanitizeError($input);

        $this->assertStringContainsString('[SID_MASKED]', $sanitized);
        $this->assertStringNotContainsString($messageSid, $sanitized);
        $this->assertStringNotContainsString($mediaSid, $sanitized);
    }

    #[Test]
    public function it_replaces_fully_non_ascii_message_with_placeholder(): void
    {
        $input = '你好，世界！';
        $sanitized = WhatsAppSanitizer::sanitizeError($input);

        $this->assertSame('[NON_ASCII]', $sanitized);
    }

    #[Test]
    public function it_masks_phone_number_retaining_last_four_digits(): void
    {
        $phone = '+14155551234';
        $masked = WhatsAppSanitizer::maskPhoneNumber($phone);

        $this->assertSame('********1234', $masked);
        $this->assertSame('', WhatsAppSanitizer::maskPhoneNumber(''));
        $this->assertSame('****', WhatsAppSanitizer::maskPhoneNumber('1234'));
    }

    #[Test]
    public function it_does_not_mask_long_regular_words(): void
    {
        $input = 'An uncharacteristic misunderstanding regarding authentication';
        $sanitized = WhatsAppSanitizer::sanitizeError($input);

        $this->assertStringNotContainsString('[TOKEN_MASKED]', $sanitized);
        $this->assertStringContainsString('uncharacteristic', $sanitized);
    }

    #[Test]
    public function it_masks_bare_e164_phone_numbers(): void
    {
        $input = 'Failed delivering to 15551234567 and 442079460000.';
        $sanitized = WhatsAppSanitizer::sanitizeError($input);

        $this->assertStringContainsString('[PHONE_MASKED]', $sanitized);
        $this->assertStringNotContainsString('15551234567', $sanitized);
        $this->assertStringNotContainsString('442079460000', $sanitized);
    }

    #[Test]
    public function it_masks_twenty_to_thirty_one_digit_runs(): void
    {
        $input = 'Account reference: 1234567890123456789012345.';
        $sanitized = WhatsAppSanitizer::sanitizeError($input);

        $this->assertStringContainsString('[PII_MASKED]', $sanitized);
        $this->assertStringNotContainsString('1234567890123456789012345', $sanitized);
    }

    #[Test]
    public function it_masks_bare_seven_to_ten_digit_numbers(): void
    {
        $input = 'Failed sending to 5551234567 and 9876543.';
        $sanitized = WhatsAppSanitizer::sanitizeError($input);

        $this->assertStringContainsString('[PHONE_MASKED]', $sanitized);
        $this->assertStringNotContainsString('5551234567', $sanitized);
        $this->assertStringNotContainsString('9876543', $sanitized);
    }

    #[Test]
    public function it_masks_credit_card_as_pii(): void
    {
        $input = 'Card number: 4111222233334444.';
        $sanitized = WhatsAppSanitizer::sanitizeError($input);

        $this->assertStringContainsString('[PII_MASKED]', $sanitized);
        $this->assertStringNotContainsString('[PHONE_MASKED]', $sanitized);
        $this->assertStringNotContainsString('4111222233334444', $sanitized);
    }

    #[Test]
    public function it_masks_media_urls(): void
    {
        $mediaUrl1 = 'https://example.com/storage/members/123/avatar.jpg';
        $mediaUrl2 = 'https://s3.amazonaws.com/gym-media/receipt_9876.pdf?download=true';
        $input = "Failed uploading media: {$mediaUrl1} and {$mediaUrl2}.";
        $sanitized = WhatsAppSanitizer::sanitizeError($input);

        $this->assertStringContainsString('[URL_MASKED]', $sanitized);
        $this->assertStringNotContainsString($mediaUrl1, $sanitized);
        $this->assertStringNotContainsString($mediaUrl2, $sanitized);
    }

    #[Test]
    public function it_masks_signed_urls(): void
    {
        $signedUrl = 'https://storage.googleapis.com/gym-app/report.csv?signature=abcdef123456&expires=1700000000';
        $input = "Error processing signed resource: {$signedUrl}";
        $sanitized = WhatsAppSanitizer::sanitizeError($input);

        $this->assertStringContainsString('[URL_MASKED]', $sanitized);
        $this->assertStringNotContainsString('signature=abcdef123456', $sanitized);
    }

    #[Test]
    public function it_masks_custom_strings_with_punctuation(): void
    {
        $input = "Failed delivering to member Mary-Jane O'Connor for custom variable promo-2026.";
        $sanitized = WhatsAppSanitizer::sanitizeError($input, ["Mary-Jane O'Connor", 'promo-2026']);

        $this->assertStringContainsString('[PII_MASKED]', $sanitized);
        $this->assertStringNotContainsString("Mary-Jane O'Connor", $sanitized);
        $this->assertStringNotContainsString('promo-2026', $sanitized);
    }
}
