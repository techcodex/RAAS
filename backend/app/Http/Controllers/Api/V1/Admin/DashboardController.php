<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\DocumentStatus;
use App\Enums\OrganizationStatus;
use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\Organization;
use App\Models\Project;
use Illuminate\Http\JsonResponse;

/**
 * Platform-wide aggregates and read-only configuration for the admin dashboard.
 * These routes run with no tenant bound, so model queries are already global;
 * `withoutGlobalScope('organization')` is defensive.
 */
class DashboardController extends Controller
{
    public function index(): JsonResponse
    {
        $orgsByStatus = Organization::query()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $docsByStatus = Document::withoutGlobalScope('organization')
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $documentsByStatus = collect(DocumentStatus::cases())
            ->mapWithKeys(fn (DocumentStatus $status) => [
                $status->value => (int) ($docsByStatus[$status->value] ?? 0),
            ]);

        return response()->json([
            'organizations' => [
                'total' => (int) $orgsByStatus->sum(),
                'active' => (int) ($orgsByStatus[OrganizationStatus::Active->value] ?? 0),
                'disabled' => (int) ($orgsByStatus[OrganizationStatus::Disabled->value] ?? 0),
            ],
            'projects' => [
                'total' => Project::withoutGlobalScope('organization')->count(),
            ],
            'documents' => [
                'total' => (int) $documentsByStatus->sum(),
                'by_status' => $documentsByStatus,
            ],
        ]);
    }

    public function settings(): JsonResponse
    {
        return response()->json([
            'documents' => [
                'max_size_kb' => (int) config('raas.documents.max_size_kb'),
                'allowed_extensions' => config('raas.documents.allowed_extensions'),
            ],
            'organizations' => [
                'default_document_limit' => config('raas.organizations.default_document_limit'),
            ],
        ]);
    }
}
