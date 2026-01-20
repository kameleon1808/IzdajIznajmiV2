<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\AdminReportResource;
use App\Models\Listing;
use App\Models\Message;
use App\Models\Rating;
use App\Models\Report;
use App\Models\User;
use App\Services\AuditLogService;
use App\Events\ReportUpdated;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

class ModerationController extends Controller
{
    public function __construct(private AuditLogService $auditLog)
    {
    }

    public function queue(Request $request): JsonResponse
    {
        $admin = $this->authorizeAdmin($request);

        $type = $this->resolveTargetClass($request->query('type'));
        $status = $request->query('status');
        $search = $request->query('q');

        $query = Report::query()
            ->select('reports.*')
            ->selectRaw('(SELECT COUNT(*) FROM reports r WHERE r.target_type = reports.target_type AND r.target_id = reports.target_id) as total_reports')
            ->with('reporter:id,name,full_name')
            ->latest();

        if ($type) {
            $query->where('target_type', $type);
        }

        if ($status && in_array($status, ['open', 'resolved', 'dismissed'], true)) {
            $query->where('status', $status);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('reason', 'like', '%' . $search . '%')
                    ->orWhere('details', 'like', '%' . $search . '%');
            });
        }

        $reports = $query->paginate(15);
        $this->attachTargetSummaries(collect($reports->items()));

        return AdminReportResource::collection($reports)->response();
    }

    public function show(Request $request, Report $report): JsonResponse
    {
        $this->authorizeAdmin($request);
        $report->load('reporter:id,name,full_name');
        $report->setAttribute('total_reports', Report::where('target_type', $report->target_type)->where('target_id', $report->target_id)->count());
        $this->attachTargetSummaries(collect([$report]));

        return response()->json(new AdminReportResource($report));
    }

    public function update(Request $request, Report $report): JsonResponse
    {
        $admin = $this->authorizeAdmin($request);

        $data = $request->validate([
            'action' => ['required', Rule::in(['dismiss', 'resolve'])],
            'resolution' => ['nullable', 'string', 'max:255'],
            'delete_target' => ['sometimes', 'boolean'],
            'flag_user_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        if (!empty($data['delete_target'])) {
            $this->deleteTarget($report);
        }

        if (!empty($data['flag_user_id'])) {
            $user = User::find($data['flag_user_id']);
            if ($user) {
                $user->is_suspicious = true;
                $user->save();
            }
        }

        $report->status = $data['action'] === 'dismiss' ? 'dismissed' : 'resolved';
        $report->resolution = $data['resolution'] ?? $report->status;
        $report->reviewed_by = $admin->id;
        $report->reviewed_at = now();
        $report->save();

        event(new ReportUpdated($report));

        $this->auditLog->record(
            $admin->id,
            'report.' . $report->status,
            $report->target_type,
            $report->target_id,
            [
                'report_id' => $report->id,
                'delete_target' => (bool) ($data['delete_target'] ?? false),
                'flag_user_id' => $data['flag_user_id'] ?? null,
            ]
        );

        $report->load('reporter:id,name,full_name');
        $this->attachTargetSummaries(collect([$report]));

        return response()->json(new AdminReportResource($report));
    }

    private function attachTargetSummaries(Collection $reports): void
    {
        $grouped = $reports->groupBy('target_type');

        if ($ratings = $grouped->get(Rating::class)) {
            $targets = Rating::with(['rater:id,name,full_name', 'ratee:id,name,full_name', 'listing:id,title'])
                ->whereIn('id', $ratings->pluck('target_id'))
                ->get()
                ->keyBy('id');

            foreach ($ratings as $report) {
                $rating = $targets->get($report->target_id);
                $report->setAttribute('target_summary', $rating ? [
                    'id' => $rating->id,
                    'rating' => (int) $rating->rating,
                    'comment' => $rating->comment,
                    'listingTitle' => $rating->listing?->title,
                    'rater' => [
                        'id' => $rating->rater?->id,
                        'name' => $rating->rater?->full_name ?? $rating->rater?->name,
                    ],
                    'ratee' => [
                        'id' => $rating->ratee?->id,
                        'name' => $rating->ratee?->full_name ?? $rating->ratee?->name,
                    ],
                ] : null);
            }
        }

        if ($messages = $grouped->get(Message::class)) {
            $targets = Message::with([
                'sender:id,name,full_name',
                'conversation.listing:id,title',
            ])
                ->whereIn('id', $messages->pluck('target_id'))
                ->get()
                ->keyBy('id');

            foreach ($messages as $report) {
                $message = $targets->get($report->target_id);
                $report->setAttribute('target_summary', $message ? [
                    'id' => $message->id,
                    'body' => $message->body,
                    'sender' => [
                        'id' => $message->sender?->id,
                        'name' => $message->sender?->full_name ?? $message->sender?->name,
                    ],
                    'listingTitle' => $message->conversation?->listing?->title,
                ] : null);
            }
        }

        if ($listings = $grouped->get(Listing::class)) {
            $targets = Listing::select('id', 'title', 'city', 'status')
                ->whereIn('id', $listings->pluck('target_id'))
                ->get()
                ->keyBy('id');

            foreach ($listings as $report) {
                $listing = $targets->get($report->target_id);
                $report->setAttribute('target_summary', $listing ? [
                    'id' => $listing->id,
                    'title' => $listing->title,
                    'city' => $listing->city,
                    'status' => $listing->status,
                ] : null);
            }
        }
    }

    private function deleteTarget(Report $report): void
    {
        if ($report->target_type === Rating::class) {
            $target = Rating::find($report->target_id);
            $target?->delete();
        }

        if ($report->target_type === Message::class) {
            $target = Message::find($report->target_id);
            $target?->delete();
        }

        if ($report->target_type === Listing::class) {
            $target = Listing::find($report->target_id);
            if ($target) {
                $target->status = 'archived';
                $target->archived_at = now();
                $target->save();
            }
        }
    }

    private function resolveTargetClass(?string $type): ?string
    {
        return match ($type) {
            'rating' => Rating::class,
            'message' => Message::class,
            'listing' => Listing::class,
            default => null,
        };
    }

    private function authorizeAdmin(Request $request): User
    {
        $user = $request->user();
        abort_unless($user, 401, 'Unauthenticated');
        $isAdmin = (method_exists($user, 'hasRole') && $user->hasRole('admin')) || $user->role === 'admin';
        abort_unless($isAdmin, 403, 'Forbidden');

        return $user;
    }
}
