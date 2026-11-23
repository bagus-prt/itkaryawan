<?php

namespace Tests\Feature;

use App\Models\DocumentRequest;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LeaveReviewControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_return_a_request_then_approve_the_resubmission(): void
    {
        $this->travelTo('2026-11-06 09:00:00');
        Storage::fake('local');
        $employee = $this->createUserWithRole('employee');
        $manager = $this->createUserWithRole('manager');

        $this->actingAs($employee)
            ->post('/leave-requests', $this->validPayload())
            ->assertRedirect();

        $leaveRequest = DocumentRequest::query()->firstOrFail();

        $this->actingAs($manager)
            ->get('/leave-reviews')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('LeaveReviews/Index')
                ->where('requests.data.0.id', $leaveRequest->id)
                ->where('requests.data.0.status', 'diajukan')
            );

        $this->actingAs($manager)
            ->get(route('leave-requests.show', $leaveRequest))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('LeaveRequests/Show')
                ->where('request.manager_name', $manager->name)
            );

        $this->actingAs($manager)
            ->post("/leave-reviews/{$leaveRequest->id}", [
                'action' => 'perlu_revisi',
                'notes' => 'Mohon lengkapi keterangan tujuan.',
            ])
            ->assertRedirect("/leave-requests/{$leaveRequest->id}");

        $this->assertDatabaseHas('document_requests', [
            'id' => $leaveRequest->id,
            'status' => 'perlu_revisi',
            'reviewed_by' => $manager->id,
            'review_notes' => 'Mohon lengkapi keterangan tujuan.',
        ]);
        $this->assertDatabaseHas('document_request_histories', [
            'document_request_id' => $leaveRequest->id,
            'action' => 'perlu_revisi',
            'notes' => 'Mohon lengkapi keterangan tujuan.',
        ]);

        $this->actingAs($employee)
            ->get("/leave-requests/{$leaveRequest->id}/edit")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('LeaveRequests/Form')
                ->where('request.id', $leaveRequest->id)
            );

        $revisedPayload = $this->validPayload();
        $revisedPayload['reason'] = 'Keperluan keluarga dan tujuan perjalanan telah dilengkapi.';

        $this->actingAs($employee)
            ->put("/leave-requests/{$leaveRequest->id}", $revisedPayload)
            ->assertRedirect("/leave-requests/{$leaveRequest->id}");

        $this->assertDatabaseHas('document_requests', [
            'id' => $leaveRequest->id,
            'status' => 'diajukan',
            'reviewed_by' => null,
            'review_notes' => null,
            'reviewer_name_snapshot' => null,
            'reason' => 'Keperluan keluarga dan tujuan perjalanan telah dilengkapi.',
        ]);
        $this->assertDatabaseHas('document_request_histories', [
            'document_request_id' => $leaveRequest->id,
            'action' => 'diajukan_kembali',
            'old_status' => 'perlu_revisi',
            'new_status' => 'diajukan',
        ]);

        $this->actingAs($manager)
            ->post("/leave-reviews/{$leaveRequest->id}", [
                'action' => 'disetujui',
                'notes' => 'Data telah diperiksa.',
                'manager_signature' => $this->signatureData(),
            ])
            ->assertRedirect("/leave-requests/{$leaveRequest->id}");

        $approvedRequest = $leaveRequest->fresh();
        $this->assertSame('disetujui', $approvedRequest->status);
        $this->assertSame($manager->id, $approvedRequest->reviewed_by);
        $this->assertSame(
            'CUTI/2026/'.str_pad((string) $leaveRequest->id, 6, '0', STR_PAD_LEFT),
            $approvedRequest->letter_number
        );
        $this->assertTrue(Storage::disk('local')->exists($approvedRequest->manager_signature_path));
        $this->assertDatabaseHas('document_request_histories', [
            'document_request_id' => $leaveRequest->id,
            'action' => 'disetujui',
            'notes' => 'Data telah diperiksa.',
        ]);

        $this->actingAs($employee)
            ->get("/leave-requests/{$leaveRequest->id}/letter")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('LeaveRequests/Letter')
                ->where('request.letter_number', $approvedRequest->letter_number)
                ->where('request.leave_requested_days', 3)
                ->where('request.leave_remaining_days', 6)
                ->where('request.submission_date', '06/11/2026')
                ->where('request.submitted_by_name', $employee->name)
                ->where('request.manager_name', $manager->name)
                ->where('request.manager_signature_url', route('leave-requests.signature', [$leaveRequest, 'manager']))
            );

        $this->actingAs($manager)
            ->post("/leave-reviews/{$leaveRequest->id}", [
                'action' => 'ditolak',
                'notes' => 'Permohonan sudah diputus.',
            ])
            ->assertForbidden();
    }

    public function test_manager_cannot_approve_without_a_note_and_a_valid_signature(): void
    {
        Storage::fake('local');
        $employee = $this->createUserWithRole('employee');
        $manager = $this->createUserWithRole('manager');
        $leaveRequest = $this->createLeaveRequest($employee);

        $this->actingAs($manager)
            ->from("/leave-requests/{$leaveRequest->id}")
            ->post("/leave-reviews/{$leaveRequest->id}", [
                'action' => 'disetujui',
                'notes' => '',
                'manager_signature' => '',
            ])
            ->assertSessionHasErrors(['notes', 'manager_signature']);

        $this->assertDatabaseHas('document_requests', [
            'id' => $leaveRequest->id,
            'status' => 'diajukan',
            'reviewed_by' => null,
            'manager_signature_path' => null,
        ]);
    }

    public function test_employee_cannot_open_or_post_to_manager_review_queue(): void
    {
        $employee = $this->createUserWithRole('employee');
        $owner = $this->createUserWithRole('employee');
        $leaveRequest = $this->createLeaveRequest($owner);

        $this->actingAs($employee)
            ->get('/leave-reviews')
            ->assertForbidden();

        $this->actingAs($employee)
            ->post("/leave-reviews/{$leaveRequest->id}", [
                'action' => 'disetujui',
                'notes' => 'Disetujui.',
                'manager_signature' => $this->signatureData(),
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('document_requests', [
            'id' => $leaveRequest->id,
            'status' => 'diajukan',
        ]);
    }

    public function test_manager_can_reject_a_request_with_a_review_note(): void
    {
        $employee = $this->createUserWithRole('employee');
        $manager = $this->createUserWithRole('manager');
        $leaveRequest = $this->createLeaveRequest($employee);

        $this->actingAs($manager)
            ->post("/leave-reviews/{$leaveRequest->id}", [
                'action' => 'ditolak',
                'notes' => 'Jadwal cuti belum dapat disetujui.',
            ])
            ->assertRedirect("/leave-requests/{$leaveRequest->id}");

        $this->assertDatabaseHas('document_requests', [
            'id' => $leaveRequest->id,
            'status' => 'ditolak',
            'reviewed_by' => $manager->id,
            'review_notes' => 'Jadwal cuti belum dapat disetujui.',
            'manager_signature_path' => null,
        ]);
        $this->assertDatabaseHas('document_request_histories', [
            'document_request_id' => $leaveRequest->id,
            'action' => 'ditolak',
            'notes' => 'Jadwal cuti belum dapat disetujui.',
        ]);
    }

    public function test_manager_can_search_and_filter_requests_and_sees_current_quarter_summary(): void
    {
        $this->travelTo('2026-11-07 10:00:00');
        $manager = $this->createUserWithRole('manager');
        $employee = $this->createUserWithRole('employee');
        $matchingRequest = $this->createLeaveRequest($employee);
        $matchingRequest->employee_name = 'Budi Santoso';
        $matchingRequest->department = 'Teknologi Informasi';
        $matchingRequest->destination = 'Jakarta';
        $matchingRequest->status = 'disetujui';
        $matchingRequest->created_at = now()->subDays(2);
        $matchingRequest->save();
        $matchingRequest->histories()->create([
            'actor_id' => $manager->id,
            'action' => 'disetujui',
            'new_status' => 'disetujui',
        ]);
        $matchingRequest->histories()->create([
            'actor_id' => $manager->id,
            'action' => 'perlu_revisi',
            'new_status' => 'perlu_revisi',
        ]);

        $otherRequest = $this->createLeaveRequest($employee);
        $otherRequest->employee_name = 'Siti Aminah';
        $otherRequest->status = 'ditolak';
        $otherRequest->created_at = now()->subDays(3);
        $otherRequest->save();
        $otherRequest->histories()->create([
            'actor_id' => $manager->id,
            'action' => 'ditolak',
            'new_status' => 'ditolak',
        ]);

        $oldRequest = $this->createLeaveRequest($employee);
        $oldRequest->created_at = now()->subMonths(4);
        $oldRequest->save();
        $oldHistory = $oldRequest->histories()->create([
            'actor_id' => $manager->id,
            'action' => 'perlu_revisi',
            'new_status' => 'perlu_revisi',
        ]);
        $oldHistory->created_at = now()->subMonths(4);
        $oldHistory->save();

        $this->actingAs($manager)
            ->get('/leave-reviews?search=Budi&status=disetujui&month=11&year=2026')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('LeaveReviews/Index')
                ->where('requests.total', 1)
                ->where('requests.data.0.id', $matchingRequest->id)
                ->where('filters.search', 'Budi')
                ->where('filters.status', 'disetujui')
                ->where('filters.month', 11)
                ->where('filters.year', 2026)
                ->where('periodSummary.submitted', 2)
                ->where('periodSummary.processed', 2)
                ->where('periodSummary.period', 'Triwulan IV 2026 (Oktober–Desember)')
            );

        $this->travelTo('2027-01-01 00:00:00');

        $this->actingAs($manager)
            ->get('/leave-reviews')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('LeaveReviews/Index')
                ->where('periodSummary.submitted', 0)
                ->where('periodSummary.processed', 0)
                ->where('periodSummary.period', 'Triwulan I 2027 (Januari–Maret)')
            );
    }

    /**
     * @return array<string, mixed>
     */
    private function validPayload(): array
    {
        return [
            'employee_name' => 'Pemohon Contoh',
            'department' => 'Teknologi Informasi',
            'work_location' => 'Banjarmasin',
            'start_date' => '2026-12-01',
            'end_date' => '2026-12-03',
            'destination' => 'Jakarta',
            'reason' => 'Keperluan keluarga.',
            'leave_entitlement_year' => 2026,
            'leave_entitlement_days' => 12,
            'leave_taken_days' => 3,
            'transportation' => 'Pesawat',
            'expense_paid_by' => 'Pribadi',
            'return_to_work_date' => '2026-12-04',
            'leave_due_date' => '2026-12-10',
            'companions' => [
                ['name' => 'Ayu', 'notes' => 'Keluarga'],
                ['name' => '', 'notes' => ''],
                ['name' => '', 'notes' => ''],
            ],
            'employee_signature' => $this->signatureData(),
        ];
    }

    private function signatureData(): string
    {
        return 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/U3sAAAAASUVORK5CYII=';
    }

    private function createUserWithRole(string $roleName): User
    {
        $role = Role::query()->firstOrCreate(
            ['name' => $roleName],
            ['label' => ucfirst($roleName)]
        );

        return User::factory()->create([
            'role_id' => $role->id,
            'is_active' => true,
        ]);
    }

    private function createLeaveRequest(User $employee): DocumentRequest
    {
        return DocumentRequest::query()->create([
            'user_id' => $employee->id,
            'type' => 'cuti',
            'reason' => 'Keperluan keluarga.',
            'status' => 'diajukan',
        ]);
    }
}
