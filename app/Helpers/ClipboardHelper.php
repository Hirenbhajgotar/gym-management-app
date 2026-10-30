<?php

declare(strict_types=1);

namespace App\Helpers;

class ClipboardHelper
{
    /**
     * Build one clipboard implementation for every Filament surface.
     *
     * Filament panels are commonly served over HTTP/LAN, where the async
     * Clipboard API is unavailable. The execCommand fallback is required, and
     * failure must be visible rather than claiming the value was copied.
     */
    public static function copyScript(
        string $value,
        string $successTitle = 'Copied to clipboard',
        string $failureTitle = 'Copy failed — select the text manually',
        ?string $successBody = null,
        ?string $failureBody = null
    ): string {
        $valueJson = self::json($value);
        $successTitleJson = self::json($successTitle);
        $failureTitleJson = self::json($failureTitle);
        $successBodyJson = self::json($successBody);
        $failureBodyJson = self::json($failureBody);

        return <<<JS
            (() => {
                const text = {$valueJson};
                const successTitle = {$successTitleJson};
                const failureTitle = {$failureTitleJson};
                const successBody = {$successBodyJson};
                const failureBody = {$failureBodyJson};
                const notify = (copied) => {
                    let notification = new FilamentNotification()
                        .title(copied ? successTitle : failureTitle);
                    const body = copied ? successBody : failureBody;

                    if (body) {
                        notification = notification.body(body);
                    }

                    (copied ? notification.success() : notification.danger()).send();
                };
                const fallback = () => {
                    const input = document.createElement('textarea');
                    input.value = text;
                    input.setAttribute('readonly', '');
                    input.style.position = 'fixed';
                    input.style.opacity = '0';
                    document.body.appendChild(input);
                    input.select();
                    let ok = false;
                    try { ok = document.execCommand('copy'); } catch (e) { ok = false; }
                    document.body.removeChild(input);
                    notify(ok);
                };

                if (navigator.clipboard && window.isSecureContext) {
                    navigator.clipboard.writeText(text).then(() => notify(true)).catch(fallback);
                } else {
                    fallback();
                }
            })();
            JS;
    }

    private static function json(?string $value): string
    {
        $encoded = json_encode(
            $value,
            JSON_HEX_TAG
                | JSON_HEX_AMP
                | JSON_HEX_APOS
                | JSON_HEX_QUOT
                | JSON_INVALID_UTF8_SUBSTITUTE
                | JSON_UNESCAPED_SLASHES
        );

        return is_string($encoded) ? $encoded : 'null';
    }
}
