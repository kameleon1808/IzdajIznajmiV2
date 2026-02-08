<?php

namespace App\Listeners;

use App\Events\RatingCreated;
use App\Models\Notification;
use App\Services\NotificationService;

class SendRatingNotification
{
    public function __construct(private NotificationService $notifications) {}

    public function handle(RatingCreated $event): void
    {
        $rating = $event->rating->loadMissing('ratee', 'listing');
        $ratee = $rating->ratee;
        $listing = $rating->listing;

        if (! $ratee || ! $listing) {
            return;
        }

        $this->notifications->createNotification($ratee, Notification::TYPE_RATING_RECEIVED, [
            'title' => 'You received a new rating',
            'body' => sprintf('You were rated %d stars for "%s".', $rating->rating, $listing->title),
            'data' => [
                'rating_id' => $rating->id,
                'listing_id' => $listing->id,
                'score' => $rating->rating,
            ],
            'url' => sprintf('/users/%d/reviews', $ratee->id),
        ]);
    }
}
