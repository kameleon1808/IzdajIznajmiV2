<?php

namespace App\Listeners;

use App\Events\ReportUpdated;
use App\Models\Notification;
use App\Models\Report;
use App\Services\NotificationService;

class SendReportUpdateNotification
{
    public function __construct(private NotificationService $notifications)
    {
    }

    public function handle(ReportUpdated $event): void
    {
        $report = $event->report->loadMissing('reporter');
        $reporter = $report->reporter;

        if (! $reporter) {
            return;
        }

        if (! in_array($report->status, ['resolved', 'dismissed'], true)) {
            return;
        }

        $this->notifications->createNotification($reporter, Notification::TYPE_REPORT_UPDATE, [
            'title' => 'Your report was ' . $report->status,
            'body' => $report->resolution
                ? mb_strimwidth($report->resolution, 0, 140, '...')
                : sprintf('Your report about a %s has been %s.', class_basename($report->target_type ?? ''), $report->status),
            'data' => [
                'report_id' => $report->id,
                'status' => $report->status,
            ],
            'url' => '/support/reports',
        ]);
    }
}

