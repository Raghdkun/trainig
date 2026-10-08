<?php

namespace App\Http\Controllers\Training;

use App\Enums\ArchiveRequestStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Training\RejectArchiveRequestRequest;
use App\Http\Requests\Training\StoreArchiveRequestRequest;
use App\Models\Trainee;
use App\Models\TraineeArchiveRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Two-step archiving: a store manager asks for a trainee to be moved to the
 * Archive (with a reason), and a super admin approves or rejects it. The
 * trainee stays on the active roster until the request is approved.
 */
class TraineeArchiveRequestController extends Controller
{
    /**
     * The admin review queue — pending requests first, then recent history.
     */
    public function index(Request $request): Response
    {
        $tab = $request->query('tab') === 'history' ? 'history' : 'pending';

        $requests = TraineeArchiveRequest::query()
            ->when(
                $tab === 'pending',
                fn ($query) => $query->pending()->oldest(),
                fn ($query) => $query->where('status', '!=', ArchiveRequestStatus::Pending)->latest('reviewed_at')->limit(200),
            )
            ->with(['trainee:id,name,position,store_id', 'trainee.store:id,name', 'requester:id,name', 'reviewer:id,name'])
            ->get();

        return Inertia::render('training/archive-requests/index', [
            'requests' => $requests->map(fn (TraineeArchiveRequest $archiveRequest): array => [
                'id' => $archiveRequest->id,
                'status' => $archiveRequest->status->value,
                'reason' => $archiveRequest->reason,
                'review_note' => $archiveRequest->review_note,
                'trainee' => [
                    'id' => $archiveRequest->trainee->id,
                    'name' => $archiveRequest->trainee->name,
                    'position' => $archiveRequest->trainee->position,
                ],
                'store' => $archiveRequest->trainee->store->only(['id', 'name']),
                'requested_by' => $archiveRequest->requester?->only(['id', 'name']),
                'reviewed_by' => $archiveRequest->reviewer?->only(['id', 'name']),
                'created_at' => $archiveRequest->created_at->toIso8601String(),
                'reviewed_at' => $archiveRequest->reviewed_at?->toIso8601String(),
            ])->all(),
            'filters' => ['tab' => $tab],
            'pendingCount' => $tab === 'pending'
                ? $requests->count()
                : TraineeArchiveRequest::pending()->count(),
        ]);
    }

    /**
     * A manager asks for one of their trainees to be archived.
     */
    public function store(StoreArchiveRequestRequest $request, Trainee $trainee): RedirectResponse
    {
        $this->authorize('requestArchive', $trainee);

        $trainee->archiveRequests()->create([
            'requested_by' => $request->user()->id,
            'reason' => $request->validated('reason'),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Archive request sent for admin review.')]);

        return back();
    }

    /**
     * Approve the request and move the trainee to the Archive.
     */
    public function approve(Request $request, TraineeArchiveRequest $archiveRequest): RedirectResponse
    {
        $this->authorize('archive', $archiveRequest->trainee);

        if (! $archiveRequest->isPending()) {
            return $this->alreadyReviewed();
        }

        DB::transaction(function () use ($request, $archiveRequest): void {
            $archiveRequest->update([
                'status' => ArchiveRequestStatus::Approved,
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
            ]);

            if (! $archiveRequest->trainee->isArchived()) {
                $archiveRequest->trainee->update([
                    'archived_at' => now(),
                    'archived_by' => $request->user()->id,
                ]);
            }
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':name moved to Archive.', ['name' => $archiveRequest->trainee->name])]);

        return back();
    }

    /**
     * Reject the request — the trainee stays on the active roster, and the
     * optional note is shown to the manager on the trainee's page.
     */
    public function reject(RejectArchiveRequestRequest $request, TraineeArchiveRequest $archiveRequest): RedirectResponse
    {
        $this->authorize('archive', $archiveRequest->trainee);

        if (! $archiveRequest->isPending()) {
            return $this->alreadyReviewed();
        }

        $archiveRequest->update([
            'status' => ArchiveRequestStatus::Rejected,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'review_note' => $request->validated('review_note'),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Archive request rejected.')]);

        return back();
    }

    private function alreadyReviewed(): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'error', 'message' => __('This request has already been reviewed.')]);

        return back();
    }
}
