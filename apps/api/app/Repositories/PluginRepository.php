<?php

namespace App\Repositories;

use App\Contracts\PluginRepositoryInterface;
use App\Enums\PluginStatus;
use App\Models\Category;
use App\Models\Plugin;
use App\Models\Tag;
use App\Support\LikeHelper;
use App\Models\PluginDailyView;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * @extends BaseRepository<Plugin>
 */
class PluginRepository extends BaseRepository implements PluginRepositoryInterface
{
    public function getModel(): string
    {
        return Plugin::class;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Plugin
    {
        /** @var Plugin $plugin */
        $plugin = parent::create($attributes);

        return $plugin;
    }

    public function findApprovedById(string $id): Plugin
    {
        return $this->baseQuery()
            ->where('status', PluginStatus::Approved)
            ->findOrFail($id);
    }

    /**
     * @param  array<int, string>  $ids
     * @return Collection<int, Plugin>
     */
    public function findApprovedByIds(array $ids): Collection
    {
        if (empty($ids)) {
            return $this->model->newCollection();
        }

        $plugins = $this->baseQuery()
            ->where('status', PluginStatus::Approved)
            ->whereNotNull('approved_at')
            ->selectRaw("*, {$this->starCountSql()} as star_count, {$this->commentCountSql()} as comment_count")
            ->whereIn('id', $ids)
            ->get();

        $order = array_flip($ids);

        return $plugins->sortBy(fn (Plugin $plugin) => $order[$plugin->id] ?? 9999)->values();
    }

    public function incrementViewCount(string $id, int $count): void
    {
        $this->model->newQuery()->where('id', $id)->increment('view_count', $count);

        PluginDailyView::upsert(
            [
                ['plugin_id' => $id, 'date' => now()->format('Y-m-d'), 'views_count' => $count],
            ],
            ['plugin_id', 'date'],
            ['views_count' => DB::raw("plugin_daily_views.views_count + {$count}")]
        );
    }

    public function findById(string $id): Plugin
    {
        return $this->baseQuery()->findOrFail($id);
    }

    /**
     * Write the given attributes onto the plugin and return it re-read.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function update(Model $model, array $attributes): Plugin
    {
        /** @var Plugin $updated */
        $updated = parent::update($model, $attributes);

        return $updated;
    }

    public function replaceCategory(Plugin $plugin, Category $category): void
    {
        $plugin->categoryLinks()->delete();
        $plugin->categoryLinks()->create(['category_id' => $category->id]);
    }

    /**
     * @param  array<int, Tag>  $tags
     */
    public function replaceTags(Plugin $plugin, array $tags): void
    {
        $plugin->tagLinks()->delete();

        $plugin->tagLinks()->createMany(
            array_map(fn (Tag $tag): array => ['tag_id' => $tag->id], $tags),
        );
    }

    public function getPaginatedApprovedPlugins(int $perPage, ?string $category = null, ?string $search = null): LengthAwarePaginator
    {
        return $this->baseQuery()
            ->where('status', PluginStatus::Approved)
            ->whereNotNull('approved_at')
            // Keep only the plugins filed under this category, when given.
            ->when($category !== null, fn (Builder $query) => $query->whereHas(
                'categories',
                fn (Builder $categories) => $categories->where('name', $category),
            ))
            // Match the keyword in either column, and nothing at all without one.
            ->when(filled($search), fn (Builder $query) => $query->where(
                fn (Builder $matches) => $matches
                    ->where(fn (Builder $name) => LikeHelper::whereContains($name, 'name', $search))
                    ->orWhere(fn (Builder $title) => LikeHelper::whereContains($title, 'title', $search)),
            ))
            ->withCount([
                'stars as star_count',
                'comments as comment_count' => fn (Builder $comments) => $comments->visible(),
            ])
            ->orderByDesc('approved_at')
            ->paginate($perPage);
    }

    public function getPaginatedPluginsByUser(string $userId, ?PluginStatus $status, int $perPage): LengthAwarePaginator
    {
        $query = $this->baseQuery()
            ->where('user_id', $userId)
            ->withCount([
                'stars as star_count',
                'comments as comment_count' => fn (Builder $comments) => $comments->visible(),
            ]);

        if ($status !== null) {
            $query->where('status', $status);
        }

        // Submission time rather than approved_at, which is null for anything
        // not yet approved — and on this list that is most of the rows.
        return $query
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    public function getTrendingPlugins(int $daysLimit, array $weights, float $gravity, float $ageOffset, int $limit): Collection
    {
        return $this->baseQuery()
            ->where('status', PluginStatus::Approved)
            ->whereNotNull('approved_at')
            ->where('approved_at', '>=', now()->subDays($daysLimit))
            ->selectRaw("*, {$this->starCountSql()} as star_count, {$this->commentCountSql()} as comment_count, (
                (view_count * ? + {$this->commentCountSql()} * ? + {$this->starCountSql()} * ?)
                / POWER({$this->getAgeInSecondsSql()}/3600.0 + ?, ?)
            ) as trending_score", [
                $weights['view'],
                $weights['comment'],
                $weights['star'],
                $ageOffset,
                $gravity,
            ])
            ->orderByDesc('trending_score')
            ->limit($limit)
            ->get();
    }

    /**
     * Shared skeleton for every query that feeds `PluginResource`.
     *
     * @return Builder<Plugin>
     */
    private function baseQuery(): Builder
    {
        return $this->newQuery()->with(['user.profile', 'categories', 'tags']);
    }

    private function getAgeInSecondsSql(): string
    {
        return match ($this->model->getConnection()->getDriverName()) {
            'sqlite' => "(strftime('%s', 'now') - strftime('%s', approved_at))",
            'pgsql' => 'EXTRACT(EPOCH FROM (NOW() - approved_at))',
            default => '(UNIX_TIMESTAMP(NOW()) - UNIX_TIMESTAMP(approved_at))',
        };
    }

    private function starCountSql(): string
    {
        $prefix = $this->model->getConnection()->getTablePrefix();

        return "(select count(*) from {$prefix}stars
            where {$prefix}stars.plugin_id = {$prefix}plugins.id
            and {$prefix}stars.deleted_at is null)";
    }

    private function commentCountSql(): string
    {
        $prefix = $this->model->getConnection()->getTablePrefix();

        return "(select count(*) from {$prefix}comments
            where {$prefix}comments.plugin_id = {$prefix}plugins.id
            and {$prefix}comments.hidden_at is null
            and {$prefix}comments.deleted_at is null)";
    }

    public function getTopAllTimePlugins(int $limit): Collection
    {
        return $this->baseQuery()
            ->where('status', PluginStatus::Approved)
            ->whereNotNull('approved_at')
            ->selectRaw("*, {$this->starCountSql()} as star_count, {$this->commentCountSql()} as comment_count")
            ->orderByDesc('view_count')
            ->orderByRaw($this->starCountSql().' desc')
            ->limit($limit)
            ->get();
    }
}
