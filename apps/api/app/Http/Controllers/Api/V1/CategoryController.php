<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CategoryController extends Controller
{
    /**
     * List the categories a plugin may be filed under.
     *
     * @unauthenticated
     */
    public function index(): AnonymousResourceCollection
    {
        return CategoryResource::collection(
            Category::query()->orderBy('name')->get()
        );
    }
}
