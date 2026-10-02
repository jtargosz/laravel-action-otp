<?php

namespace Jtargosz\ActionOtp\Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Jtargosz\ActionOtp\Contracts\VerifiableAction;
use Jtargosz\ActionOtp\Facades\ActionOtp;
use Jtargosz\ActionOtp\Tests\TestCase;

/**
 * Quick start stand-in: returns the attributes instead of User::create(),
 * the test app has no database.
 */
class RegisterUserAction implements VerifiableAction
{
    public function __construct(
        public string $name,
        public string $email,
        public string $passwordHash,
    ) {}

    public function handle(): mixed
    {
        return ['name' => $this->name, 'email' => $this->email, 'hashed' => Hash::check('secret123', $this->passwordHash)];
    }
}

/**
 * The Quick start routes and the Testing example from README.md, copied as
 * close to verbatim as the test app allows (no unique:users rule).
 */
class ReadmeTest extends TestCase
{
    protected function defineRoutes($router): void
    {
        $router->middleware('web')->group(function ($router) {
            $router->post('/register', function (Request $request) {
                $data = $request->validate([
                    'name' => ['required', 'string', 'max:255'],
                    'email' => ['required', 'email'],
                    'password' => ['required', 'string', 'min:8'],
                ]);

                $email = strtolower($data['email']);

                ActionOtp::to($email)->for('register')->send(
                    new RegisterUserAction($data['name'], $email, Hash::make($data['password'])),
                    Notification::route('mail', $email)
                );

                return response()->json(['message' => 'Code sent.']);
            })->middleware('throttle:5,1');

            $router->post('/register/verify', function (Request $request) {
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
        });
    }

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

    public function test_quick_start_with_real_notifications(): void
    {
        Notification::fake();
        $this->fixCode('482913');

        $this->postJson('/register', ['name' => 'Ann', 'email' => 'Ann@Example.com', 'password' => 'secret123'])
            ->assertOk()
            ->assertExactJson(['message' => 'Code sent.']);

        $this->assertSame('482913', $this->lastMessage()->code);

        $this->postJson('/register/verify', ['email' => 'ann@example.com', 'code' => '000000'])
            ->assertStatus(422)
            ->assertExactJson(['message' => 'Code does not match.']);

        $this->postJson('/register/verify', ['email' => 'ann@example.com', 'code' => '482913'])
            ->assertOk()
            ->assertExactJson(['user' => ['name' => 'Ann', 'email' => 'ann@example.com', 'hashed' => true]]);
    }
}
