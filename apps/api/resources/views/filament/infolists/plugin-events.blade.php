{{--
    Read-only timeline shown in the Review History section of the plugin infolist.

    Styles are hand-written rather than Tailwind utilities on purpose: Filament
    v5 ships its own compiled stylesheet under the `fi-*` namespace and is not
    rebuilt from this project's views.

    Colours are scoped to `.plugin-timeline` and re-declared under `.dark`, so the
    panel's light/dark toggle keeps working without depending on Filament's
    internal custom properties.
--}}
<style>
    .plugin-timeline {
        --pt-border: #e5e7eb;
        --pt-surface: #f9fafb;
        --pt-surface-card: #ffffff;
        --pt-muted: #6b7280;
        --pt-text: #111827;
        --pt-text-secondary: #4b5563;
        --pt-accent: #f59e0b;
        --pt-success-bg: #ecfdf5;
        --pt-success-border: #a7f3d0;
        --pt-success-text: #065f46;
        --pt-danger-bg: #fef2f2;
        --pt-danger-border: #fecaca;
        --pt-danger-text: #991b1b;
        --pt-warning-bg: #fffbeb;
        --pt-warning-border: #fde68a;
        --pt-warning-text: #92400e;
        --pt-info-bg: #eff6ff;
        --pt-info-border: #bfdbfe;
        --pt-info-text: #1e40af;
    }

    .dark .plugin-timeline {
        --pt-border: #374151;
        --pt-surface: #111827;
        --pt-surface-card: #1f2937;
        --pt-muted: #9ca3af;
        --pt-text: #f9fafb;
        --pt-text-secondary: #d1d5db;
        --pt-accent: #fbbf24;
        --pt-success-bg: #064e3b;
        --pt-success-border: #047857;
        --pt-success-text: #6ee7b7;
        --pt-danger-bg: #450a0a;
        --pt-danger-border: #7f1d1d;
        --pt-danger-text: #fca5a5;
        --pt-warning-bg: #451a03;
        --pt-warning-border: #78350f;
        --pt-warning-text: #fde68a;
        --pt-info-bg: #1e3a8a;
        --pt-info-border: #1d4ed8;
        --pt-info-text: #93c5fd;
    }

    .plugin-timeline__list {
        display: flex;
        flex-direction: column;
        gap: 0.875rem;
        margin: 0;
        padding: 0;
        list-style: none;
    }

    .plugin-timeline__item {
        position: relative;
        padding: 0.875rem 1rem;
        background-color: var(--pt-surface);
        border: 1px solid var(--pt-border);
        border-radius: 0.5rem;
    }

    .plugin-timeline__header {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: 0.5rem;
        margin-bottom: 0.375rem;
    }

    .plugin-timeline__badge {
        display: inline-flex;
        align-items: center;
        gap: 0.375rem;
        padding: 0.2rem 0.55rem;
        font-size: 0.75rem;
        font-weight: 600;
        border-radius: 9999px;
        line-height: 1.25;
    }

    .plugin-timeline__badge--approved {
        background-color: var(--pt-success-bg);
        border: 1px solid var(--pt-success-border);
        color: var(--pt-success-text);
    }

    .plugin-timeline__badge--rejected {
        background-color: var(--pt-danger-bg);
        border: 1px solid var(--pt-danger-border);
        color: var(--pt-danger-text);
    }

    .plugin-timeline__badge--update_requested {
        background-color: var(--pt-warning-bg);
        border: 1px solid var(--pt-warning-border);
        color: var(--pt-warning-text);
    }

    .plugin-timeline__badge--created,
    .plugin-timeline__badge--resubmitted {
        background-color: var(--pt-info-bg);
        border: 1px solid var(--pt-info-border);
        color: var(--pt-info-text);
    }

    .plugin-timeline__date {
        font-size: 0.75rem;
        color: var(--pt-muted);
    }

    .plugin-timeline__actor {
        font-size: 0.8125rem;
        color: var(--pt-text-secondary);
        margin: 0 0 0.375rem;
    }

    .plugin-timeline__actor strong {
        color: var(--pt-text);
    }

    .plugin-timeline__message {
        margin: 0.5rem 0 0;
        padding: 0.625rem 0.875rem;
        background-color: var(--pt-surface-card);
        border-left: 3px solid var(--pt-accent);
        border-radius: 0.25rem;
        font-size: 0.8125rem;
        line-height: 1.5;
        color: var(--pt-text);
        white-space: pre-wrap;
        word-break: break-word;
    }

    .plugin-timeline__empty {
        padding: 1.25rem;
        text-align: center;
        color: var(--pt-muted);
        font-size: 0.875rem;
        background-color: var(--pt-surface);
        border: 1px dashed var(--pt-border);
        border-radius: 0.5rem;
    }
</style>

@php
    $plugin = $getRecord();
    $events = $plugin ? $plugin->events()->with('admin')->get() : collect();
@endphp

<div class="plugin-timeline">
    @if ($events->isEmpty())
        <div class="plugin-timeline__empty">
            {{ __('plugin.infolist.values.no_events') }}
        </div>
    @else
        <ul class="plugin-timeline__list">
            @foreach ($events as $event)
                @php
                    $typeVal = $event->event_type instanceof \App\Enums\PluginEventType
                        ? $event->event_type->value
                        : (string) $event->event_type;
                    $badgeClass = 'plugin-timeline__badge--' . $typeVal;
                    $typeLabel = $event->event_type instanceof \App\Enums\PluginEventType
                        ? $event->event_type->label()
                        : ucfirst(str_replace('_', ' ', $typeVal));
                @endphp
                <li class="plugin-timeline__item">
                    <div class="plugin-timeline__header">
                        <span class="plugin-timeline__badge {{ $badgeClass }}">
                            {{ $typeLabel }}
                        </span>
                        <time class="plugin-timeline__date">
                            {{ $event->created_at?->format('d/m/Y H:i') }}
                        </time>
                    </div>

                    <div class="plugin-timeline__actor">
                        @if ($event->admin)
                            Reviewer: <strong>{{ $event->admin->name }}</strong> ({{ $event->admin->email }})
                        @else
                            Action by: <strong>Plugin Author</strong>
                        @endif
                    </div>

                    @if ($event->message)
                        <div class="plugin-timeline__message">
                            {{ $event->message }}
                        </div>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif
</div>

