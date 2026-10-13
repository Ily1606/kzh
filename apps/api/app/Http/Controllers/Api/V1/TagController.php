<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\TagResource;
use App\Models\Tag;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TagController extends Controller
{
    /**
     * List the tags a plugin may carry.
     *
     * @unauthenticated
     */
    public function index(): AnonymousResourceCollection
    {
        return TagResource::collection(
            Tag::query()->orderBy('name')->get()
        );
    }
}
