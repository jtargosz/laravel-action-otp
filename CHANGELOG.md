# Changelog

## 1.0.0 - 2026-09-25

- First release for Laravel 13 and PHP 8.3
- Action based codes with CodeManager, CacheCodeVault and SecureCodeGenerator
- Mail notification with custom notification support, SMS example in docs
- Throttle on wrong attempts, send cooldown, code expiry, events
- Atomic verify, sends and clear serialized per identifier, codes compared with hash_equals
- Malformed cache records fail closed
- Artisan command make:otp-action
- Validation rule ValidOtpCode
- Translations: en, pl, it, es, de, fr, pt, nl
- Optional AI tools for laravel/ai
