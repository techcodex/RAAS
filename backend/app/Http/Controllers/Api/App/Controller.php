<?php

namespace App\Http\Controllers\Api\App;

use App\Models\AppPublication;
use Illuminate\Http\Request;

/**
 * Base for the employee-app (`/api/app/*`) controllers. These routes carry no
 * tenant context; publications are resolved by their public slug.
 */
abstract class Controller extends \App\Http\Controllers\Controller
{
    /**
     * The published, reachable publication for a slug — or a 404. Unreachable
     * means: no such slug, deactivated, or the owning organization is disabled.
     */
    protected function activePublication(string $slug): AppPublication
    {
        $publication = AppPublication::withoutGlobalScopes()
            ->with('project.organization', 'project.credential')
            ->where('slug', $slug)
            ->first();

        abort_if($publication === null || ! $publication->is_active, 404, 'This app is not available.');
        abort_if($publication->project->organization->isDisabled(), 404, 'This app is not available.');

        return $publication;
    }

    /**
     * As `activePublication()`, and asserts the signed-in employee belongs to it.
     */
    protected function employeePublication(Request $request, string $slug): AppPublication
    {
        $publication = $this->activePublication($slug);

        abort_unless($request->user()->app_publication_id === $publication->id, 403);

        return $publication;
    }
}
