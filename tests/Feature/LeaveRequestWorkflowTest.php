<?php

namespace Tests\Feature;

use App\Models\DocumentRequest;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LeaveRequestWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_can_submit_a_leave_request_with_a_private_signature(): void
    {
        $this->travelTo('2026-11-06 09:00:00');
        Storage::fake('local');
        $employee = $this->createUserWithRole('employee');
        $payload = $this->validPayload();
        $payload['employee_name'] = $employee->name;

        $this->actingAs($employee)
            ->post('/leave-requests', $payload)
            ->assertRedirect();

        $leaveRequest = DocumentRequest::query()->firstOrFail();

        $this->assertDatabaseHas('document_requests', [
            'id' => $leaveRequest->id,
            'user_id' => $employee->id,
            'type' => 'cuti',
            'status' => 'diajukan',
            'department' => 'Teknologi Informasi',
            'employee_name' => $employee->name,
            'leave_entitlement_year' => 2026,
        ]);
        Storage::disk('local')->assertExists($leaveRequest->employee_signature_path);
        $this->assertDatabaseHas('document_request_histories', [
            'document_request_id' => $leaveRequest->id,
            'action' => 'diajukan',
            'new_status' => 'diajukan',
        ]);

        $this->actingAs($employee)
            ->get(route('leave-requests.signature', [$leaveRequest, 'employee']))
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_employee_cannot_view_another_employees_leave_request_or_signature(): void
    {
        Storage::fake('local');
        $owner = $this->createUserWithRole('employee');
        $otherEmployee = $this->createUserWithRole('employee');
        $leaveRequest = $this->createLeaveRequest($owner);
        $signaturePath = 'leave-signatures/private.png';
        Storage::disk('local')->put($signaturePath, 'private image');
        $leaveRequest->update(['employee_signature_path' => $signaturePath]);

        $this->actingAs($otherEmployee)
            ->get(route('leave-requests.show', $leaveRequest))
            ->assertNotFound();

        $this->actingAs($otherEmployee)
            ->get(route('leave-requests.signature', [$leaveRequest, 'employee']))
            ->assertNotFound();
    }

    public function test_karyawan_can_open_their_leave_requests_and_revision_form(): void
    {
        $karyawan = $this->createUserWithRole('karyawan');
        $manager = $this->createUserWithRole('manager');
        $leaveRequest = $this->createLeaveRequest($karyawan);
        $leaveRequest->update(['status' => 'perlu_revisi']);

        $this->actingAs($karyawan)
            ->get(route('leave-requests.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('LeaveRequests/Index')
                ->where('requests.data.0.id', $leaveRequest->id)
            );

        $this->get(route('leave-requests.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('LeaveRequests/Form')
            );

        $this->get(route('leave-requests.show', $leaveRequest))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('LeaveRequests/Show')
                ->where('canEdit', true)
                ->where('request.manager_name', $manager->name)
            );

        $this->get(route('leave-requests.edit', $leaveRequest))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('LeaveRequests/Form')
            );
    }

    public function test_karyawan_can_submit_a_leave_request(): void
    {
        $this->travelTo('2026-11-06 09:00:00');
        Storage::fake('local');
        $karyawan = $this->createUserWithRole('karyawan');
        $payload = $this->validPayload();
        $payload['employee_name'] = 'Budi Santoso';

        $response = $this->actingAs($karyawan)
            ->post(route('leave-requests.store'), $payload);

        $leaveRequest = DocumentRequest::query()->firstOrFail();

        $response->assertRedirect(route('leave-requests.show', $leaveRequest));
        $this->assertDatabaseHas('document_requests', [
            'id' => $leaveRequest->id,
            'user_id' => $karyawan->id,
            'type' => 'cuti',
            'status' => 'diajukan',
            'employee_name' => 'Budi Santoso',
        ]);
        Storage::disk('local')->assertExists($leaveRequest->employee_signature_path);

        $this->actingAs($karyawan)
            ->get(route('leave-requests.show', $leaveRequest))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('LeaveRequests/Show')
                ->where('request.employee_name', 'Budi Santoso')
                ->where('request.submitted_by_name', $karyawan->name)
            );
    }

    public function test_leave_request_validation_rejects_missing_details_and_invalid_signature(): void
    {
        $this->travelTo('2026-11-06 09:00:00');
        Storage::fake('local');
        $employee = $this->createUserWithRole('employee');

        $this->actingAs($employee)
            ->from('/leave-requests/create')
            ->post('/leave-requests', [])
            ->assertSessionHasErrors([
                'employee_name',
                'department',
                'work_location',
                'start_date',
                'end_date',
                'destination',
                'reason',
                'leave_entitlement_year',
                'leave_entitlement_days',
                'leave_taken_days',
                'transportation',
                'expense_paid_by',
                'return_to_work_date',
                'employee_signature',
            ]);

        $invalidSignature = $this->validPayload();
        $invalidSignature['employee_signature'] = 'data:image/png;base64,not-a-png';

        $this->actingAs($employee)
            ->from('/leave-requests/create')
            ->post('/leave-requests', $invalidSignature)
            ->assertSessionHasErrors('employee_signature');

        $this->assertDatabaseCount('document_requests', 0);
    }

    public function test_unauthenticated_visitors_are_redirected_to_login_for_leave_pages(): void
    {
        $this->get('/leave-requests')
            ->assertRedirect('/login');

        $this->get('/leave-reviews')
            ->assertRedirect('/login');
    }

    public function test_leave_dates_and_taken_leave_must_be_within_valid_ranges(): void
    {
        $this->travelTo('2026-11-06 09:00:00');
        $employee = $this->createUserWithRole('employee');
        $payload = $this->validPayload();

        $payload['end_date'] = '2026-11-30';
        $this->actingAs($employee)
            ->from('/leave-requests/create')
            ->post('/leave-requests', $payload)
            ->assertSessionHasErrors([
                'end_date' => 'Tanggal selesai harus sama atau setelah tanggal mulai.',
            ]);

        $payload = $this->validPayload();
        $payload['leave_taken_days'] = 13;
        $this->actingAs($employee)
            ->from('/leave-requests/create')
            ->post('/leave-requests', $payload)
            ->assertSessionHasErrors([
                'leave_taken_days' => 'Jumlah cuti yang telah diambil tidak boleh melebihi hak cuti.',
            ]);

        $payload = $this->validPayload();
        $payload['return_to_work_date'] = $payload['end_date'];
        $this->actingAs($employee)
            ->from('/leave-requests/create')
            ->post('/leave-requests', $payload)
            ->assertSessionHasErrors([
                'return_to_work_date' => 'Tanggal masuk kerja kembali harus setelah tanggal selesai cuti.',
            ]);

        $this->assertDatabaseCount('document_requests', 0);
    }

    public function test_manager_cannot_use_employee_leave_submission_routes(): void
    {
        $manager = $this->createUserWithRole('manager');

        $this->actingAs($manager)
            ->get('/leave-requests/create')
            ->assertForbidden();

        $this->actingAs($manager)
            ->post('/leave-requests', $this->validPayload())
            ->assertForbidden();

        $this->assertDatabaseCount('document_requests', 0);
    }

    public function test_administrator_can_read_leave_data_but_cannot_submit_or_change_it(): void
    {
        $this->travelTo('2026-11-07 10:00:00');
        $admin = $this->createUserWithRole('admin');
        $manager = $this->createUserWithRole('manager');
        $employee = $this->createUserWithRole('employee');
        $leaveRequest = $this->createLeaveRequest($employee);
        $leaveRequest->employee_name = 'Pemohon Contoh';
        $leaveRequest->save();

        $this->actingAs($admin)
            ->get('/leave-administration?search=Pemohon&status=diajukan&month=11&year=2026')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('LeaveRequests/Administration')
                ->where('requests.data.0.id', $leaveRequest->id)
                ->where('filters.search', 'Pemohon')
                ->where('filters.status', 'diajukan')
                ->where('filters.month', 11)
                ->where('filters.year', 2026)
                ->where('periodSummary.submitted', 1)
                ->where('periodSummary.processed', 0)
                ->where('periodSummary.period', 'Triwulan IV 2026 (Oktober–Desember)')
            );

        $this->actingAs($admin)
            ->get(route('leave-requests.show', $leaveRequest))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('LeaveRequests/Show')
                ->where('readOnly', true)
                ->where('canEdit', false)
                ->where('canReview', false)
                ->where('request.manager_name', $manager->name)
            );

        $this->actingAs($admin)
            ->get('/leave-requests/create')
            ->assertForbidden();

        $this->actingAs($admin)
            ->post("/leave-reviews/{$leaveRequest->id}", [
                'action' => 'disetujui',
                'notes' => 'Disetujui.',
                'manager_signature' => $this->signatureData(),
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('document_requests', [
            'id' => $leaveRequest->id,
            'status' => 'diajukan',
            'reviewed_by' => null,
        ]);
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
