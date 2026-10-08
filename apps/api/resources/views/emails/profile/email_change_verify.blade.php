<x-mail::message>
# Verify Your New Email

You recently requested to change the email address for your DSH account.

Please click the button below to verify this new email address.

<x-mail::button :url="$url">
Verify Email
</x-mail::button>

If you did not request this change, you can safely ignore this email.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
