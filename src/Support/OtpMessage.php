<?php

namespace Jtargosz\ActionOtp\Support;

use DateTimeInterface;

/**
 * What a notification gets to deliver. It never contains the pending action
 * or the notifiable, so it is safe to queue and log.
 */
final class OtpMessage
{
    public function __construct(
        public readonly string $code,
        public readonly DateTimeInterface $expiresAt,
        public readonly string $purpose = 'default',
        public readonly ?string $link = null,
    ) {}

    /**
     * SMS body in the origin-bound one-time code format: iOS and Android
     * offer the code for autofill on the given host only.
     *
     * The last line is `@host #code`. Without a host only the text is returned.
     */
    public function smsText(?string $host = null): string
    {
        $text = (string) __('action-otp::action-otp.sms', [
            'code' => $this->code,
            'app' => (string) config('app.name', 'Laravel'),
        ]);

        $host ??= self::defaultHost();

        if ($host === null || $host === '') {
            return $text;
        }

        return $text."\n\n@".$host.' #'.$this->code;
    }

    private static function defaultHost(): ?string
    {
        $configured = config('action-otp.sms_host');

        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        $host = parse_url((string) config('app.url', ''), PHP_URL_HOST);

        return is_string($host) && $host !== '' ? $host : null;
    }
}
