<x-mail::message>
# {{ __('mail.email_change_alert.greeting') }}

{{ __('mail.email_change_alert.body', ['app' => config('app.name'), 'email' => $newEmail]) }}

{{ __('mail.email_change_alert.action') }}

{{ __('mail.email_change_alert.warning') }}

{{ __('mail.email_change_alert.footer') }}

{{ __('mail.email_change_alert.thanks') }}<br>
{{ config('app.name') }}
</x-mail::message>
