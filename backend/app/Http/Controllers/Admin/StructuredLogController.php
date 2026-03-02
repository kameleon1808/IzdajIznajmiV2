<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StructuredLogController extends Controller
{
    private const DEFAULT_LIMIT = 200;

    private const MAX_LIMIT = 500;

    public function index(Request $request): JsonResponse
    {
        $date = $request->input('date', now()->toDateString());

        // Prevent path traversal — only allow YYYY-MM-DD.
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            abort(422, 'Invalid date format.');
        }

        $limit = min((int) ($request->input('limit', self::DEFAULT_LIMIT)), self::MAX_LIMIT);
        $actionFilter = $request->input('action');
        $levelFilter = $request->input('level');
        $securityOnly = filter_var($request->input('security_event'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        $userIdFilter = $request->input('user_id');

        $path = storage_path("logs/structured-{$date}.log");

        if (! file_exists($path)) {
            return response()->json([]);
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $entries = [];

        foreach ($lines as $line) {
            $entry = json_decode($line, true);
            if (! is_array($entry)) {
                continue;
            }

            $ctx = $entry['context'] ?? [];

            if ($actionFilter && ! str_contains($ctx['action'] ?? $entry['message'] ?? '', $actionFilter)) {
                continue;
            }

            if ($levelFilter) {
                $entryLevel = strtolower($entry['level_name'] ?? '');
                if ($entryLevel !== strtolower($levelFilter)) {
                    continue;
                }
            }

            if ($securityOnly === true && empty($ctx['security_event'])) {
                continue;
            }

            if ($userIdFilter !== null && (string) ($ctx['user_id'] ?? '') !== (string) $userIdFilter) {
                continue;
            }

            $entries[] = $entry;
        }

        // Return newest first, limited.
        $entries = array_reverse($entries);
        if ($limit > 0) {
            $entries = array_slice($entries, 0, $limit);
        }

        return response()->json($entries);
    }
}
