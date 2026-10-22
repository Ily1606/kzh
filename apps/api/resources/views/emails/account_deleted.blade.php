<x-mail::message>
# {{ __('mail.account_deleted.greeting', ['name' => $user->name]) }}

{{ __('mail.account_deleted.body', ['app' => config('app.name')]) }}

{{ __('mail.account_deleted.info') }}

{{ __('mail.account_deleted.thanks') }}<br>
{{ __('mail.account_deleted.team', ['app' => config('app.name')]) }}
</x-mail::message>

