<?php

namespace App\Models;

use App\Enums\CommentReportStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommentReport extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'comment_id',
        'plugin_id',
        'reason',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'status' => CommentReportStatus::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function comment(): BelongsTo
    {
        return $this->belongsTo(Comment::class)->withTrashed();
    }

    public function plugin(): BelongsTo
    {
        return $this->belongsTo(Plugin::class);
    }
}
