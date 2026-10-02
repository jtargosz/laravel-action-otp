# Laravel Action OTP

[![Tests](https://github.com/jtargosz/laravel-action-otp/actions/workflows/tests.yml/badge.svg)](https://github.com/jtargosz/laravel-action-otp/actions)
[![Latest version](https://img.shields.io/packagist/v/jtargosz/laravel-action-otp.svg)](https://packagist.org/packages/jtargosz/laravel-action-otp)
[![Downloads](https://img.shields.io/packagist/dt/jtargosz/laravel-action-otp.svg)](https://packagist.org/packages/jtargosz/laravel-action-otp)
[![PHP version](https://img.shields.io/packagist/php-v/jtargosz/laravel-action-otp.svg)](https://packagist.org/packages/jtargosz/laravel-action-otp)
[![Laravel 13](https://img.shields.io/badge/laravel-13-FF2D20.svg)](https://laravel.com/docs/13.x)
[![License: MIT](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

**Confirm any action with a one-time code.** You describe the work in an action class, send a code by mail or SMS, and the action runs exactly once, only after the right code comes back.

> Building with an AI assistant? The full API in plain text lives in [`llms.txt`](llms.txt). Point your agent at that file first.

Typical uses: registration without half-created users, password reset, email or phone change, login 2FA, "type the code to delete your account".

```php
// Send: the action waits in cache, bound to this browser session
ActionOtp::to($email)->for('password-reset')->send(new ResetPasswordAction($email, Hash::make($password)), $user);

// Verify: runs handle() once and gives you its return value
$result = ActionOtp::to($email)->for('password-reset')->verify($code);
```

## Contents

- [Why this package](#why-this-package)
- [Requirements](#requirements)
- [Install](#install)
- [Quick start](#quick-start)
- [API](#api)
- [Browsers, API clients and purposes](#browsers-api-clients-and-purposes)
- [Per action settings](#per-action-settings)
- [Examples](#examples)
- [Routes, magic link and otp.confirm](#routes-magic-link-and-otpconfirm)
- [Notifications and SMS](#notifications-and-sms)
- [Config](#config)
- [Security](#security)
- [Testing](#testing)
- [Use with Laravel AI SDK](#use-with-laravel-ai-sdk)
- [Translations](#translations)
- [Upgrading from 1.x](#upgrading-from-1x)
- [Contributing](#contributing)
- [License](#license)

## Why this package

- The code is tied to an action class. `handle()` runs exactly once, only after a correct code. Nothing is created or changed before that.
- Each code is bound to the browser session (or API token) that asked for it. Someone who knows your email cannot replace your pending action or confirm theirs with your code.
- Several pending actions per identifier: a password reset does not cancel a pending registration.
- No database tables. Codes live in cache and clean up after themselves.
- Throttling, lockout, send cooldown and expiry built in.
- Per action settings with attributes: lifetime, code format, notification class.
- Translated mail in 8 languages, SMS text with iOS and Android autofill.
- `ActionOtp::fake()` for tests, optional routes, scanner-safe magic link and an `otp.confirm` middleware.

How it compares, honestly:

| | This package | [spatie/laravel-one-time-passwords](https://github.com/spatie/laravel-one-time-passwords) | [benbjurstrom/otpz](https://github.com/benbjurstrom/otpz) | [tzsk/otp](https://github.com/tzsk/otp) |
| --- | --- | --- | --- | --- |
| Built for | Confirming any action | Passwordless login | Passwordless login | Generating and checking codes |
| What runs after the code | Your action class | Login | Login | Nothing, you decide |
| Storage | Cache | Database | Database | Cache |
| Bound to | Session or challenge token | IP and user agent | Session | Nothing |
| UI | None, Blade examples below | Livewire component | Starter kit pages | None |

If you want a ready-made passwordless login screen, spatie or otpz fit better. If you need "do X only after the user proves they own this email or phone", this package is the shorter path.

This is not TOTP (authenticator apps) and not a replacement for passkeys.

## Requirements

- PHP 8.3, 8.4 or 8.5
- Laravel 13
- A persistent cache driver (file, database, redis, memcached). The array driver loses codes between requests, the null driver disables the package. The database driver needs the `cache_locks` table next to the `cache` table.
- Sessions for browser flows. API clients use the challenge token instead.

## Install

```bash
composer require jtargosz/laravel-action-otp
```

Publish the config (optional):

```bash
php artisan vendor:publish --tag=action-otp-config
```

## Quick start

Generate an action:

```bash
php artisan make:otp-action RegisterUserAction
```

Fill it in. The action waits in cache until the code is verified, so hash the password before you build it. Never keep a plain password in an action.

```php
<?php

namespace App\OtpActions;

use App\Models\User;
use Jtargosz\ActionOtp\Contracts\VerifiableAction;

class RegisterUserAction implements VerifiableAction
{
    public function __construct(
        public string $name,
        public string $email,
        public string $passwordHash,
    ) {
    }

    public function handle(): mixed
    {
        return User::create([
            'name' => $this->name,
            'email' => $this->email,
            'password' => $this->passwordHash,
        ]);
    }
}
```

Send the code. These routes live in `routes/web.php`, so the code is bound to the visitor's session:

```php
use App\OtpActions\RegisterUserAction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Jtargosz\ActionOtp\Facades\ActionOtp;

Route::post('/register', function (Request $request) {
    $data = $request->validate([
        'name' => ['required', 'string', 'max:255'],
        'email' => ['required', 'email', 'unique:users,email'],
        'password' => ['required', 'string', 'min:8'],
    ]);

    $email = strtolower($data['email']);

    ActionOtp::to($email)->for('register')->send(
        new RegisterUserAction($data['name'], $email, Hash::make($data['password'])),
        Notification::route('mail', $email)
    );

    return response()->json(['message' => 'Code sent.']);
})->middleware('throttle:5,1');
```

Verify the code. `payload` holds whatever `handle()` returned:

```php
Route::post('/register/verify', function (Request $request) {
    $data = $request->validate([
        'email' => ['required', 'email'],
        'code' => ['required', 'string'],
    ]);

    $result = ActionOtp::to(strtolower($data['email']))->for('register')->verify($data['code']);

    if (! $result->ok()) {
        return response()->json(['message' => $result->message], 422);
    }

    return response()->json(['user' => $result->payload]);
})->middleware('throttle:10,1');
```

Keep a unique index on `users.email`. Request validation alone races under parallel registrations, the database is the final guard.

## API

Every call starts with `to()`, which scopes the code to one identifier (email, phone or user id). Identifiers are compared exactly, so normalize them first, for example `strtolower()` emails. `for()` and `withChallenge()` are optional.

| Call | What it does | Returns |
| --- | --- | --- |
| `to($identifier)` | Scopes to an identifier | the manager |
| `for($purpose)` | Scopes to a purpose, default `default` | the manager |
| `withChallenge($token)` | Uses the token from `send()` instead of the session (API clients) | the manager |
| `send($action, $notifiable)` | Stores the action, generates a code, sends the notification. Replaces only the pending code of the same session or token | `sent` + `challenge`, or `throttled` during cooldown or lockout |
| `verify($code)` | Checks the code, deletes it, runs `handle()` once | `verified` + `payload`, or `mismatch`, `empty`, `expired`, `throttled` |
| `peek($code)` | Checks the code without running `handle()` and without deleting it | `matched`, `mismatch`, `empty`, `expired`, `throttled` |
| `resend()` | New code (and link) for the pending action, same challenge | `sent`, `empty`, or `throttled` during cooldown or lockout |
| `clear()` | Deletes the pending code. Counters, lockout and cooldown stay | void |

Codes are strings. `012345` stays `012345`, whitespace is trimmed, and letter codes are case-insensitive.

The result object:

```php
$result->status;    // OtpStatus: sent, matched, verified, empty, mismatch, throttled, expired, device_mismatch
$result->message;   // translated string for the status
$result->payload;   // mixed, set only when verified
$result->challenge; // string, set only by send()
$result->ok();      // true when verified
$result->found();   // true when matched or verified
$result->toArray(); // status, message, payload, challenge
```

The code is consumed before `handle()` runs, so a throwing `handle()` does not leave a reusable code. The user resends and tries again.

## Browsers, API clients and purposes

Each `send()` creates a challenge, a random token that identifies this one pending action. The package looks it up in this order:

1. the token passed to `withChallenge()`,
2. the current browser session (set automatically by `send()`),
3. for `#[AnyDevice]` actions only, the last pending action of that identifier and purpose.

Without a challenge, `verify()` returns `empty` and the attempt does not count.

Browser flow: nothing to do. Send and verify from routes with the `web` middleware and the session carries the challenge.

API flow: return the challenge to the client and send it back when verifying:

```php
// POST /api/login/code
$result = ActionOtp::to($email)->for('login')->send(new ConfirmLoginAction($user->id), $user);

return ['challenge' => $result->challenge];

// POST /api/login/verify
$result = ActionOtp::to($email)->for('login')->withChallenge($request->input('challenge'))->verify($request->input('code'));
```

The challenge is a bearer secret for that one pending action, treat it like a short-lived token.

Purposes let one identifier have several pending actions. Each purpose has its own code, and a challenge from one purpose never works for another. Attempt counters, lockout and cooldown are shared by all purposes of an identifier.

```php
ActionOtp::to($email)->for('register')->send($register, $notifiable);
ActionOtp::to($email)->for('password-reset')->send($reset, $notifiable); // after the cooldown

ActionOtp::to($email)->for('register')->verify($code);
```

## Per action settings

Attributes on the action class override the config. They are inherited by subclasses.

```php
use Jtargosz\ActionOtp\Attributes\CodeFormat;
use Jtargosz\ActionOtp\Attributes\CodeLength;
use Jtargosz\ActionOtp\Attributes\SendWith;
use Jtargosz\ActionOtp\Attributes\Ttl;

#[Ttl(10)]
#[SendWith(\App\Notifications\CodeSms::class)]
class ResetPasswordAction implements VerifiableAction { /* ... */ }

#[Ttl(30)]
#[CodeFormat('alphanumeric')]
#[CodeLength(8)]
class ChangeEmailAction implements VerifiableAction { /* ... */ }
```

| Attribute | Overrides |
| --- | --- |
| `#[Ttl(minutes)]` | `ttl_minutes` |
| `#[CodeFormat('numeric' \| 'alpha' \| 'alphanumeric')]` | `code_format` |
| `#[CodeLength(1..64)]` | `code_length` |
| `#[SendWith(Notification::class)]` | `notification` |
| `#[AnyDevice]` | session binding, see below |

`#[AnyDevice]` lets the code and link work from any device, without the session or token. Use it only for low risk actions, such as confirming a newsletter address. Whoever triggers a send for an identifier replaces its pending `AnyDevice` action, so the owner could end up confirming an action someone else started. Never use it for password resets, email changes or anything that grants access.

## Examples

Login with 2FA. Send to the User model, log in after verify:

```php
use Illuminate\Support\Facades\Auth;
use Jtargosz\ActionOtp\Facades\ActionOtp;

ActionOtp::to($user->email)->for('login')->send(new ConfirmLoginAction($user->id), $user);

$result = ActionOtp::to($user->email)->for('login')->verify($code);

if ($result->ok()) {
    Auth::loginUsingId($result->payload);
}
```

Password reset. The password changes only after verify:

```php
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Jtargosz\ActionOtp\Contracts\VerifiableAction;

class ResetPasswordAction implements VerifiableAction
{
    public function __construct(public string $email, public string $passwordHash)
    {
    }

    public function handle(): mixed
    {
        $user = User::where('email', $this->email)->firstOrFail();
        $user->update(['password' => $this->passwordHash]);

        return $user;
    }
}

ActionOtp::to($email)->for('password-reset')->send(
    new ResetPasswordAction($email, Hash::make($password)),
    Notification::route('mail', $email)
);
```

Resend a fresh code when the message got lost. Honors the send cooldown:

```php
$result = ActionOtp::to($email)->for('password-reset')->resend();

if ($result->status !== OtpStatus::Sent) {
    return response()->json(['message' => $result->message], 429);
}
```

Check without consuming, for live validation of a single field:

```php
$result = ActionOtp::to($email)->for('register')->peek($code);

return match ($result->status) {
    OtpStatus::Matched => response()->json(['ok' => true]),
    OtpStatus::Throttled => response()->json(['message' => $result->message], 429),
    default => response()->json(['message' => $result->message], 422),
};
```

Cancel the flow:

```php
ActionOtp::to($email)->for('register')->clear();
```

Validate the code as a form field, without consuming it:

```php
use Jtargosz\ActionOtp\Validation\ValidOtpCode;

$request->validate([
    'email' => ['required', 'email'],
    'code' => ['required', 'string', new ValidOtpCode($request->input('email'), 'register')],
]);
```

The third argument takes a challenge for API clients. The rule uses `peek()`, so the code stays valid until you call `verify()`. A wrong code counts as an attempt, so a failed validation followed by a failed `verify()` burns two tries. Arrays, numbers and a missing identifier fail without counting.

The code field. `autocomplete="one-time-code"` lets browsers offer the code from SMS:

```blade
<label for="code">{{ __('Verification code') }}</label>
<input id="code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code"
       pattern="[0-9]*" maxlength="6" required aria-describedby="code-help">
<p id="code-help">{{ __('We sent it to your email.') }}</p>
@error('code') <p role="alert">{{ $message }}</p> @enderror
```

For `alpha` or `alphanumeric` codes drop `inputmode` and `pattern`.

## Routes, magic link and otp.confirm

Optional. Register the routes in `routes/web.php`:

```php
use Jtargosz\ActionOtp\Facades\ActionOtp;

ActionOtp::routes(); // prefix "otp", middleware ["web"]
// ActionOtp::routes('api/otp', ['api']); // for token clients
```

| Route | Name | Input |
| --- | --- | --- |
| `POST /otp/verify` | `action-otp.verify` | `identifier`, `code`, `purpose`, `challenge` |
| `POST /otp/resend` | `action-otp.resend` | `identifier`, `purpose`, `challenge` |
| `GET /otp/link` | `action-otp.link` | signed, shows the link view |
| `POST /otp/link` | `action-otp.link.store` | signed, verifies |
| `GET /otp/confirm` | `action-otp.confirm` | auth, sends a code once per session and shows the confirm view |
| `POST /otp/confirm` | `action-otp.confirm.store` | auth, `code` |
| `POST /otp/confirm/resend` | `action-otp.confirm.resend` | auth |

All routes are rate limited to `rate_limit` requests per minute per IP.

Responses: JSON requests get `{status, message}` with 200, 409 (`device_mismatch`), 422 or 429. The payload is never serialized. If `handle()` returns a `Responsable` or `Response`, it is sent as is. Browser requests redirect to the intended URL (fallback `redirects.verified`) with a `status` flash, or back with a `code` error. To change this, bind your own `Jtargosz\ActionOtp\Contracts\VerifyResponse` in a service provider.

### Magic link

Set `ACTION_OTP_LINK=true`, register the routes and a view. The default mail then shows a button next to the code.

```php
// AppServiceProvider::boot()
ActionOtp::linkView('auth.otp-link');
```

```blade
{{-- resources/views/auth/otp-link.blade.php --}}
<form method="POST" action="{{ $url }}">
    @csrf
    <p>{{ __('Confirm this request?') }}</p>
    <button type="submit">{{ __('Confirm') }}</button>
</form>
```

Opening the link only shows this page. Mail scanners that follow links consume nothing, the POST from the page does the work. The link works in the browser that started the flow. Anywhere else the user gets `device_mismatch` and types the code in the original browser instead, unless the action has `#[AnyDevice]`. A wrong link token counts as an attempt.

### otp.confirm

Like `password.confirm`, but with a code sent to the logged-in user:

```php
Route::get('/settings/security', SecurityController::class)->middleware(['auth', 'otp.confirm']);
Route::delete('/account', DeleteAccountController::class)->middleware(['auth', 'otp.confirm']);

// custom confirm route or timeout in seconds
use Jtargosz\ActionOtp\Http\Middleware\RequireOtpConfirmation;

Route::post('/billing', BillingController::class)->middleware(['auth', RequireOtpConfirmation::using(null, 300)]);
```

```php
// AppServiceProvider::boot()
ActionOtp::confirmView('auth.otp-confirm');
ActionOtp::confirmUsing(fn ($user) => $user->phone); // optional, default: email, then user id
```

```blade
{{-- resources/views/auth/otp-confirm.blade.php --}}
<form method="POST" action="{{ $submitUrl }}">
    @csrf
    <label for="code">{{ __('Code we just sent you') }}</label>
    <input id="code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code" required>
    @error('code') <p role="alert">{{ $message }}</p> @enderror
    <button type="submit">{{ __('Confirm') }}</button>
</form>
<form method="POST" action="{{ $resendUrl }}">
    @csrf
    <button type="submit">{{ __('Send a new code') }}</button>
</form>
```

Unconfirmed JSON requests get 423, browser requests are redirected to `/otp/confirm`. After a correct code the confirmation lasts `confirm_timeout` seconds (default 900). Only GET requests are remembered as the intended URL, same as Laravel's `password.confirm`.

## Notifications and SMS

The default notification is `Jtargosz\ActionOtp\Mail\CodeMail`: a translated markdown mail, queued and encrypted on the queue. It gets only an `OtpMessage` (code, expiry, purpose, link), never the action. Publish the view to restyle it:

```bash
php artisan vendor:publish --tag=action-otp-views
```

Your own notification receives the same `OtpMessage`:

```php
use Jtargosz\ActionOtp\Support\OtpMessage;

public function __construct(public OtpMessage $otp)
{
}
```

Set it globally with the `notification` config or per action with `#[SendWith]`. Notifications are sent in the current app locale unless the notifiable implements `HasLocalePreference`.

`smsText()` builds an SMS in the origin-bound one-time code format, so iOS and Android offer the code for autofill on your site only. The last line is `@host #code`. The host comes from `sms_host`, then `app.url`.

```text
482913 is your Acme verification code.

@acme.com #482913
```

<details>
<summary>SMS example with Vonage</summary>

```php
<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\VonageMessage;
use Illuminate\Notifications\Notification;
use Jtargosz\ActionOtp\Support\OtpMessage;

class CodeSms extends Notification
{
    public function __construct(public OtpMessage $otp)
    {
    }

    public function via(object $notifiable): array
    {
        return ['vonage'];
    }

    public function toVonage(object $notifiable): VonageMessage
    {
        return (new VonageMessage)->content($this->otp->smsText());
    }
}
```

</details>

Keep actions serializable. Most cache drivers serialize records, so avoid closures in action properties. If the action holds an Eloquent model, use the `SerializesModels` trait so `handle()` works on fresh data.

## Config

File `config/action-otp.php`:

| Key | Default | Meaning |
| --- | --- | --- |
| `code_format` | `numeric` | `numeric`, `alpha` or `alphanumeric` |
| `code_length` | `6` | Code length (1-64) |
| `ttl_minutes` | `15` | Code lifetime |
| `expired_grace_minutes` | `5` | How long an expired code stays readable as `expired` (0 disables) |
| `max_attempts` | `5` | Wrong tries before lock |
| `throttle_seconds` | `60` | Lock duration (0 disables the lock) |
| `send_cooldown` | `30` | Min seconds between sends per identifier (0 disables) |
| `store_prefix` | `action-otp:` | Cache key prefix |
| `notification` | `CodeMail::class` | Notification class |
| `channels` | `['mail']` | Channels of the default `CodeMail` |
| `sms_host` | `null` | Host for the SMS autofill line, falls back to `app.url` |
| `link.enabled` | `false` | Magic link in the default mail |
| `rate_limit` | `10` | Requests per minute per IP on the package routes |
| `confirm_timeout` | `900` | Seconds an `otp.confirm` confirmation lasts |
| `redirects.verified` | `/` | Fallback redirect after verify on the package routes |

Env keys: `ACTION_OTP_FORMAT`, `ACTION_OTP_LENGTH`, `ACTION_OTP_TTL`, `ACTION_OTP_GRACE`, `ACTION_OTP_ATTEMPTS`, `ACTION_OTP_THROTTLE`, `ACTION_OTP_COOLDOWN`, `ACTION_OTP_PREFIX`, `ACTION_OTP_SMS_HOST`, `ACTION_OTP_LINK`, `ACTION_OTP_RATE_LIMIT`, `ACTION_OTP_CONFIRM_TIMEOUT`.

## Security

- Each pending action is bound to the session or challenge token that created it. A send from another browser never replaces, reveals or unlocks it, so knowing someone's email is not enough to swap in your own action.
- Codes are compared with `hash_equals`. Identifiers, purposes and challenges appear in cache keys and links only as SHA-256 hashes.
- After `max_attempts` wrong tries the identifier is locked for `throttle_seconds`, across all purposes. Wrong tries count over a one hour window, across codes: `send()` and `resend()` never reset the count or lift the lock. Once the limit is reached, each further wrong try in that window locks again. Only a successful verify resets the counters, lockout and cooldown. `clear()` and expiry keep them, so a public "cancel" endpoint cannot lift the limits.
- Every operation on an identifier runs under one lock, so parallel requests cannot run `handle()` twice, slip past the attempt limit or skip the send cooldown.
- A missing or wrong challenge returns `empty` and does not count as an attempt. Wrong codes do count per identifier, whichever session they come from, and sends share one cooldown per identifier. So someone who can start a flow for an identifier can trigger its lockout or cooldown (not bypass it). Protect your send routes with Laravel rate limiting. The examples in this file ship with `throttle` middleware, keep it or tighten it.
- Statuses tell `empty` apart from `mismatch`. Within one session this shows whether a code is pending. If that matters, map both to one message.
- The pending action waits in cache until verified, including any data you pass to it. Treat the cache as trusted storage, keep `ttl_minutes` short and hash passwords before they go into an action.
- The default `CodeMail` never puts the action into the queue payload and is encrypted on the queue. Events carry no code and no action.
- The magic link GET page consumes nothing, only the POST does, and it is signed and expires with the code.
- `#[AnyDevice]` removes the session binding for that action. Read the warning in [Per action settings](#per-action-settings).
- Cache keys start with `store_prefix`. If several apps share one cache backend, set a unique `ACTION_OTP_PREFIX` per app.
- Events `CodeSent`, `CodeVerified` and `CodeFailed` are dispatched for logs and metrics.

## Testing

`ActionOtp::fake()` keeps storage, sessions and throttling real, but records sends instead of delivering notifications:

```php
use Jtargosz\ActionOtp\Facades\ActionOtp;

public function test_registration_requires_the_code(): void
{
    ActionOtp::fake('123456'); // omit the code to keep random codes

    $this->postJson('/register', ['name' => 'Ann', 'email' => 'ann@example.com', 'password' => 'secret123'])->assertOk();

    ActionOtp::assertSent(RegisterUserAction::class, fn ($action, $message, $identifier, $purpose) => $identifier === 'ann@example.com');

    $this->postJson('/register/verify', [
        'email' => 'ann@example.com',
        'code' => ActionOtp::codeFor('ann@example.com', 'register'),
    ])->assertOk();

    ActionOtp::assertVerified(RegisterUserAction::class);
}
```

Assertions: `assertSent`, `assertSentTimes`, `assertNotSent`, `assertNothingSent`, `assertVerified`, `assertNotVerified`. Helpers: `codeFor`, `challengeFor` (for API flows), `messageFor`. Use an array cache in tests.

## Use with Laravel AI SDK

The package suggests `laravel/ai` but does not require it. Two tools are included. The identifier, purpose and challenge come from your code, the model only supplies the code:

- `Jtargosz\ActionOtp\Ai\ResendOtpTool` resends the pending code
- `Jtargosz\ActionOtp\Ai\CheckOtpTool` checks a code with `peek()`, without running the action

```bash
composer require laravel/ai
```

```php
public function tools(): iterable
{
    return [
        new \Jtargosz\ActionOtp\Ai\ResendOtpTool($this->user->email, 'login'),
        new \Jtargosz\ActionOtp\Ai\CheckOtpTool($this->user->email, 'login'),
    ];
}
```

Expose these tools only to authenticated agents acting for that user.

## Translations

Ships with `en`, `pl`, `it`, `es`, `de`, `fr`, `pt` and `nl`: statuses, the mail and the SMS text. Messages follow the app locale. Publish with tag `action-otp-lang` and edit the files in `lang/vendor/action-otp/{locale}/action-otp.php`. To add a language, copy the `en` file to `lang/vendor/action-otp/{locale}/action-otp.php` with the same keys.

## Upgrading from 1.x

2.0 has breaking changes. See [UPGRADE.md](UPGRADE.md).

## Contributing

See CONTRIBUTING.md. Bug reports and small focused PRs are welcome.

## License

MIT. See LICENSE.
