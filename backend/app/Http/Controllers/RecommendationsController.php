<?php

namespace App\Http\Controllers;

use App\Http\Resources\ListingResource;
use App\Services\RecommendationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RecommendationsController extends Controller
{
    public function index(Request $request, RecommendationService $service): JsonResponse
    {
        $user = $request->user();
        abort_unless($user, 401, 'Unauthenticated');
        abort_unless($this->userHasRole($user, ['seeker', 'admin']), 403, 'Forbidden');

        $page = max((int) $request->input('page', 1), 1);
        $perPage = (int) $request->input('perPage', 10);
        $perPage = min(max($perPage, 1), 50);

        $filters = [
            'category' => $request->input('category'),
            'city' => $request->input('city'),
            'guests' => $request->input('guests'),
            'priceMin' => $request->input('priceMin'),
            'priceMax' => $request->input('priceMax'),
            'rooms' => $request->input('rooms'),
            'areaMin' => $request->input('areaMin'),
            'areaMax' => $request->input('areaMax'),
            'amenities' => $request->input('amenities'),
            'facilities' => $request->input('facilities'),
            'instantBook' => $request->boolean('instantBook'),
            'rating' => $request->input('rating'),
        ];

        $result = $service->recommendFor($user, $filters, $page, $perPage);

        return response()->json([
            'data' => ListingResource::collection($result['items'])->resolve(),
            'meta' => $result['meta'],
            'reasons' => $result['reasons'],
        ]);
    }

    private function userHasRole($user, array|string $roles): bool
    {
        $roles = (array) $roles;
        foreach ($roles as $role) {
            if ((method_exists($user, 'hasRole') && $user->hasRole($role)) || $user->role === $role) {
                return true;
            }
        }

        return false;
    }
}
