<?php

namespace App\Events\Concerns;

use App\Models\User;
use App\Support\RequestContext;
use Illuminate\Support\Str;

/**
 * Shared request metadata carried by every auth event.
 *
 * The using event declares $user / $requestContext and captures them in its own
 * constructor, while the HTTP request that triggered the event is still bound.
 * The trait only reads that stored snapshot; it must never resolve
 * app(Request::class) itself. Auth listeners are queued (see LogAuthActivity),
 * so context() runs in a queue worker where the bound request is the console
 * request and would report 127.0.0.1 / "Symfony" instead of the client that
 * performed the action.
 *
 * @property-read User $user
 * @property-read RequestContext $requestContext
 * @property-read array{user_id: mixed, email: string} $snapshot
 */
trait InteractsWithRequestContext
{
    /**
     * Identity of the account, read once at construction time.
     *
     * SerializesModels reduces the user in the queue payload to an identifier
     * and re-queries it inside the worker, so anything read from
     * `$this->user` there describes the row as it is when the job runs, not
     * when it was dispatched. An audit entry written on a later attempt (the
     * retry backoff is 10s/60s) would then show a newer email address than the
     * one the account actually signed in with. Freezing the fields the log
     * needs keeps the entry a record of the event instead of a snapshot of
     * the present.
     *
     * The email is masked before it is stored, so the queue payload holds no
     * raw address, and keeping the models on the event keeps
     * SerializesModels in charge of the payload, which never carries the
     * password hash.
     *
     * @var array{user_id: mixed, email: string}
     */
    public readonly array $snapshot;

    /**
     * IP address of the originating request, null for non-HTTP callers.
     *
     * Exposed on its own so a failure report can include the origin without
     * building the whole event context first.
     */
    public function ipAddress(): ?string
    {
        return $this->requestContext->ipAddress;
    }

    /**
     * User agent of the originating request, null for non-HTTP callers.
     */
    public function userAgent(): ?string
    {
        return $this->requestContext->userAgent;
    }

    /**
     * Key of the account the event is about, taken from the dispatch-time
     * snapshot.
     *
     * A listener reporting a failure must not reach for the model: the row may
     * be gone by then, and resolving it would throw on the failure path and
     * swallow the very report it was asked to write.
     */
    public function userId(): string|int|null
    {
        return $this->snapshot['user_id'];
    }

    /**
     * Freeze the identity fields of the account at construction time.
     *
     * Called from each event's constructor, while the model still holds the
     * values that belong to the moment the event describes.
     */
    protected function captureSnapshot(User $user): void
    {
        $this->snapshot = [
            'user_id' => $user->getKey(),
            // PII: the audit entry only has to let a human recognise the
            // account. The user id already identifies it, and a raw address
            // in a log file is just something to harvest.
            'email' => Str::mask((string) $user->email, '*', 3),
        ];
    }

    /**
     * Keys shared by every auth event payload.
     *
     * The concrete event merges its own attributes on top:
     *
     *     return [...$this->baseContext(), 'token_id' => $this->tokenId];
     *
     * Keeping the block here makes a new shared field (e.g. request_id) a
     * one-line change instead of an edit in every auth event.
     *
     * @return array<string, mixed>
     */
    public function baseContext(): array
    {
        return [
            ...$this->snapshot,
            'ip_address' => $this->ipAddress(),
            'user_agent' => $this->userAgent(),
        ];
    }
}
