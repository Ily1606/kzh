<x-mail::message>
# Security Alert: Email Change Requested

We noticed a request to change the email address associated with your DSH account to **{{ $newEmail }}**.

If you requested this change, no further action is required from this email address.

**If you did not request this change, your account may be compromised.**

Please secure your account immediately.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
