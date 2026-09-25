<?php

namespace App\Repositories;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * The "skeleton" shared by every repository in the application.
 *
 * This is the Template Method pattern: this class owns the lifecycle
 * (bootstrapping a clean model instance, building queries) and delegates the
 * one thing it cannot know — which model it serves — to its children through
 * the {@see self::getModel()} hook.
 *
 * @template TModel of Model
 */
abstract class BaseRepository
{
    /**
     * A clean, container-resolved instance of the concrete model.
     *
     * @var TModel
     */
    protected Model $model;

    /**
     * @var Builder<TModel>
     */
    protected Builder $query;

    public function __construct(
        protected readonly Application $app,
    ) {
        $this->resetModel();
    }

    /**
     * Hook method. Every child must answer "which model do I serve?".
     *
     * The base class calls this method, never the other way around — the
     * Hollywood Principle ("don't call us, we'll call you").
     *
     * @return class-string<TModel>
     */
    abstract public function getModel(): string;

    /**
     * Template method: rebuild both the model and its query builder from the
     * container so a repository instance is always reusable and stateless
     * between calls.
     *
     * @return TModel
     */
    public function resetModel(): Model
    {
        /** @var TModel $instance */
        $instance = $this->app->make($this->getModel());

        $this->query = $instance->newQuery();

        return $this->model = $instance;
    }

    /**
     * A fresh query builder for the concrete model.
     *
     * Children build every query from here, so no repository ever has to
     * reference a model class name directly.
     *
     * @return Builder<TModel>
     */
    public function newQuery(): Builder
    {
        return $this->model->newQuery();
    }

    /**
     * @return TModel|null
     */
    public function find(mixed $id): ?Model
    {
        /** @var TModel|null $found */
        $found = (clone $this->query)->find($id);

        return $found;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return TModel
     */
    public function create(array $attributes): Model
    {
        /** @var TModel $created */
        $created = (clone $this->query)->create($attributes);

        return $created;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return TModel
     */
    public function update(Model $model, array $attributes): Model
    {
        $model->fill($attributes)->save();

        return $model->refresh();
    }

    public function delete(Model $model): bool
    {
        return (bool) $model->delete();
    }
}
