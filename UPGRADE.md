# Upgrade guide

## 1.x to 2.0

Plan a short maintenance window: pending codes from 1.x use a different cache layout and are not read by 2.0. Users with a code in flight request a new one.

### Verification needs the session or the challenge token

In 1.x anyone who knew an identifier could send a new action for it, replacing the pending one. In 2.0 each code is bound to the browser session or the challenge token that `send()` returned.

- Browser apps: send and verify from routes with the `web` middleware. Nothing else to change.
- API clients: return `$result->challenge` from the send endpoint and pass it back:

```php
// before
ActionOtp::to($email)->verify($code);

// after, API
ActionOtp::to($email)->withChallenge($request->input('challenge'))->verify($code);
```

- Flows where the code must work on another device (for example a link opened on the phone): add `#[AnyDevice]` to the action, only for low risk actions. See the README warning.

Without a session or token, `verify()`, `peek()`, `resend()` and `clear()` find nothing and return `empty`.

### Codes are strings

`verify()` and `peek()` accept only `string`. Integer codes lost leading zeros in 1.x.

```php
// before
ActionOtp::to($email)->verify($request->integer('code'));

// after
ActionOtp::to($email)->verify($request->string('code')->toString());
```

### One pending action per session and purpose

A new `send()` replaces only the pending code of the same session (or token) and purpose. Use `for()` when one identifier needs several flows at once:

```php
ActionOtp::to($email)->for('password-reset')->send($action, $user);
ActionOtp::to($email)->for('password-reset')->verify($code);
```

Calls without `for()` use the purpose `default`, as before.

### Notifications receive an OtpMessage

Custom notifications got the full record array in 1.x. They now get `Jtargosz\ActionOtp\Support\OtpMessage` with `code`, `expiresAt`, `purpose` and `link`. The action and the notifiable are no longer passed.

```php
// before
public function __construct(protected array $record) {}
// $this->record['code'], $this->record['expires_at']

// after
public function __construct(public OtpMessage $otp) {}
// $this->otp->code, $this->otp->expiresAt, $this->otp->smsText()
```

The default `CodeMail` is now a translated markdown mail. If you extended it, publish the view with `--tag=action-otp-views` instead.

### Events carry no code and no action

| Event | 1.x | 2.0 |
| --- | --- | --- |
| `CodeSent` | `identifier`, `record` | `identifier`, `purpose`, `expiresAt` |
| `CodeVerified` | `identifier`, `payload` | `identifier`, `purpose`, `payload` |
| `CodeFailed` | `identifier` | `identifier`, `purpose` |

### send() needs an object notifiable

`send(VerifiableAction $action, object $notifiable)`. It still has to have a `notify()` method.

### New StoresCodes contract

Only relevant if you bound your own vault. `scope()` and `flush()` are gone:

```php
public function put(string $identifierHash, string $challengeHash, array $record): void;
public function get(string $identifierHash, string $challengeHash): ?array;
public function forget(string $identifierHash, string $challengeHash): void;
```

`forget()` deletes only the record. The record shape is documented as `OtpRecord` on the interface.

### AI tools renamed

`SendOtpTool` and `VerifyOtpTool` are removed. Their replacements take the identifier from your code instead of the model:

```php
// before
new SendOtpTool, new VerifyOtpTool

// after
new ResendOtpTool($user->email, 'login'), new CheckOtpTool($user->email, 'login')
```

### ValidOtpCode

The signature is `new ValidOtpCode($identifier, $purpose = 'default', $challenge = null)`. Pass the purpose if you use `for()`.

### Tests

Reading the code from the vault no longer works the same way. Use the fake:

```php
ActionOtp::fake('123456');
// ...
ActionOtp::assertSent(RegisterUserAction::class);
$code = ActionOtp::codeFor('ann@example.com', 'register');
```

### New dependencies

`illuminate/http`, `illuminate/mail`, `illuminate/routing`, `illuminate/session`, `illuminate/translation`, `illuminate/validation` and `illuminate/view` are now required. A full Laravel app already has them.
