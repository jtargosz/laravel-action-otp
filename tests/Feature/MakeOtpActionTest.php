<?php

namespace Jtargosz\ActionOtp\Tests\Feature;

use Jtargosz\ActionOtp\Tests\TestCase;

class MakeOtpActionTest extends TestCase
{
    public function test_make_command_generates_action(): void
    {
        $path = app_path('OtpActions/InviteUserAction.php');

        if (file_exists($path)) {
            unlink($path);
        }

        try {
            $this->artisan('make:otp-action', ['name' => 'InviteUserAction'])
                ->assertSuccessful();

            $this->assertFileExists($path);

            $contents = (string) file_get_contents($path);
            $this->assertStringContainsString('namespace App\OtpActions;', $contents);
            $this->assertStringContainsString('class InviteUserAction implements VerifiableAction', $contents);
        } finally {
            if (file_exists($path)) {
                unlink($path);
            }

            $dir = dirname($path);

            if (is_dir($dir) && count(scandir($dir)) === 2) {
                rmdir($dir);
            }
        }
    }
}
