# Changelog

## 1.1.0 - 2026-09-29

- Added `expired_grace_minutes` config (`ACTION_OTP_GRACE`, default 5): expired codes stay readable as `expired` instead of `empty`
- Fixed cache TTL computation to keep records through the grace period (min 60s)
- Unified identifier trimming in `CacheCodeVault::scope()`
- Documented action serializability (`SerializesModels` for Eloquent models) and added `SECURITY.md`
- Widened PHPUnit to `^11.0|^12.0|^13.0`, added PHPStan array shapes, CI checkout v5, Dependabot for composer and actions

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
