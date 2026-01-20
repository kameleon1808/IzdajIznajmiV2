<?php

namespace App\Listeners;

use App\Events\ApplicationCreated;
use App\Events\ApplicationStatusChanged;
use App\Models\Notification;
use App\Services\NotificationService;

class SendApplicationNotifications
{
    public function __construct(private NotificationService $notifications)
    {
    }

    public function onCreated(ApplicationCreated $event): void
    {
        $application = $event->application;
        $landlord = $application->landlord;
        $listing = $application->listing;

        if (! $landlord || ! $listing) {
            return;
        }

        $this->notifications->createNotification($landlord, Notification::TYPE_APPLICATION_CREATED, [
            'title' => 'New application received',
            'body' => sprintf('You have a new application for "%s".', $listing->title),
            'data' => [
                'application_id' => $application->id,
                'listing_id' => $listing->id,
                'seeker_id' => $application->seeker_id,
            ],
            'url' => sprintf('/landlord/applications?applicationId=%d', $application->id),
        ]);
    }

    public function onStatusChanged(ApplicationStatusChanged $event): void
    {
        $application = $event->application->loadMissing('seeker', 'listing');
        $seeker = $application->seeker;
        $listing = $application->listing;

        if (! $seeker || ! $listing) {
            return;
        }

        $this->notifications->createNotification($seeker, Notification::TYPE_APPLICATION_STATUS_CHANGED, [
            'title' => 'Application status updated',
            'body' => sprintf('Your application for "%s" is now %s.', $listing->title, $application->status),
            'data' => [
                'application_id' => $application->id,
                'listing_id' => $listing->id,
                'status' => $application->status,
            ],
            'url' => sprintf('/applications?applicationId=%d', $application->id),
        ]);
    }
}

