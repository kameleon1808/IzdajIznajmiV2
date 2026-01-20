<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Request;

class AuditLogService
{
    public function record(?int $actorId, string $action, ?string $subjectType = null, ?int $subjectId = null, array $metadata = []): void
    {
        $request = Request::instance();

        AuditLog::create([
            'actor_user_id' => $actorId,
            'action' => $action,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'metadata' => $metadata ?: null,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
        ]);
    }
}
