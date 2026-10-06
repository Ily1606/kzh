<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\CommentReport;
use App\Models\Comment;

class CommentContextTree extends Component
{
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
    
    protected function getDescendantIds($parentId)
    {
        $ids = [];
        $children = Comment::withTrashed()->where('parent_comment_id', $parentId)->pluck('id')->toArray();
        foreach ($children as $childId) {
            $ids[] = $childId;
            $ids = array_merge($ids, $this->getDescendantIds($childId));
        }
        return $ids;
    }
    
    public function deleteComment($id)
    {
        $ids = array_merge([$id], $this->getDescendantIds($id));
        
        Comment::whereIn('id', $ids)->delete();
        CommentReport::whereIn('comment_id', $ids)->update(['status' => \App\Enums\CommentReportStatus::Resolved]);
    }
    
    public function hideComment($id)
    {
        $ids = array_merge([$id], $this->getDescendantIds($id));
        
        Comment::whereIn('id', $ids)->update(['hidden_at' => now()]);
        CommentReport::whereIn('comment_id', $ids)->update(['status' => \App\Enums\CommentReportStatus::Resolved]);
    }
    
    public function dismissReport($id)
    {
        CommentReport::where('comment_id', $id)->update(['status' => \App\Enums\CommentReportStatus::Rejected]);
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
