<?php

namespace Tests\Feature\Training;

use App\Enums\ArchiveRequestStatus;
use App\Models\Store;
use App\Models\Trainee;
use App\Models\TraineeArchiveRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class TraineeArchiveRequestTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: User, 1: Trainee}
     */
    private function managerWithTrainee(): array
    {
        $store = Store::factory()->create();
        $manager = User::factory()->manager($store)->create();
        $trainee = Trainee::factory()->forStore($store)->create();

        return [$manager, $trainee];
    }

    public function test_manager_can_request_archive_with_a_reason_and_trainee_stays_active(): void
    {
        [$manager, $trainee] = $this->managerWithTrainee();

        $this->actingAs($manager)
            ->post(route('trainees.archive-requests.store', $trainee), ['reason' => 'Completed the trainee stage.'])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('trainee_archive_requests', [
            'trainee_id' => $trainee->id,
            'requested_by' => $manager->id,
            'reason' => 'Completed the trainee stage.',
            'status' => ArchiveRequestStatus::Pending->value,
        ]);
        $this->assertFalse($trainee->refresh()->isArchived());
    }

    public function test_reason_is_required(): void
    {
        [$manager, $trainee] = $this->managerWithTrainee();

        $this->actingAs($manager)
            ->post(route('trainees.archive-requests.store', $trainee), ['reason' => ''])
            ->assertSessionHasErrors('reason');

        $this->actingAs($manager)
            ->post(route('trainees.archive-requests.store', $trainee), ['reason' => 'abc'])
            ->assertSessionHasErrors('reason');

        $this->assertDatabaseCount('trainee_archive_requests', 0);
    }

    public function test_manager_cannot_submit_a_second_request_while_one_is_pending(): void
    {
        [$manager, $trainee] = $this->managerWithTrainee();
        TraineeArchiveRequest::factory()->for($trainee)->create(['requested_by' => $manager->id]);

        $this->actingAs($manager)
            ->post(route('trainees.archive-requests.store', $trainee), ['reason' => 'Asking again please.'])
            ->assertForbidden();

        $this->assertDatabaseCount('trainee_archive_requests', 1);
    }

    public function test_manager_can_request_again_after_a_rejection(): void
    {
        [$manager, $trainee] = $this->managerWithTrainee();
        TraineeArchiveRequest::factory()->for($trainee)->rejected()->create(['requested_by' => $manager->id]);

        $this->actingAs($manager)
            ->post(route('trainees.archive-requests.store', $trainee), ['reason' => 'Left the company.'])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, $trainee->archiveRequests()->pending()->count());
    }

    public function test_manager_cannot_request_archive_for_another_stores_trainee(): void
    {
        $manager = User::factory()->manager()->create();
        $trainee = Trainee::factory()->create();

        $this->actingAs($manager)
            ->post(route('trainees.archive-requests.store', $trainee), ['reason' => 'Not my trainee.'])
            ->assertForbidden();
    }

    public function test_manager_cannot_request_archive_for_an_already_archived_trainee(): void
    {
        $store = Store::factory()->create();
        $manager = User::factory()->manager($store)->create();
        $trainee = Trainee::factory()->forStore($store)->archived()->create();

        $this->actingAs($manager)
            ->post(route('trainees.archive-requests.store', $trainee), ['reason' => 'Already gone.'])
            ->assertForbidden();
    }

    public function test_manager_cannot_archive_directly(): void
    {
        [$manager, $trainee] = $this->managerWithTrainee();

        $this->actingAs($manager)
            ->patch(route('trainees.archive', $trainee))
            ->assertForbidden();

        $this->assertFalse($trainee->refresh()->isArchived());
    }

    public function test_admin_approval_moves_trainee_to_archive(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $archiveRequest = TraineeArchiveRequest::factory()->create();

        $this->actingAs($admin)
            ->patch(route('training.archive-requests.approve', $archiveRequest))
            ->assertSessionHasNoErrors();

        $archiveRequest->refresh();
        $this->assertSame(ArchiveRequestStatus::Approved, $archiveRequest->status);
        $this->assertSame($admin->id, $archiveRequest->reviewed_by);
        $this->assertNotNull($archiveRequest->reviewed_at);

        $trainee = $archiveRequest->trainee->refresh();
        $this->assertTrue($trainee->isArchived());
        $this->assertSame($admin->id, $trainee->archived_by);
    }

    public function test_admin_rejection_keeps_trainee_active_with_a_note(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $archiveRequest = TraineeArchiveRequest::factory()->create();

        $this->actingAs($admin)
            ->patch(route('training.archive-requests.reject', $archiveRequest), ['review_note' => 'Needs two more weeks.'])
            ->assertSessionHasNoErrors();

        $archiveRequest->refresh();
        $this->assertSame(ArchiveRequestStatus::Rejected, $archiveRequest->status);
        $this->assertSame('Needs two more weeks.', $archiveRequest->review_note);
        $this->assertFalse($archiveRequest->trainee->refresh()->isArchived());
    }

    public function test_an_already_reviewed_request_cannot_be_reviewed_again(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $archiveRequest = TraineeArchiveRequest::factory()->rejected()->create();

        $this->actingAs($admin)
            ->patch(route('training.archive-requests.approve', $archiveRequest));

        $this->assertSame(ArchiveRequestStatus::Rejected, $archiveRequest->refresh()->status);
        $this->assertFalse($archiveRequest->trainee->refresh()->isArchived());
    }

    public function test_manager_cannot_review_requests(): void
    {
        [$manager, $trainee] = $this->managerWithTrainee();
        $archiveRequest = TraineeArchiveRequest::factory()->for($trainee)->create();

        $this->actingAs($manager)->get(route('training.archive-requests.index'))->assertForbidden();
        $this->actingAs($manager)->patch(route('training.archive-requests.approve', $archiveRequest))->assertForbidden();
        $this->actingAs($manager)->patch(route('training.archive-requests.reject', $archiveRequest))->assertForbidden();

        $this->assertTrue($archiveRequest->refresh()->isPending());
    }

    public function test_admin_direct_archive_settles_a_pending_request(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $archiveRequest = TraineeArchiveRequest::factory()->create();

        $this->actingAs($admin)
            ->patch(route('trainees.archive', $archiveRequest->trainee))
            ->assertSessionHasNoErrors();

        $this->assertSame(ArchiveRequestStatus::Approved, $archiveRequest->refresh()->status);
    }

    public function test_admin_queue_lists_pending_requests_and_history(): void
    {
        $this->withoutVite();
        $admin = User::factory()->superAdmin()->create();
        $pending = TraineeArchiveRequest::factory()->create(['reason' => 'Finished training.']);
        TraineeArchiveRequest::factory()->approved()->create();
        TraineeArchiveRequest::factory()->rejected('Not yet.')->create();

        $this->actingAs($admin)
            ->get(route('training.archive-requests.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('training/archive-requests/index')
                ->has('requests', 1)
                ->where('requests.0.id', $pending->id)
                ->where('requests.0.reason', 'Finished training.')
                ->where('pendingCount', 1)
                ->where('pendingArchiveRequests', 1)
            );

        $this->actingAs($admin)
            ->get(route('training.archive-requests.index', ['tab' => 'history']))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('requests', 2)
                ->where('filters.tab', 'history')
            );
    }

    public function test_trainee_pages_show_pending_request_state(): void
    {
        $this->withoutVite();
        [$manager, $trainee] = $this->managerWithTrainee();
        TraineeArchiveRequest::factory()->for($trainee)->create(['requested_by' => $manager->id, 'reason' => 'Done.']);

        $this->actingAs($manager)
            ->get(route('trainees.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('trainees.0.archive_pending', true)
                ->where('pendingArchiveRequests', null)
            );

        $this->actingAs($manager)
            ->get(route('trainees.show', $trainee))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('archiveRequest.status', 'pending')
                ->where('archiveRequest.reason', 'Done.')
                ->where('canRequestArchive', false)
                ->where('canArchive', false)
            );
    }

    public function test_manager_sees_request_option_and_admin_sees_direct_archive(): void
    {
        $this->withoutVite();
        [$manager, $trainee] = $this->managerWithTrainee();
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($manager)
            ->get(route('trainees.show', $trainee))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('archiveRequest', null)
                ->where('canRequestArchive', true)
                ->where('canArchive', false)
            );

        $this->actingAs($admin)
            ->get(route('trainees.show', $trainee))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('canRequestArchive', false)
                ->where('canArchive', true)
            );
    }
}
