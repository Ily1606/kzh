@props(['comment', 'isReply' => false])

@php
    $isTarget = $comment->id === $targetCommentId;
    $replyData = $this->getRepliesFor($comment->id);
    $hasReplies = $replyData['total'] > 0;
    $contentLength = mb_strlen($comment->content);
    $isLong = $contentLength > 350 || substr_count($comment->content, "\n") > 4;
@endphp

<div class="cmt-node {{ $isReply ? 'cmt-node--reply' : '' }}">
    {{-- Comment Card --}}
    <div class="cmt-card {{ $isTarget ? ($this->reportId ? 'cmt-card--target-report' : 'cmt-card--target-comment') : '' }}">
        {{-- Header row --}}
        <div class="cmt-header">
            <div class="cmt-user-group">
                {{-- Avatar --}}
                <div class="cmt-avatar">
                    {{ strtoupper(substr($comment->author->name ?? 'U', 0, 1)) }}
                </div>

                {{-- Author & Timestamp --}}
                <span class="cmt-author">{{ $comment->author->name ?? 'Unknown User' }}</span>
                <span class="cmt-date">{{ $comment->created_at?->diffForHumans() }}</span>

                {{-- Badges --}}
                @if($isTarget)
                    @if($this->reportId)
                        <x-filament::badge color="danger" size="sm">Reported Target</x-filament::badge>
                    @else
                        <x-filament::badge color="primary" size="sm">Selected Comment</x-filament::badge>
                    @endif
                @endif

                @if($comment->trashed())
                    <x-filament::badge color="gray" size="sm">Deleted</x-filament::badge>
                @endif

                @if($comment->hidden_at)
                    <x-filament::badge color="warning" size="sm">Hidden</x-filament::badge>
                @endif
            </div>

            {{-- Action buttons --}}
            <div class="cmt-actions">
                @if(!$comment->trashed())
                    <x-filament::button
                        size="xs"
                        color="danger"
                        outlined
                        wire:click="mountAction('deleteComment', { comment_id: '{{ $comment->id }}' })"
                    >
                        Delete
                    </x-filament::button>

                    @if(!$comment->hidden_at)
                        <x-filament::button
                            size="xs"
                            color="warning"
                            outlined
                            wire:click="mountAction('hideComment', { comment_id: '{{ $comment->id }}' })"
                        >
                            Hide
                        </x-filament::button>
                    @else
                        <x-filament::button
                            size="xs"
                            color="success"
                            outlined
                            wire:click="mountAction('unhideComment', { comment_id: '{{ $comment->id }}' })"
                        >
                            Unhide
                        </x-filament::button>
                    @endif
                @endif

                @if($isTarget && $this->reportId)
                    @php
                        $targetReport = \App\Models\CommentReport::withTrashed()->find($this->reportId);
                        $isDismissed = $targetReport ? $targetReport->trashed() : false;
                    @endphp
                    @if(!$isDismissed)
                        <x-filament::button
                            size="xs"
                            color="success"
                            outlined
                            wire:click="mountAction('dismissReport', { comment_id: '{{ $comment->id }}' })"
                        >
                            Dismiss Reports
                        </x-filament::button>
                    @endif
                @endif
            </div>
        </div>

        {{-- Content body (Clean typography, no sliced lines) --}}
        @if($isLong)
            <div x-data="{ expanded: false }">
                <div x-show="expanded" class="cmt-content">
                    {{ trim($comment->content) }}
                </div>
                <div x-show="!expanded" class="cmt-content">
                    {{ \Illuminate\Support\Str::limit(trim($comment->content), 300) }}
                </div>
                <button
                    type="button"
                    @click="expanded = !expanded"
                    class="cmt-toggle-btn"
                >
                    <span x-text="expanded ? 'Show less' : 'Read full comment'"></span>
                </button>
            </div>
        @else
            <div class="cmt-content">
                {{ trim($comment->content) }}
            </div>
        @endif
    </div>

    {{-- Replies Tree --}}
    @if($hasReplies)
        <div class="cmt-replies-tree">
            @if($replyData['hiddenBefore'] > 0)
                <div>
                    <button
                        type="button"
                        wire:click="loadOlder('{{ $comment->id }}')"
                        class="cmt-load-link"
                    >
                        <svg style="width: 14px; height: 14px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"></path></svg>
                        Load {{ min(5, $replyData['hiddenBefore']) }} older reply(s) ({{ $replyData['hiddenBefore'] }} hidden)
                    </button>
                </div>
            @endif

            @foreach($replyData['replies'] as $reply)
                @include('filament.resources.comments.partials.comment-node', ['comment' => $reply, 'isReply' => true])
            @endforeach

            @if($replyData['hiddenAfter'] > 0)
                <div>
                    <button
                        type="button"
                        wire:click="loadNewer('{{ $comment->id }}')"
                        class="cmt-load-link"
                    >
                        <svg style="width: 14px; height: 14px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                        Load {{ min(5, $replyData['hiddenAfter']) }} newer reply(s) ({{ $replyData['hiddenAfter'] }} hidden)
                    </button>
                </div>
            @endif
        </div>
    @endif
</div>
