@component('mail::message')
# {{ __('mail.welcome.greeting', ['app' => config('app.name'), 'name' => $user->name]) }}

{{ __('mail.welcome.body') }}

@component('mail::button', ['url' => config('app.frontend_url')])
{{ __('mail.welcome.button') }}
@endcomponent

{{ __('mail.welcome.footer') }}

{{ __('mail.welcome.thanks') }}<br>
{{ __('mail.welcome.team', ['app' => config('app.name')]) }}
@endcomponent
