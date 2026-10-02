# Changelog

## 2.0.0 - Unreleased

Breaking changes, see [UPGRADE.md](UPGRADE.md):

- Each code is bound to the browser session or the challenge token returned by `send()`. A send from another browser can no longer replace a pending action
- `verify()` and `peek()` accept only string codes, `send()` needs an object notifiable
- Notifications receive an `OtpMessage` instead of the record array. Events carry no code and no action
- New `StoresCodes` contract (`put`, `get`, `forget` by identifier and challenge hash)
- AI tools `SendOtpTool` and `VerifyOtpTool` replaced by `ResendOtpTool` and `CheckOtpTool`, which take the identifier from the constructor
- Pending codes from 1.x are not read after the upgrade

Added:

- Purposes with `for()`: several pending actions per identifier
- `withChallenge()` for API clients, `OtpResult::$challenge`
- Per action attributes `#[Ttl]`, `#[CodeFormat]`, `#[CodeLength]`, `#[SendWith]` and `#[AnyDevice]`
- `ActionOtp::fake()` with `assertSent`, `assertSentTimes`, `assertNotSent`, `assertNothingSent`, `assertVerified`, `assertNotVerified`, `codeFor`, `challengeFor`, `messageFor`
- Translated markdown mail (publish tag `action-otp-views`), `OtpMessage::smsText()` with the `@host #code` autofill line
- Optional routes with `ActionOtp::routes()`: verify, resend, scanner-safe magic link, `otp.confirm` middleware with confirm pages. Replaceable `VerifyResponse`
- Status `device_mismatch`
- Config keys `sms_host`, `link.enabled`, `rate_limit`, `confirm_timeout`, `redirects.verified`

Fixed:

- A parallel send can no longer skip the send cooldown
- Letter codes are case-insensitive and all codes are trimmed
- `ValidOtpCode` fails array and number input and a missing identifier without an exception and without counting an attempt

## 1.2.0 - 2026-09-30

Security fixes:

- `CodeMail` keeps only `code` and `expires_at` and implements `ShouldBeEncrypted`, so the pending action (and anything in it) no longer lands in the queue payload or `failed_jobs`. Subclasses reading `$this->record['action']` or `['notifiable']` need to change
- `send()` and `resend()` no longer reset the wrong-attempt counter or lift the lockout, and return `throttled` while the identifier is locked. Previously anyone able to trigger a send could clear the lockout
- `peek()` now runs under the per-identifier lock and the attempt counter is incremented atomically, so parallel requests cannot bypass `max_attempts`
- `clear()` and expired or malformed records delete only the record. Attempt counters, lockout and send cooldown stay until a successful `verify()`, so a public cancel endpoint can no longer lift the limits. Custom `StoresCodes::flush()` implementations should delete only the record
- README and `llms.txt` examples hash passwords before building the action

Fixes:

- Package translations now load. Messages used keys outside the `action-otp::` namespace and came back as raw keys such as `action-otp.sent`

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
