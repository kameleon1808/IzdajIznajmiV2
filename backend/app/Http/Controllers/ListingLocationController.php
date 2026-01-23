<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateListingLocationRequest;
use App\Http\Resources\ListingResource;
use App\Jobs\GeocodeListingJob;
use App\Models\Listing;
use Illuminate\Http\JsonResponse;

class ListingLocationController extends Controller
{
    public function update(UpdateListingLocationRequest $request, Listing $listing): JsonResponse
    {
        $this->authorize('updateLocation', $listing);
        $data = $request->validated();

        $listing->forceFill([
            'lat' => $data['latitude'],
            'lng' => $data['longitude'],
            'location_source' => 'manual',
            'location_overridden_at' => now(),
            'geocoded_at' => null,
        ])->saveQuietly();

        return (new ListingResource($listing->fresh(['images', 'facilities'])))->response();
    }

    public function reset(Listing $listing): JsonResponse
    {
        $this->authorize('updateLocation', $listing);

        $listing->forceFill([
            'lat' => null,
            'lng' => null,
            'location_source' => 'geocoded',
            'location_overridden_at' => null,
            'geocoded_at' => null,
        ])->saveQuietly();

        GeocodeListingJob::dispatchSync($listing->id, true);

        return (new ListingResource($listing->fresh(['images', 'facilities'])))->response();
    }
}
