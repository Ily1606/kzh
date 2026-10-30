<div class="cmt-tree-wrapper">
    <style>
        .cmt-tree-wrapper {
            --cmt-card-bg: #ffffff;
            --cmt-card-border: #e5e7eb;
            --cmt-text: #111827;
            --cmt-muted: #6b7280;
            --cmt-avatar-bg: #f3f4f6;
            --cmt-avatar-text: #374151;
            --cmt-avatar-border: #e5e7eb;
            --cmt-target-bg: #eff6ff;
            --cmt-target-border: #3b82f6;
            --cmt-target-report-bg: #fef2f2;
            --cmt-target-report-border: #ef4444;
            --cmt-thread-line: #e5e7eb;
            font-family: inherit;
        }

        .dark .cmt-tree-wrapper,
        [data-theme="dark"] .cmt-tree-wrapper {
            --cmt-card-bg: #18181b;
            --cmt-card-border: rgba(255, 255, 255, 0.1);
            --cmt-text: #f4f4f5;
            --cmt-muted: #9ca3af;
            --cmt-avatar-bg: #27272a;
            --cmt-avatar-text: #e4e4e7;
            --cmt-avatar-border: rgba(255, 255, 255, 0.15);
            --cmt-target-bg: rgba(59, 130, 246, 0.15);
            --cmt-target-border: #3b82f6;
            --cmt-target-report-bg: rgba(239, 68, 68, 0.15);
            --cmt-target-report-border: #ef4444;
            --cmt-thread-line: rgba(255, 255, 255, 0.12);
        }

        .cmt-card {
            background-color: var(--cmt-card-bg);
            border: 1px solid var(--cmt-card-border);
            border-radius: 12px;
            padding: 16px;
            transition: all 0.15s ease-in-out;
        }

        .cmt-card--target-comment {
            background-color: var(--cmt-target-bg);
            border: 2px solid var(--cmt-target-border);
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        }

        .cmt-card--target-report {
            background-color: var(--cmt-target-report-bg);
            border: 2px solid var(--cmt-target-report-border);
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        }

        .cmt-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 8px;
        }

        .cmt-user-group {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .cmt-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background-color: var(--cmt-avatar-bg);
            color: var(--cmt-avatar-text);
            border: 1px solid var(--cmt-avatar-border);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 13px;
            flex-shrink: 0;
        }

        .cmt-author {
            font-weight: 600;
            font-size: 14px;
            color: var(--cmt-text);
        }

        .cmt-date {
            font-size: 12px;
            color: var(--cmt-muted);
        }

        .cmt-actions {
            display: flex;
            align-items: center;
            gap: 6px;
            margin-left: auto;
        }

        .cmt-content {
            font-size: 14px;
            line-height: 1.6;
            color: var(--cmt-text);
            word-break: break-word;
            overflow-wrap: break-word;
            white-space: pre-line;
            text-indent: 0;
            margin: 0;
            padding-left: 42px;
        }

        .cmt-toggle-btn {
            margin-top: 6px;
            margin-left: 42px;
            font-size: 12px;
            font-weight: 600;
            color: #2563eb;
            background: transparent;
            border: none;
            cursor: pointer;
            padding: 0;
        }
        .cmt-toggle-btn:hover {
            text-decoration: underline;
        }
        .dark .cmt-toggle-btn {
            color: #60a5fa;
        }

        .cmt-replies-tree {
            margin-top: 12px;
            margin-left: 20px;
            padding-left: 18px;
            border-left: 2px solid var(--cmt-thread-line);
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .cmt-node {
            margin-bottom: 12px;
        }
        .cmt-node--reply {
            margin-bottom: 0;
        }

        .cmt-load-link {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 13px;
            font-weight: 600;
            color: #2563eb;
            background: transparent;
            border: none;
            cursor: pointer;
            padding: 4px 8px;
            border-radius: 6px;
            transition: background-color 0.15s;
        }
        .cmt-load-link:hover {
            background-color: rgba(37, 99, 235, 0.08);
        }
        .dark .cmt-load-link {
            color: #60a5fa;
        }
        .dark .cmt-load-link:hover {
            background-color: rgba(96, 165, 250, 0.12);
        }

        .cmt-empty-notice {
            padding: 16px;
            border-radius: 10px;
            background-color: rgba(234, 179, 8, 0.1);
            color: #eab308;
            border: 1px solid rgba(234, 179, 8, 0.2);
            font-size: 14px;
        }
    </style>

    @if(!$targetCommentId)
        <div class="cmt-empty-notice">
            The reported comment could not be found or the record is invalid.
        </div>
    @else
        @php
            $rootData = $this->getRepliesFor(null);
        @endphp

        <div>
            @if($rootData['hiddenBefore'] > 0)
                <div style="margin-bottom: 10px;">
                    <button
                        type="button"
                        wire:click="loadOlder(null)"
                        class="cmt-load-link"
                    >
                        <svg style="width: 14px; height: 14px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"></path></svg>
                        Load {{ min(5, $rootData['hiddenBefore']) }} older root comment(s) ({{ $rootData['hiddenBefore'] }} hidden)
                    </button>
                </div>
            @endif
            
            @foreach($rootData['replies'] as $root)
                @include('filament.resources.comments.partials.comment-node', ['comment' => $root, 'isReply' => false])
            @endforeach
            
            @if($rootData['hiddenAfter'] > 0)
                <div style="margin-top: 10px;">
                    <button
                        type="button"
                        wire:click="loadNewer(null)"
                        class="cmt-load-link"
                    >
                        <svg style="width: 14px; height: 14px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                        Load {{ min(5, $rootData['hiddenAfter']) }} newer root comment(s) ({{ $rootData['hiddenAfter'] }} hidden)
                    </button>
                </div>
            @endif
        </div>
    @endif

    <x-filament-actions::modals />
</div>
