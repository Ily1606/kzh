<x-mail::message>
# {{ __('mail.account_locked.greeting', ['name' => $user->name]) }}

{{ __('mail.account_locked.body', ['app' => config('app.name')]) }}

{{ __('mail.account_locked.info') }}

{{ __('mail.account_locked.thanks') }}<br>
{{ __('mail.account_locked.team', ['app' => config('app.name')]) }}
</x-mail::message>

