{{--
    Read-only detail shown when a comment's content cell is clicked.

    Styles are hand-written rather than Tailwind utilities on purpose: Filament
    v5 ships its own compiled stylesheet under the `fi-*` namespace and is not
    rebuilt from this project's views, so Tailwind classes used here would never
    make it into the bundle. The block therefore carries its own CSS.

    Colours are scoped to `.comment-detail` and re-declared under `.dark`, so the
    panel's light/dark toggle keeps working without depending on Filament's
    internal custom properties. The amber accent mirrors the panel primary.
--}}
<style>
    .comment-detail {
        --cd-border: #e5e7eb;
        --cd-surface: #f9fafb;
        --cd-muted: #6b7280;
        --cd-text: #111827;
        --cd-accent: #f59e0b;
        --cd-danger-bg: #fef2f2;
        --cd-danger-border: #fecaca;
        --cd-danger-text: #b91c1c;
    }

    .dark .comment-detail {
        --cd-border: #374151;
        --cd-surface: #111827;
        --cd-muted: #9ca3af;
        --cd-text: #f9fafb;
        --cd-accent: #fbbf24;
        --cd-danger-bg: #450a0a;
        --cd-danger-border: #7f1d1d;
        --cd-danger-text: #fca5a5;
    }

    .comment-detail__meta {
        display: grid;
        gap: 0.875rem 1.5rem;
        grid-template-columns: repeat(auto-fit, minmax(9rem, 1fr));
        margin: 0;
        padding: 0.875rem 1rem;
        background-color: var(--cd-surface);
        border: 1px solid var(--cd-border);
        border-radius: 0.5rem;
    }

    .comment-detail__meta-item {
        min-width: 0;
    }

    .comment-detail__label {
        margin: 0 0 0.125rem;
        font-size: 0.6875rem;
        font-weight: 600;
        letter-spacing: 0.06em;
        text-transform: uppercase;
        color: var(--cd-muted);
    }

    .comment-detail__value {
        margin: 0;
        font-size: 0.875rem;
        font-weight: 600;
        color: var(--cd-text);
        overflow-wrap: anywhere;
    }

    .comment-detail__content {
        margin: 1rem 0 0;
        padding: 0.75rem 0 0.75rem 0.875rem;
        border-left: 3px solid var(--cd-accent);
        font-size: 0.875rem;
        line-height: 1.7;
        color: var(--cd-text);
        white-space: pre-line;
        overflow-wrap: anywhere;
    }

    .comment-detail__badge {
        display: inline-flex;
        align-items: center;
        gap: 0.375rem;
        margin-top: 1rem;
        padding: 0.25rem 0.625rem;
        font-size: 0.75rem;
        font-weight: 600;
        color: var(--cd-danger-text);
        background-color: var(--cd-danger-bg);
        border: 1px solid var(--cd-danger-border);
        border-radius: 9999px;
    }

    .comment-detail__badge::before {
        content: "";
        width: 0.375rem;
        height: 0.375rem;
        border-radius: 9999px;
        background-color: currentColor;
    }
</style>

<div class="comment-detail">
    <dl class="comment-detail__meta">
        <div class="comment-detail__meta-item">
            <dt class="comment-detail__label">{{ __('comment.table.modal.plugin') }}</dt>
            <dd class="comment-detail__value">{{ $comment->plugin?->name ?? '—' }}</dd>
        </div>

        <div class="comment-detail__meta-item">
            <dt class="comment-detail__label">{{ __('comment.table.modal.author') }}</dt>
            <dd class="comment-detail__value">{{ $comment->author?->name ?? '—' }}</dd>
        </div>

        <div class="comment-detail__meta-item">
            <dt class="comment-detail__label">{{ __('comment.table.modal.published_at') }}</dt>
            <dd class="comment-detail__value">{{ $comment->created_at?->format('d/m/Y H:i') ?? '—' }}</dd>
        </div>
    </dl>

    <p class="comment-detail__content">{{ $comment->content }}</p>

    @if ($comment->hidden_at)
        <span class="comment-detail__badge">
            {{ __('comment.table.modal.hidden_at_value', ['date' => $comment->hidden_at->format('d/m/Y H:i')]) }}
        </span>
    @endif
</div>