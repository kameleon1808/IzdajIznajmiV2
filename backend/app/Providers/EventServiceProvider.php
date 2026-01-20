<?php

namespace App\Providers;

use App\Events\ApplicationCreated;
use App\Events\ApplicationStatusChanged;
use App\Events\MessageCreated;
use App\Events\RatingCreated;
use App\Events\ReportUpdated;
use App\Listeners\SendApplicationNotifications;
use App\Listeners\SendMessageNotification;
use App\Listeners\SendRatingNotification;
use App\Listeners\SendReportUpdateNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        ApplicationCreated::class => [
            SendApplicationNotifications::class . '@onCreated',
        ],
        ApplicationStatusChanged::class => [
            SendApplicationNotifications::class . '@onStatusChanged',
        ],
        MessageCreated::class => [
            SendMessageNotification::class,
        ],
        RatingCreated::class => [
            SendRatingNotification::class,
        ],
        ReportUpdated::class => [
            SendReportUpdateNotification::class,
        ],
    ];
}

