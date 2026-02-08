<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\Conversation;
use App\Models\Listing;
use App\Models\Message;
use App\Models\Rating;
use App\Models\Report;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class KpiController extends Controller
{
    public function summary(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request);
        $now = now();
        $dayAgo = $now->copy()->subDay();
        $weekAgo = $now->copy()->subDays(7);

        $summary = [
            'listings' => [
                'last24h' => Listing::where('created_at', '>=', $dayAgo)->count(),
                'last7d' => Listing::where('created_at', '>=', $weekAgo)->count(),
            ],
            'applications' => [
                'last24h' => Application::where('created_at', '>=', $dayAgo)->count(),
                'last7d' => Application::where('created_at', '>=', $weekAgo)->count(),
            ],
            'messages' => [
                'last24h' => Message::where('created_at', '>=', $dayAgo)->count(),
                'last7d' => Message::where('created_at', '>=', $weekAgo)->count(),
            ],
            'ratings' => [
                'last24h' => Rating::where('created_at', '>=', $dayAgo)->count(),
                'last7d' => Rating::where('created_at', '>=', $weekAgo)->count(),
            ],
            'reports' => [
                'last24h' => Report::where('created_at', '>=', $dayAgo)->count(),
                'last7d' => Report::where('created_at', '>=', $weekAgo)->count(),
            ],
            'suspiciousUsers' => User::where('is_suspicious', true)->count(),
        ];

        return response()->json($summary);
    }

    public function conversion(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request);

        $listings = Listing::count();
        $applications = Application::count();
        $conversations = Conversation::count();
        $ratings = Rating::count();

        $conversion = [
            'browseToApply' => [
                'from' => $listings,
                'to' => $applications,
                'rate' => $listings > 0 ? round($applications / $listings, 2) : 0,
            ],
            'applyToChat' => [
                'from' => $applications,
                'to' => $conversations,
                'rate' => $applications > 0 ? round($conversations / $applications, 2) : 0,
            ],
            'chatToRating' => [
                'from' => $conversations,
                'to' => $ratings,
                'rate' => $conversations > 0 ? round($ratings / $conversations, 2) : 0,
            ],
        ];

        return response()->json($conversion);
    }

    public function trends(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request);

        $range = $request->query('range', '7d');
        $days = $range === '30d' ? 30 : 7;
        $startDate = now()->subDays($days - 1)->startOfDay();
        $endDate = now()->endOfDay();

        $series = [
            'listings' => $this->countsByDate(Listing::class, $startDate, $endDate),
            'applications' => $this->countsByDate(Application::class, $startDate, $endDate),
            'messages' => $this->countsByDate(Message::class, $startDate, $endDate),
            'ratings' => $this->countsByDate(Rating::class, $startDate, $endDate),
            'reports' => $this->countsByDate(Report::class, $startDate, $endDate),
        ];

        $data = [];
        for ($date = $startDate->copy(); $date <= $endDate; $date->addDay()) {
            $key = $date->toDateString();
            $data[] = [
                'date' => $key,
                'listings' => (int) ($series['listings'][$key] ?? 0),
                'applications' => (int) ($series['applications'][$key] ?? 0),
                'messages' => (int) ($series['messages'][$key] ?? 0),
                'ratings' => (int) ($series['ratings'][$key] ?? 0),
                'reports' => (int) ($series['reports'][$key] ?? 0),
            ];
        }

        return response()->json([
            'range' => $range,
            'data' => $data,
        ]);
    }

    private function countsByDate(string $modelClass, $start, $end): array
    {
        /** @var \Illuminate\Database\Eloquent\Model $model */
        $model = new $modelClass;

        return $model->newQuery()
            ->select(DB::raw('DATE(created_at) as date'), DB::raw('COUNT(*) as count'))
            ->whereBetween('created_at', [$start, $end])
            ->groupBy(DB::raw('DATE(created_at)'))
            ->pluck('count', 'date')
            ->toArray();
    }

    private function authorizeAdmin(Request $request): void
    {
        $user = $request->user();
        abort_unless($user, 401, 'Unauthenticated');
        $isAdmin = (method_exists($user, 'hasRole') && $user->hasRole('admin')) || $user->role === 'admin';
        abort_unless($isAdmin, 403, 'Forbidden');
    }
}
