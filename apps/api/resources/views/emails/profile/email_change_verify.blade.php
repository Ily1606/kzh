<x-mail::message>
# {{ __('mail.email_change_verify.greeting') }}

{{ __('mail.email_change_verify.body', ['app' => config('app.name')]) }}

{{ __('mail.email_change_verify.action') }}

<x-mail::button :url="$url">
{{ __('mail.email_change_verify.button') }}
</x-mail::button>

{{ __('mail.email_change_verify.footer') }}

{{ __('mail.email_change_verify.thanks') }}<br>
{{ config('app.name') }}
</x-mail::message>
