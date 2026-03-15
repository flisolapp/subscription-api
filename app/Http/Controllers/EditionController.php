<?php

namespace App\Http\Controllers;

use App\Models\Edition;
use Illuminate\Http\JsonResponse;

class EditionController extends Controller
{
    /**
     * GET /editions
     *
     * Returns all editions ordered by year descending.
     * The frontend uses this to let the user (or the app) pick an edition_id
     * before submitting any subscription form.
     */
    public function index(): JsonResponse
    {
        $editions = Edition::orderByDesc('year')
            ->whereNull('removed_at')
            ->get(['id', 'year', 'active']);

        return response()->json([
            'data' => $editions,
        ]);
    }

    /**
     * GET /editions/active
     *
     * Convenience endpoint: returns the single currently active edition.
     * Returns 404 if none is marked active.
     */
    public function active(): JsonResponse
    {
        $edition = Edition::where('active', true)
            ->whereNull('removed_at')
            ->first(['id', 'year', 'active']);

        if (! $edition) {
            return response()->json([
                'message' => 'Nenhuma edição ativa encontrada.',
            ], 404);
        }

        return response()->json([
            'data' => $edition,
        ]);
    }
}
