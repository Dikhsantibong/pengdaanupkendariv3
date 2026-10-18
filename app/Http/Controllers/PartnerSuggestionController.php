<?php

namespace App\Http\Controllers;

use App\Models\Partner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The Mitra master data offered while a partner's name is being typed: the
 * names starting with what was typed first, then those containing it.
 */
class PartnerSuggestionController extends Controller
{
    /**
     * The partners matching what was typed so far.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $search = $request->string('search')->squish()->limit(100, '')->value();

        if ($search === '') {
            return response()->json(['partners' => []]);
        }

        $partners = Partner::query()
            ->active()
            ->where('name', 'like', '%'.addcslashes($search, '%_\\').'%')
            ->orderByRaw('CASE WHEN name LIKE ? THEN 0 ELSE 1 END', [addcslashes($search, '%_\\').'%'])
            ->orderBy('name')
            ->limit(8)
            ->get(['id', 'name', 'vendor_code', 'director_name', 'address']);

        return response()->json([
            'partners' => $partners->map(fn (Partner $partner): array => [
                'id' => $partner->id,
                'name' => $partner->name,
                'vendor_code' => $partner->vendor_code,
                'director_name' => $partner->director_name,
                'address' => $partner->address,
            ])->all(),
        ]);
    }
}
