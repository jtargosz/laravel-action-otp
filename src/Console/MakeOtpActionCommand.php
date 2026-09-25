<?php

namespace Jtargosz\ActionOtp\Console;

use Illuminate\Console\GeneratorCommand;
use Symfony\Component\Console\Input\InputOption;

class MakeOtpActionCommand extends GeneratorCommand
{
    protected $name = 'make:otp-action';

    protected $description = 'Create a new OTP action class';

    protected $type = 'OtpAction';

    protected function getDefaultNamespace($rootNamespace): string
    {
        return $rootNamespace.'\\OtpActions';
    }

    protected function getStub(): string
    {
        return __DIR__.'/../../stubs/otp-action.stub';
    }

    protected function getOptions(): array
    {
        return [
            ['force', null, InputOption::VALUE_NONE, 'Create the class even if it exists'],
        ];
    }
}
