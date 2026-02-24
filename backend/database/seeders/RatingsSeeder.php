<?php

namespace Database\Seeders;

use App\Models\Listing;
use App\Models\ListingRating;
use App\Models\Rating;
use App\Models\RatingReply;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class RatingsSeeder extends Seeder
{
    public function run(): void
    {
        // Truncate in FK-safe order
        DB::table('rating_replies')->truncate();
        DB::table('rating_reports')->truncate();
        ListingRating::truncate();
        Rating::truncate();

        $seekers = User::where('role', 'seeker')->orderBy('id')->get();
        $landlords = User::where('role', 'landlord')->orderBy('id')->get();
        $listings = Listing::where('status', 'active')->orderBy('id')->get();

        if ($seekers->isEmpty() || $landlords->isEmpty() || $listings->isEmpty()) {
            return;
        }

        // Comments for seeker → landlord ratings
        $seekerOnLandlordComments = [
            [5, 'Excellent landlord! Very responsive and accommodating. The apartment was exactly as described.'],
            [5, 'Perfect experience from start to finish. Would highly recommend renting from this landlord.'],
            [4, 'Great landlord, very professional. Minor issues were resolved quickly.'],
            [4, 'Good communication and fair pricing. The place was clean and well-maintained.'],
            [5, 'Outstanding! The landlord went above and beyond to make us feel welcome.'],
            [3, 'Decent experience. The landlord was a bit slow to respond but helpful overall.'],
            [4, 'Solid rental experience. Trustworthy landlord and the property was as advertised.'],
            [5, 'One of the best rentals I have had. Truly professional and kind.'],
            [3, 'Average experience. Some maintenance issues were slow to be resolved.'],
            [4, 'Good landlord overall. Flexible with move-in dates and very approachable.'],
        ];

        // Comments for landlord → seeker ratings
        $landlordOnSeekerComments = [
            [5, 'Excellent tenant! Left the apartment in perfect condition. Would rent to again.'],
            [5, 'Very responsible and clean. Paid on time and communicated well.'],
            [4, 'Good tenant, respected the property and neighbors. Only minor issues.'],
            [4, 'Reliable and respectful. Would gladly rent to this tenant again.'],
            [5, 'Outstanding tenant. No issues whatsoever throughout the rental period.'],
            [3, 'Decent tenant but was late on one payment. Otherwise fine.'],
            [4, 'Property was returned in good condition. Pleasant to deal with.'],
            [5, 'Highly recommended tenant. Professional and punctual throughout the stay.'],
            [3, 'Some minor cleanliness issues at checkout, but overall acceptable.'],
            [4, 'Good tenant. Communicated any issues promptly and respectfully.'],
        ];

        // Comments for listing ratings (seeker → listing)
        $listingComments = [
            [5, 'Amazing apartment! Great location, modern furnishings, and very clean. Highly recommended!'],
            [5, 'Perfect flat for the price. Central location with stunning views. Will return!'],
            [4, 'Very nice place, exactly as described. The neighborhood is quiet and convenient.'],
            [4, 'Great value for money. The apartment has everything you need for a comfortable stay.'],
            [5, 'Absolutely loved this place. Spacious, bright, and beautifully decorated.'],
            [3, 'Decent apartment but some appliances needed attention. Overall acceptable.'],
            [4, 'Nice property in a great part of the city. Would recommend to friends.'],
            [5, 'Exceptional listing. Clean, modern, and well-equipped. 5 stars all around.'],
            [3, 'The listing photos were slightly misleading, but the place was still comfortable.'],
            [4, 'Comfortable and well-located flat. The landlord was very helpful during our stay.'],
            [5, 'Best rental experience I have had in Belgrade. Spotless and fully equipped.'],
            [4, 'Great place overall. A few minor maintenance issues but nothing deal-breaking.'],
            [5, 'Loved the terrace and the view. Very quiet neighborhood. Would book again.'],
            [3, 'Average experience. The flat was okay but not as modern as the photos suggested.'],
            [4, 'Good apartment with easy access to public transport. Clean and well-furnished.'],
        ];

        // Landlord replies to ratings they received
        $landlordReplies = [
            'Thank you for the kind words! We look forward to hosting you again.',
            'We appreciate your feedback and are glad you enjoyed your stay.',
            'Thank you! It was a pleasure having you as a tenant.',
            'We value your review and will work to improve the mentioned points.',
            'Thank you for choosing our property. Hope to see you again!',
            'Great review, thank you! We always aim for a 5-star experience.',
            'Much appreciated! We are happy to hear everything met your expectations.',
        ];

        // Build landlord → first listing map
        $landlordListingMap = [];
        foreach ($landlords as $landlord) {
            $listing = $listings->firstWhere('owner_id', $landlord->id);
            if ($listing) {
                $landlordListingMap[$landlord->id] = $listing;
            }
        }

        $createdRatings = [];

        // ── Seeker → Landlord ratings ──────────────────────────────────────────
        // Each of 10 seekers rates each of the first 5 landlords on that landlord's listing.
        // Unique constraint: [listing_id, rater_id, ratee_id] is satisfied because
        // every landlord maps to a distinct listing.
        foreach ($seekers as $seekerIdx => $seeker) {
            foreach ($landlords->take(5) as $landlordIdx => $landlord) {
                $listing = $landlordListingMap[$landlord->id] ?? null;
                if (! $listing) {
                    continue;
                }

                $commentIdx = ($seekerIdx * 5 + $landlordIdx) % count($seekerOnLandlordComments);
                [$stars, $comment] = $seekerOnLandlordComments[$commentIdx];
                $createdAt = Carbon::now()->subDays(rand(15, 90));

                $rating = Rating::create([
                    'listing_id' => $listing->id,
                    'rater_id'   => $seeker->id,
                    'ratee_id'   => $landlord->id,
                    'rating'     => $stars,
                    'comment'    => ($seekerIdx % 5 === 0) ? null : $comment,
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ]);

                $createdRatings[] = ['rating' => $rating, 'landlord' => $landlord];
            }
        }

        // ── Landlord → Seeker ratings ──────────────────────────────────────────
        // Each of the first 5 landlords rates all 10 seekers on their own listing.
        // Unique constraint satisfied: same listing_id + landlord rater + different ratees.
        foreach ($landlords->take(5) as $landlordIdx => $landlord) {
            $listing = $landlordListingMap[$landlord->id] ?? null;
            if (! $listing) {
                continue;
            }

            foreach ($seekers as $seekerIdx => $seeker) {
                $commentIdx = ($landlordIdx * 10 + $seekerIdx) % count($landlordOnSeekerComments);
                [$stars, $comment] = $landlordOnSeekerComments[$commentIdx];
                $createdAt = Carbon::now()->subDays(rand(15, 90));

                Rating::create([
                    'listing_id' => $listing->id,
                    'rater_id'   => $landlord->id,
                    'ratee_id'   => $seeker->id,
                    'rating'     => $stars,
                    'comment'    => ($seekerIdx % 4 === 0) ? null : $comment,
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ]);
            }
        }

        // ── Listing ratings (seeker → property) ────────────────────────────────
        // Each of the first 15 listings gets rated by 3–6 seekers.
        // Seeker assignment is deterministic via index offset to avoid duplicates
        // within the same listing (unique: [listing_id, seeker_id]).
        $targetListings = $listings->take(15);
        foreach ($targetListings as $listingIdx => $listing) {
            // seekerCount cycles 3, 4, 5, 6, 3, 4, 5, 6 … (max 6 < 10 total seekers)
            $seekerCount = 3 + ($listingIdx % 4);

            for ($si = 0; $si < $seekerCount; $si++) {
                $seeker = $seekers->get(($listingIdx + $si) % $seekers->count());
                if (! $seeker) {
                    continue;
                }

                $commentIdx = ($listingIdx + $si) % count($listingComments);
                [$stars, $comment] = $listingComments[$commentIdx];
                $createdAt = Carbon::now()->subDays(rand(5, 90));

                ListingRating::create([
                    'listing_id' => $listing->id,
                    'seeker_id'  => $seeker->id,
                    'rating'     => $stars,
                    'comment'    => ($si % 3 === 0) ? null : $comment,
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ]);
            }
        }

        // ── Rating replies (landlords reply to some incoming seeker reviews) ───
        // Reply to every 3rd seeker→landlord rating created above.
        foreach ($createdRatings as $idx => $data) {
            if ($idx % 3 !== 0) {
                continue;
            }

            $replyIdx = $idx % count($landlordReplies);
            $replyAt = $data['rating']->created_at->copy()->addDays(rand(1, 5));

            RatingReply::create([
                'rating_id' => $data['rating']->id,
                'author_id' => $data['landlord']->id,
                'body'      => $landlordReplies[$replyIdx],
                'is_admin'  => false,
                'created_at' => $replyAt,
                'updated_at' => $replyAt,
            ]);
        }
    }
}
