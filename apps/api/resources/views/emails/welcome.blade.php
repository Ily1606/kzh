<x-mail::message>
# {{ __('api.welcome_mail_greeting', ['app' => config('app.name'), 'name' => $user->name]) }}

{{ __('api.welcome_mail_body') }}

<x-mail::button :url="config('app.frontend_url')">
{{ __('api.welcome_mail_button') }}
</x-mail::button>

{{ __('api.welcome_mail_footer') }}

Thanks,<br>
{{ config('app.name') }} Team
</x-mail::message>
