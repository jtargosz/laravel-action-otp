<x-mail::message>
# {{ __('action-otp::action-otp.mail.heading') }}

{{ __('action-otp::action-otp.mail.intro') }}

<x-mail::panel>
**{{ $code }}**
</x-mail::panel>

{{ __('action-otp::action-otp.mail.expires', ['time' => $expires]) }}

@if ($link)
<x-mail::button :url="$link">
{{ __('action-otp::action-otp.mail.button') }}
</x-mail::button>
@endif

{{ __('action-otp::action-otp.mail.ignore') }}
</x-mail::message>
