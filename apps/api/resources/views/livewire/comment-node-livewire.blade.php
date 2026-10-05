@props(['comment', 'isReply' => false])

@php
    $isTarget = $comment->id === $targetCommentId;
    $replyData = $this->getRepliesFor($comment->id);
    $hasReplies = $replyData['total'] > 0;
@endphp

<div style="margin-top: {{ $isReply ? '16px' : '0' }}; margin-bottom: {{ !$isReply ? '16px' : '0' }};">
    <div style="display: flex; gap: 12px; align-items: flex-start;">
        <!-- Avatar -->
        <div style="flex-shrink: 0; width: 36px; height: 36px; border-radius: 50%; background-color: rgba(107, 114, 128, 0.2); display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 14px;">
            {{ strtoupper(substr($comment->author->name ?? 'U', 0, 1)) }}
        </div>
        
        <!-- Content Container -->
        <div style="flex: 1; min-width: 0;">
            <!-- Header -->
            <div style="display: flex; align-items: baseline; gap: 8px; margin-bottom: 4px;">
                <span style="font-weight: 600; font-size: 14px;">{{ $comment->author->name ?? 'Unknown User' }}</span>
                <span style="font-size: 12px; opacity: 0.6;">{{ $comment->created_at?->diffForHumans() }}</span>
                
                @if($isTarget)
                    <span style="margin-left: auto; background-color: #ef4444; color: white; padding: 2px 8px; border-radius: 12px; font-size: 11px; font-weight: bold; text-transform: uppercase;">
                        Reported Target
                    </span>
                @endif
            </div>
            
            <!-- Body -->
            <div style="font-size: 14px; line-height: 1.5; {{ $isTarget ? 'padding: 8px 12px; background-color: rgba(239, 68, 68, 0.1); border-left: 3px solid #ef4444; border-radius: 4px;' : 'opacity: 0.9;' }}">
                {{ $comment->content }}
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
