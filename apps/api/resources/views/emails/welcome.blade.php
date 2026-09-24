<x-mail::message>
# Welcome to {{ config('app.name') }}, {{ $user->name }}!

Thank you for registering an account with us. We are excited to have you on board.

<x-mail::button :url="config('app.frontend_url', 'http://localhost:5173')">
Visit Your Dashboard
</x-mail::button>

If you have any questions, feel free to reply to this email.

Thanks,<br>
{{ config('app.name') }} Team
</x-mail::message>
