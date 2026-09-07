<?php

namespace App\Http\Controllers\Api\App;

use App\Http\Resources\Api\App\PublicAppResource;

class PublicationController extends Controller
{
    /**
     * Public landing config for a published app.
     */
    public function show(string $slug): PublicAppResource
    {
        return new PublicAppResource($this->activePublication($slug));
    }
}
