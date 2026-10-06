<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\CommentReport;
use App\Models\Comment;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Actions\Action;

class CommentContextTree extends Component implements HasForms, HasActions
{
    use InteractsWithActions;
    use InteractsWithForms;

    public string $reportId;
    public ?string $targetCommentId = null;
    public ?string $pluginId = null;
    public array $ancestorIds = [];
    public array $nodeStates = [];

    public function mount(string $reportId)
    {
        $this->reportId = $reportId;
        $report = CommentReport::with('comment')->find($reportId);
        
        if (!$report || !$report->comment) {
            return;
        }
        
        $this->targetCommentId = $report->comment_id;
        $this->pluginId = $report->plugin_id;
        
        $curr = $report->comment;
        while ($curr) {
            $this->ancestorIds[] = $curr->id;
            $curr = $curr->parentComment;
        }
    }
    
    protected function fetchAllReplies($parentId)
    {
        if ($parentId === null) {
            return Comment::withTrashed()->where('plugin_id', $this->pluginId)
                ->whereNull('parent_comment_id')
                ->orderBy('created_at', 'asc')
                ->get();
        }
        
        return Comment::withTrashed()->where('parent_comment_id', $parentId)
            ->orderBy('created_at', 'asc')
            ->get();
    }
    
    public function deleteCommentAction(): Action
    {
        return Action::make('deleteComment')
            ->requiresConfirmation()
            ->color('danger')
            ->icon('heroicon-o-trash')
            ->modalHeading('Delete Comment')
            ->modalDescription('Are you sure you want to delete this comment? All child comments will also be deleted.')
            ->modalSubmitActionLabel('Yes, delete it')
            ->action(function (array $arguments) {
                $comment = Comment::withTrashed()->find($arguments['comment_id']);
                if ($comment) $comment->cascadeDelete();
            });
    }

    public function hideCommentAction(): Action
    {
        return Action::make('hideComment')
            ->requiresConfirmation()
            ->color('warning')
            ->icon('heroicon-o-eye-slash')
            ->modalHeading('Hide Comment')
            ->modalDescription('Are you sure you want to hide this comment? All child comments will also be hidden.')
            ->modalSubmitActionLabel('Yes, hide it')
            ->action(function (array $arguments) {
                $comment = Comment::withTrashed()->find($arguments['comment_id']);
                if ($comment) $comment->cascadeHide();
            });
    }

    public function dismissReportAction(): Action
    {
        return Action::make('dismissReport')
            ->requiresConfirmation()
            ->color('success')
            ->icon('heroicon-o-check-circle')
            ->modalHeading('Dismiss Reports')
            ->modalDescription('Are you sure you want to dismiss the reports for this comment?')
            ->modalSubmitActionLabel('Yes, dismiss')
            ->action(function (array $arguments) {
                CommentReport::where('comment_id', $arguments['comment_id'])->delete();
            });
    }

    public function restoreCommentAction(): Action
    {
        return Action::make('restoreComment')
            ->requiresConfirmation()
            ->color('gray')
            ->modalHeading('Restore Comment')
            ->modalDescription('Are you sure you want to restore this comment and its reports?')
            ->modalSubmitActionLabel('Yes, restore')
            ->action(function (array $arguments) {
                $comment = Comment::withTrashed()->find($arguments['comment_id']);
                if ($comment) $comment->cascadeRestore();
            });
    }

    public function unhideCommentAction(): Action
    {
        return Action::make('unhideComment')
            ->requiresConfirmation()
            ->color('gray')
            ->modalHeading('Unhide Comment')
            ->modalDescription('Are you sure you want to unhide this comment and restore its reports?')
            ->modalSubmitActionLabel('Yes, unhide')
            ->action(function (array $arguments) {
                $comment = Comment::withTrashed()->find($arguments['comment_id']);
                if ($comment) $comment->cascadeUnhide();
            });
    }
    
    public function getRepliesFor($parentId = null)
    {
        $all = $this->fetchAllReplies($parentId);
        $total = $all->count();
        $key = $parentId ?? 'root';
        
        if (!isset($this->nodeStates[$key])) {
            $targetIndex = false;
            foreach ($all as $index => $reply) {
                if (in_array($reply->id, $this->ancestorIds)) {
                    $targetIndex = $index;
                    break;
                }
            }
            
            if ($targetIndex !== false) {
                $offset = max(0, $targetIndex - 2);
                $limit = 5;
            } else {
                $offset = 0;
                $limit = 0;
            }
            
            $this->nodeStates[$key] = [
                'offset' => $offset,
                'limit' => $limit,
            ];
        }
        
        $state = $this->nodeStates[$key];
        $replies = $all->slice($state['offset'], $state['limit']);
        
        return [
            'replies' => $replies,
            'hiddenBefore' => $state['offset'],
            'hiddenAfter' => max(0, $total - ($state['offset'] + $state['limit'])),
            'total' => $total,
        ];
    }
    
    public function loadOlder($parentId)
    {
        $key = $parentId ?? 'root';
        $this->getRepliesFor($parentId); // init if needed
        
        $currentOffset = $this->nodeStates[$key]['offset'];
        $loadAmount = min(5, $currentOffset);
        
        $this->nodeStates[$key]['offset'] -= $loadAmount;
        $this->nodeStates[$key]['limit'] += $loadAmount;
    }
    
    public function loadNewer($parentId)
    {
        $key = $parentId ?? 'root';
        $this->getRepliesFor($parentId); // init if needed
        
        $this->nodeStates[$key]['limit'] += 5;
    }

    public function render()
    {
        return view('livewire.comment-context-tree');
    }
}
