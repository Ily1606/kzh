<div>
    @if(!$targetCommentId)
        <div style="padding: 16px; background-color: rgba(234, 179, 8, 0.1); color: #eab308; border-radius: 6px;">
            The reported comment could not be found or the record is invalid.
        </div>
    @else
        @php
            $rootData = $this->getRepliesFor(null);
        @endphp


        <div style="padding-top: 8px;">
            
            @if($rootData['hiddenBefore'] > 0)
                <button wire:click="loadOlder(null)" style="font-size: 12px; font-weight: bold; color: #3b82f6; padding: 4px 12px; margin-bottom: 12px; border: none; background: transparent; cursor: pointer; display: flex; align-items: center; gap: 4px;">
                    <svg style="width: 14px; height: 14px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"></path></svg>
                    Load {{ min(5, $rootData['hiddenBefore']) }} older root comment(s) ({{ $rootData['hiddenBefore'] }} hidden)
                </button>
            @endif
            
            @foreach($rootData['replies'] as $root)
                @include('livewire.comment-node-livewire', ['comment' => $root, 'isReply' => false])
            @endforeach
            
            @if($rootData['hiddenAfter'] > 0)
                <button wire:click="loadNewer(null)" style="font-size: 12px; font-weight: bold; color: #3b82f6; padding: 4px 12px; margin-top: 12px; border: none; background: transparent; cursor: pointer; display: flex; align-items: center; gap: 4px;">
                    <svg style="width: 14px; height: 14px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                    Load {{ min(5, $rootData['hiddenAfter']) }} newer root comment(s) ({{ $rootData['hiddenAfter'] }} hidden)
                </button>
            @endif
        </div>
    @endif

    <x-filament-actions::modals />
</div>
