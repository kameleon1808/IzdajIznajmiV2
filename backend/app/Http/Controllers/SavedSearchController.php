<?php

namespace App\Http\Controllers;

use App\Http\Resources\SavedSearchResource;
use App\Models\SavedSearch;
use App\Services\SavedSearchNormalizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SavedSearchController extends Controller
{
    public function __construct(private readonly SavedSearchNormalizer $normalizer) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user, 401, 'Unauthenticated');

        $searches = SavedSearch::where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->get();

        return SavedSearchResource::collection($searches)->response();
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user, 401, 'Unauthenticated');

        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:120'],
            'filters' => ['required', 'array'],
            'alerts_enabled' => ['sometimes', 'boolean'],
            'frequency' => ['sometimes', 'in:instant,daily,weekly'],
        ]);

        $normalizedFilters = $this->normalizer->normalize($data['filters']);

        $duplicate = SavedSearch::where('user_id', $user->id)
            ->get()
            ->first(fn (SavedSearch $search) => $search->filters === $normalizedFilters);

        if ($duplicate) {
            return response()->json([
                'message' => 'Saved search already exists.',
                'savedSearch' => new SavedSearchResource($duplicate),
            ], 409);
        }

        $savedSearch = SavedSearch::create([
            'user_id' => $user->id,
            'name' => $data['name'] ?? null,
            'filters' => $normalizedFilters,
            'alerts_enabled' => $data['alerts_enabled'] ?? true,
            'frequency' => $data['frequency'] ?? 'instant',
            'last_alerted_at' => null,
        ]);

        return (new SavedSearchResource($savedSearch))
            ->response()
            ->setStatusCode(201);
    }

    public function update(Request $request, SavedSearch $savedSearch): JsonResponse
    {
        $user = $request->user();
        abort_unless($user, 401, 'Unauthenticated');
        $this->authorize('update', $savedSearch);

        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:120'],
            'filters' => ['sometimes', 'array'],
            'alerts_enabled' => ['sometimes', 'boolean'],
            'frequency' => ['sometimes', 'in:instant,daily,weekly'],
        ]);

        if (array_key_exists('filters', $data)) {
            $normalizedFilters = $this->normalizer->normalize($data['filters'] ?? []);

            $duplicate = SavedSearch::where('user_id', $user->id)
                ->where('id', '!=', $savedSearch->id)
                ->get()
                ->first(fn (SavedSearch $search) => $search->filters === $normalizedFilters);

            if ($duplicate) {
                return response()->json([
                    'message' => 'Saved search already exists.',
                    'savedSearch' => new SavedSearchResource($duplicate),
                ], 409);
            }

            $savedSearch->filters = $normalizedFilters;
        }

        if (array_key_exists('name', $data)) {
            $savedSearch->name = $data['name'];
        }

        if (array_key_exists('alerts_enabled', $data)) {
            $savedSearch->alerts_enabled = $data['alerts_enabled'];
        }

        if (array_key_exists('frequency', $data)) {
            $savedSearch->frequency = $data['frequency'];
        }

        $savedSearch->save();

        return (new SavedSearchResource($savedSearch->fresh()))->response();
    }

    public function destroy(Request $request, SavedSearch $savedSearch): JsonResponse
    {
        $user = $request->user();
        abort_unless($user, 401, 'Unauthenticated');
        $this->authorize('delete', $savedSearch);

        $savedSearch->delete();

        return response()->json(['message' => 'Saved search deleted.']);
    }
}
