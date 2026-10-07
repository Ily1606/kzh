@props(['comment', 'isReply' => false])

@php
    $isTarget = $comment->id === $targetCommentId;
    $replyData = $this->getRepliesFor($comment->id);
    $hasReplies = $replyData['total'] > 0;
@endphp

<div @style(["margin-top: 12px" => $isReply, "margin-bottom: 12px" => !$isReply])>
    <div style="display: flex; gap: 12px; align-items: flex-start; padding-bottom: 12px; border-bottom: 1px solid rgba(156, 163, 175, 0.2);">
        <!-- Avatar -->
        <div style="flex-shrink: 0; width: 36px; height: 36px; border-radius: 50%; background-color: rgba(107, 114, 128, 0.2); display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 14px;">
            {{ strtoupper(substr($comment->author->name ?? 'U', 0, 1)) }}
        </div>
        
        <!-- Content Container -->
        <div style="flex: 1; min-width: 0;">
            <!-- Header -->
            <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 4px; flex-wrap: wrap;">
                <span style="font-weight: 600; font-size: 14px;">{{ $comment->author->name ?? 'Unknown User' }}</span>
                <span style="font-size: 12px; opacity: 0.6;">{{ $comment->created_at?->diffForHumans() }}</span>
                
                @if($comment->trashed())
                    <span style="background-color: #f3f4f6; color: #4b5563; padding: 2px 6px; border-radius: 4px; font-size: 10px; font-weight: bold; display: inline-flex; align-items: center; gap: 4px;">
                        Deleted
                        <button wire:click="mountAction('restoreComment', { comment_id: '{{ $comment->id }}' })" style="background: transparent; border: none; padding: 0; color: #9ca3af; cursor: pointer; font-size: 12px; line-height: 1;">&times;</button>
                    </span>
                @endif
                
                @if($comment->hidden_at)
                    <span style="background-color: #fef3c7; color: #92400e; padding: 2px 6px; border-radius: 4px; font-size: 10px; font-weight: bold; display: inline-flex; align-items: center; gap: 4px;">
                        Hidden
                        <button wire:click="mountAction('unhideComment', { comment_id: '{{ $comment->id }}' })" style="background: transparent; border: none; padding: 0; color: #d97706; cursor: pointer; font-size: 12px; line-height: 1;">&times;</button>
                    </span>
                @endif
                
                @if($isTarget)
                    <span style="background-color: #ef4444; color: white; padding: 2px 8px; border-radius: 12px; font-size: 11px; font-weight: bold; text-transform: uppercase;">
                        Reported Target
                    </span>
                @endif
                
                <!-- Actions (Right aligned) -->
                <div style="display: flex; gap: 8px; margin-left: auto;">
                    @if(!$comment->trashed())
                        <button wire:click="mountAction('deleteComment', { comment_id: '{{ $comment->id }}' })" style="font-size: 11px; font-weight: 500; color: #dc2626; background: transparent; border: 1px solid #fca5a5; border-radius: 4px; padding: 2px 8px; cursor: pointer; transition: all 0.2s;">Delete</button>
                    @endif
                    
                    @if(!$comment->hidden_at)
                        <button wire:click="mountAction('hideComment', { comment_id: '{{ $comment->id }}' })" style="font-size: 11px; font-weight: 500; color: #d97706; background: transparent; border: 1px solid #fcd34d; border-radius: 4px; padding: 2px 8px; cursor: pointer; transition: all 0.2s;">Hide</button>
                    @endif
                    
                    @if($isTarget)
                        @php
                            $targetReport = \App\Models\CommentReport::withTrashed()->find($this->reportId);
                            $isDismissed = $targetReport ? $targetReport->trashed() : false;
                        @endphp
                        @if($isDismissed)
                            <button wire:click="mountAction('restoreReport', { comment_id: '{{ $comment->id }}' })" style="font-size: 11px; font-weight: 500; color: #4b5563; background: transparent; border: 1px solid #9ca3af; border-radius: 4px; padding: 2px 8px; cursor: pointer; transition: all 0.2s;">Restore Report</button>
                        @else
                            <button wire:click="mountAction('dismissReport', { comment_id: '{{ $comment->id }}' })" style="font-size: 11px; font-weight: 500; color: #16a34a; background: transparent; border: 1px solid #86efac; border-radius: 4px; padding: 2px 8px; cursor: pointer; transition: all 0.2s;">Dismiss Reports</button>
                        @endif
                    @endif
                </div>
            </div>
            
            <!-- Body -->
            <div x-data="{ expanded: false }">
                <div 
                    x-bind:style="expanded ? 'display: block;' : 'display: -webkit-box; -webkit-line-clamp: 4; -webkit-box-orient: vertical; overflow: hidden;'"
                    @style([
                        'font-size: 14px; line-height: 1.5; display: -webkit-box; -webkit-line-clamp: 4; -webkit-box-orient: vertical; overflow: hidden;',
                        'padding: 8px 12px; background-color: rgba(239, 68, 68, 0.1); border-left: 3px solid #ef4444; border-radius: 4px;' => $isTarget,
                        'opacity: 0.9;' => !$isTarget,
                    ])
                >
                    {{ $comment->content }}
                </div>
                @if(mb_strlen($comment->content) > 300 || substr_count($comment->content, "\n") > 3)
                    <button @click="expanded = !expanded" x-text="expanded ? 'Show less' : 'Read more'" style="font-size: 11px; font-weight: bold; color: #3b82f6; background: transparent; border: none; cursor: pointer; padding: 0; margin-top: 4px;"></button>
                @endif
            </div>
        </div>
    </div>
    
    <!-- Replies Tree -->
    @if($hasReplies)
        <div style="margin-top: 12px; margin-left: 18px; padding-left: 24px; border-left: 2px solid rgba(107, 114, 128, 0.2); display: flex; flex-direction: column;">
            
            @if($replyData['hiddenBefore'] > 0)
                <button wire:click="loadOlder('{{ $comment->id }}')" style="align-self: flex-start; font-size: 12px; font-weight: bold; color: #3b82f6; padding: 4px 0; margin-bottom: 8px; border: none; background: transparent; cursor: pointer; display: flex; align-items: center; gap: 4px;">
                    <svg style="width: 14px; height: 14px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"></path></svg>
                    Load {{ min(5, $replyData['hiddenBefore']) }} older reply(s)
                </button>
            @endif
            
            @foreach($replyData['replies'] as $reply)
                @include('livewire.comment-node-livewire', ['comment' => $reply, 'isReply' => true])
            @endforeach
            
            @if($replyData['hiddenAfter'] > 0)
                <button wire:click="loadNewer('{{ $comment->id }}')" style="align-self: flex-start; font-size: 12px; font-weight: bold; color: #3b82f6; padding: 4px 0; margin-top: 8px; border: none; background: transparent; cursor: pointer; display: flex; align-items: center; gap: 4px;">
                    <svg style="width: 14px; height: 14px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                    Load {{ min(5, $replyData['hiddenAfter']) }} newer reply(s)
                </button>
            @endif
        </div>
    @endif
</div>
