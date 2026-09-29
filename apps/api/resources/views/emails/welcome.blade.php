<x-mail::message>
    # {{ __('mail.welcome.greeting', ['app' => config('app.name'), 'name' => $user->name]) }}

    {{ __('mail.welcome.body') }}

    <x-mail::button :url="config('app.frontend_url')">
        {{ __('mail.welcome.button') }}
    </x-mail::button>

    {{ __('mail.welcome.footer') }}

    {{ __('mail.welcome.thanks') }}
    {{ __('mail.welcome.team', ['app' => config('app.name')]) }}
</x-mail::message>
