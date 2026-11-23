<?php

namespace Tests\Feature;

use App\Models\PtppProgressUpdate;
use App\Models\PtppRequest;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PtppWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_submits_ptpp_for_manager_approval_and_recipient_cannot_view_it_yet(): void
    {
        $this->travelTo('2026-11-12 10:30:00');
        Storage::fake('local');
        $submitter = $this->createUserWithRole('employee');
        $recipient = $this->createUserWithRole('employee');
        $manager = $this->createUserWithRole('manager');

        $response = $this->actingAs($submitter)
            ->post(route('ptpp.store'), [
                ...$this->validPayload($recipient),
                'illustration' => $this->fakeImage('temuan.png'),
            ]);

        $ptppRequest = PtppRequest::query()->firstOrFail();

        $response->assertRedirect(route('ptpp.show', $ptppRequest));
        Storage::disk('local')->assertExists($ptppRequest->sender_signature_path);
        $this->assertDatabaseHas('ptpp_requests', [
            'id' => $ptppRequest->id,
            'ptpp_number' => 'PTPP-016/PPNLM0204/2026-55',
            'user_id' => $submitter->id,
            'recipient_id' => $recipient->id,
            'approval_status' => 'menunggu_persetujuan',
            'work_status' => 'menunggu_diterima',
            'to_function' => 'Fajar / Operasional',
            'sender_signature_name' => 'Bagus Pratama',
            'sender_short_name' => 'Bagus',
        ]);
        $this->assertSame('12/11/2026', $ptppRequest->created_at?->format('d/m/Y'));
        Storage::disk('local')->assertExists($ptppRequest->illustration_path);
        $this->assertDatabaseHas('ptpp_request_histories', [
            'ptpp_request_id' => $ptppRequest->id,
            'actor_id' => $submitter->id,
            'action' => 'diajukan',
        ]);

        $this->actingAs($submitter)
            ->get(route('ptpp.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Ptpp/Index')
                ->where('requests.data.0.id', $ptppRequest->id)
                ->where('pendingCount', 0)
            );

        $this->actingAs($recipient)
            ->get(route('ptpp.show', $ptppRequest))
            ->assertNotFound();

        $this->actingAs($manager)
            ->get(route('ptpp.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Ptpp/Index')
                ->where('requests.data.0.id', $ptppRequest->id)
                ->where('requests.data.0.approval_status', 'menunggu_persetujuan')
                ->where('pendingCount', 1)
            );
    }

    public function test_manager_approval_makes_the_letter_visible_to_its_recipient(): void
    {
        $this->travelTo('2026-11-12 10:30:00');
        $submitter = $this->createUserWithRole('employee');
        $recipient = $this->createUserWithRole('employee');
        $manager = $this->createUserWithRole('manager');
        $ptppRequest = $this->createPtppRequest($submitter, $recipient);
        Storage::fake('local');
        $illustrationPath = 'ptpp/illustrations/temuan.png';
        Storage::disk('local')->put($illustrationPath, $this->imageContent());
        $ptppRequest->update(['illustration_path' => $illustrationPath]);

        $this->actingAs($manager)
            ->post(route('ptpp.review', $ptppRequest), [
                'action' => 'disetujui',
                'notes' => 'Data dan tujuan sudah diperiksa.',
                'reviewer_signature' => $this->signatureData(),
            ])
            ->assertRedirect(route('ptpp.show', $ptppRequest));

        $this->assertDatabaseHas('ptpp_requests', [
            'id' => $ptppRequest->id,
            'approval_status' => 'disetujui',
            'work_status' => 'menunggu_diterima',
            'reviewed_by' => $manager->id,
            'reviewer_name_snapshot' => $manager->name,
        ]);
        $ptppRequest->refresh();
        Storage::disk('local')->assertExists($ptppRequest->reviewer_signature_path);
        $this->assertDatabaseHas('ptpp_request_histories', [
            'ptpp_request_id' => $ptppRequest->id,
            'action' => 'disetujui',
            'notes' => 'Data dan tujuan sudah diperiksa.',
        ]);

        $this->actingAs($recipient)
            ->get(route('ptpp.show', $ptppRequest))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Ptpp/Show')
                ->where('ptppRequest.manager_name', $manager->name)
                ->where('ptppRequest.reviewer_signature_url', route('ptpp.signature', [$ptppRequest, 'reviewer']))
                ->where('backUrl', route('ptpp.index', ['tab' => 'masuk']))
                ->where('canAccept', true)
            );

        $this->actingAs($recipient)
            ->get(route('ptpp.letter', $ptppRequest))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Ptpp/Letter')
                ->where('ptppRequest.ptpp_number', 'PTPP-016/PPNLM0204/2026-55')
                ->where('ptppRequest.recipient_name', $recipient->name)
                ->where('ptppRequest.submission_date', now()->format('d/m/Y'))
            );

        $this->actingAs($recipient)
            ->get(route('ptpp.illustration', $ptppRequest))
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private');

        $this->actingAs($recipient)
            ->get(route('ptpp.signature', [$ptppRequest, 'reviewer']))
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_employee_can_view_submitted_and_approved_incoming_ptpps_in_separate_lists(): void
    {
        $employee = $this->createUserWithRole('employee');
        $outgoingRecipient = $this->createUserWithRole('employee');
        $incomingSubmitter = $this->createUserWithRole('employee');
        $pendingSubmitter = $this->createUserWithRole('employee');
        $manager = $this->createUserWithRole('manager');

        $outgoingRequest = $this->createPtppRequest($employee, $outgoingRecipient);
        $outgoingRequest->update(['ptpp_number' => 'PTPP-OUTGOING']);
        $incomingRequest = $this->createPtppRequest($incomingSubmitter, $employee, 'disetujui', $manager);
        $incomingRequest->update(['ptpp_number' => 'PTPP-INCOMING']);
        $pendingIncomingRequest = $this->createPtppRequest($pendingSubmitter, $employee);
        $pendingIncomingRequest->update(['ptpp_number' => 'PTPP-PENDING']);

        $this->actingAs($employee)
            ->get(route('ptpp.index', ['tab' => 'diajukan']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('requests.total', 1)
                ->where('requests.data.0.id', $outgoingRequest->id)
            );

        $this->actingAs($employee)
            ->get(route('ptpp.index', ['tab' => 'masuk']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('requests.total', 1)
                ->where('requests.data.0.id', $incomingRequest->id)
            );
    }

    public function test_manager_can_return_a_ptpp_for_revision_and_owner_can_resubmit_it(): void
    {
        $submitter = $this->createUserWithRole('employee');
        $recipient = $this->createUserWithRole('employee');
        $manager = $this->createUserWithRole('manager');
        $ptppRequest = $this->createPtppRequest($submitter, $recipient);

        $this->actingAs($manager)
            ->post(route('ptpp.review', $ptppRequest), [
                'action' => 'perlu_revisi',
                'notes' => 'Mohon lengkapi uraian temuan.',
            ])
            ->assertRedirect(route('ptpp.show', $ptppRequest));

        $this->assertDatabaseHas('ptpp_requests', [
            'id' => $ptppRequest->id,
            'approval_status' => 'perlu_revisi',
            'review_notes' => 'Mohon lengkapi uraian temuan.',
        ]);

        $this->actingAs($submitter)
            ->get(route('ptpp.edit', $ptppRequest))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Ptpp/Form')
                ->where('ptppRequest.id', $ptppRequest->id)
            );

        $payload = $this->validPayload($recipient);
        $payload['description'] = 'Uraian temuan sudah dilengkapi oleh pengaju.';

        $this->actingAs($submitter)
            ->put(route('ptpp.update', $ptppRequest), $payload)
            ->assertRedirect(route('ptpp.show', $ptppRequest));

        $this->assertDatabaseHas('ptpp_requests', [
            'id' => $ptppRequest->id,
            'approval_status' => 'menunggu_persetujuan',
            'reviewed_by' => null,
            'review_notes' => null,
            'description' => $payload['description'],
        ]);
        $this->assertDatabaseHas('ptpp_request_histories', [
            'ptpp_request_id' => $ptppRequest->id,
            'action' => 'diajukan_kembali',
            'old_approval_status' => 'perlu_revisi',
            'new_approval_status' => 'menunggu_persetujuan',
        ]);
    }

    public function test_recipient_accepts_the_work_posts_multiple_updates_and_marks_it_complete(): void
    {
        Storage::fake('local');
        $submitter = $this->createUserWithRole('employee');
        $recipient = $this->createUserWithRole('employee');
        $manager = $this->createUserWithRole('manager');
        $ptppRequest = $this->createPtppRequest($submitter, $recipient, 'disetujui', $manager);

        $this->actingAs($recipient)
            ->patch(route('ptpp.accept', $ptppRequest))
            ->assertRedirect(route('ptpp.show', $ptppRequest));

        $this->assertDatabaseHas('ptpp_requests', [
            'id' => $ptppRequest->id,
            'approval_status' => 'disetujui',
            'work_status' => 'dalam_pengerjaan',
        ]);

        $this->actingAs($recipient)
            ->post(route('ptpp.updates.store', $ptppRequest), [
                'notes' => 'Pengecekan awal selesai.',
            ])
            ->assertRedirect(route('ptpp.show', $ptppRequest));

        $this->actingAs($recipient)
            ->post(route('ptpp.updates.store', $ptppRequest), [
                'notes' => 'Perbaikan selesai dan sudah didokumentasikan.',
                'photo' => $this->fakeImage('selesai.png'),
                'finish' => true,
            ])
            ->assertRedirect(route('ptpp.show', $ptppRequest));

        $ptppRequest->refresh();
        $updates = PtppProgressUpdate::query()
            ->where('ptpp_request_id', $ptppRequest->id)
            ->orderBy('id')
            ->get();

        $this->assertCount(2, $updates);
        $this->assertSame('selesai', $ptppRequest->work_status);
        $this->assertSame(100, $ptppRequest->progress_percent);
        $this->assertSame($recipient->id, $ptppRequest->verified_by);
        Storage::disk('local')->assertExists($updates[1]->photo_path);
        $this->assertDatabaseHas('ptpp_request_histories', [
            'ptpp_request_id' => $ptppRequest->id,
            'action' => 'pekerjaan_selesai',
            'new_work_status' => 'selesai',
        ]);

        $this->actingAs($manager)
            ->get(route('ptpp.show', $ptppRequest))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('ptppRequest.work_status', 'selesai')
                ->has('ptppRequest.progress_updates', 2)
            );
    }

    public function test_non_manager_cannot_approve_and_unrelated_user_cannot_view_ptpp(): void
    {
        $submitter = $this->createUserWithRole('employee');
        $recipient = $this->createUserWithRole('employee');
        $otherUser = $this->createUserWithRole('employee');
        $ptppRequest = $this->createPtppRequest($submitter, $recipient);

        $this->actingAs($otherUser)
            ->get(route('ptpp.show', $ptppRequest))
            ->assertNotFound();

        $this->actingAs($submitter)
            ->post(route('ptpp.review', $ptppRequest), [
                'action' => 'disetujui',
                'notes' => 'Tidak berwenang.',
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('ptpp_requests', [
            'id' => $ptppRequest->id,
            'approval_status' => 'menunggu_persetujuan',
        ]);
    }

    public function test_only_employees_can_create_ptpp(): void
    {
        $manager = $this->createUserWithRole('manager');

        $this->actingAs($manager)
            ->get(route('ptpp.create'))
            ->assertForbidden();

        $this->actingAs($manager)
            ->post(route('ptpp.store'), [])
            ->assertForbidden();

        $this->assertDatabaseCount('ptpp_requests', 0);
    }

    public function test_progress_photos_are_visible_to_related_users_but_not_unrelated_users(): void
    {
        Storage::fake('local');
        $submitter = $this->createUserWithRole('employee');
        $recipient = $this->createUserWithRole('employee');
        $manager = $this->createUserWithRole('manager');
        $admin = $this->createUserWithRole('admin');
        $otherUser = $this->createUserWithRole('employee');
        $ptppRequest = $this->createPtppRequest($submitter, $recipient, 'disetujui', $manager);
        $photoPath = 'ptpp/progress/update.png';
        Storage::disk('local')->put($photoPath, $this->imageContent());
        $update = $ptppRequest->progressUpdates()->create([
            'user_id' => $recipient->id,
            'progress_percent' => 50,
            'notes' => 'Perbaikan sedang berlangsung.',
            'photo_path' => $photoPath,
            'recorded_at' => now(),
        ]);
        $photoUrl = route('ptpp.updates.photo', [$ptppRequest, $update]);

        $this->actingAs($submitter)
            ->get(route('ptpp.show', $ptppRequest))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('ptppRequest.progress_updates.0.photo_url', $photoUrl)
            );

        $this->actingAs($submitter)
            ->get(route('ptpp.letter', $ptppRequest))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('ptppRequest.progress_updates', [])
            );

        foreach ([$submitter, $recipient, $manager, $admin] as $user) {
            $this->actingAs($user)
                ->get($photoUrl)
                ->assertOk()
                ->assertHeader('Cache-Control', 'no-store, private');
        }

        $this->actingAs($otherUser)
            ->get($photoUrl)
            ->assertNotFound();
    }

    public function test_manager_must_provide_a_note_before_making_a_decision(): void
    {
        $submitter = $this->createUserWithRole('employee');
        $recipient = $this->createUserWithRole('employee');
        $manager = $this->createUserWithRole('manager');
        $ptppRequest = $this->createPtppRequest($submitter, $recipient);

        $this->actingAs($manager)
            ->from(route('ptpp.show', $ptppRequest))
            ->post(route('ptpp.review', $ptppRequest), [
                'action' => 'disetujui',
                'notes' => '',
                'reviewer_signature' => $this->signatureData(),
            ])
            ->assertSessionHasErrors('notes');

        $this->assertDatabaseHas('ptpp_requests', [
            'id' => $ptppRequest->id,
            'approval_status' => 'menunggu_persetujuan',
            'reviewed_by' => null,
        ]);
    }

    public function test_ptpp_requires_a_unique_number_and_an_active_valid_recipient(): void
    {
        $submitter = $this->createUserWithRole('employee');
        $recipient = $this->createUserWithRole('employee');
        $admin = $this->createUserWithRole('admin');
        $existingRequest = $this->createPtppRequest($submitter, $recipient);
        $payload = $this->validPayload($recipient);
        $payload['ptpp_number'] = $existingRequest->ptpp_number;

        $this->actingAs($submitter)
            ->get(route('ptpp.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Ptpp/Form')
                ->has('recipients', 1)
                ->where('recipients.0.id', $recipient->id)
            );

        $this->actingAs($submitter)
            ->from(route('ptpp.create'))
            ->post(route('ptpp.store'), $payload)
            ->assertSessionHasErrors('ptpp_number');

        $payload['ptpp_number'] = 'PTPP-017/PPNLM0204/2026-56';
        $payload['recipient_id'] = $submitter->id;

        $this->actingAs($submitter)
            ->from(route('ptpp.create'))
            ->post(route('ptpp.store'), $payload)
            ->assertSessionHasErrors([
                'recipient_id' => 'Pilih penerima aktif selain akun admin dan akun Anda sendiri.',
            ]);

        $payload['recipient_id'] = $admin->id;

        $this->actingAs($submitter)
            ->from(route('ptpp.create'))
            ->post(route('ptpp.store'), $payload)
            ->assertSessionHasErrors('recipient_id');

        $recipient->forceFill(['is_active' => false])->save();
        $payload['recipient_id'] = $recipient->id;

        $this->actingAs($submitter)
            ->from(route('ptpp.create'))
            ->post(route('ptpp.store'), $payload)
            ->assertSessionHasErrors('recipient_id');

        $this->assertDatabaseCount('ptpp_requests', 1);
    }

    public function test_submitter_must_sign_ptpp_before_sending_it(): void
    {
        $submitter = $this->createUserWithRole('employee');
        $recipient = $this->createUserWithRole('employee');
        $payload = $this->validPayload($recipient);
        unset($payload['sender_signature']);

        $this->actingAs($submitter)
            ->from(route('ptpp.create'))
            ->post(route('ptpp.store'), $payload)
            ->assertSessionHasErrors([
                'sender_signature' => 'Tanda tangan pemohon wajib dibubuhkan sebelum mengirim PTPP.',
            ]);

        $this->assertDatabaseCount('ptpp_requests', 0);
    }

    public function test_manager_must_sign_ptpp_before_approving_it(): void
    {
        $submitter = $this->createUserWithRole('employee');
        $recipient = $this->createUserWithRole('employee');
        $manager = $this->createUserWithRole('manager');
        $ptppRequest = $this->createPtppRequest($submitter, $recipient);

        $this->actingAs($manager)
            ->from(route('ptpp.show', $ptppRequest))
            ->post(route('ptpp.review', $ptppRequest), [
                'action' => 'disetujui',
                'notes' => 'Sudah diperiksa.',
            ])
            ->assertSessionHasErrors([
                'reviewer_signature' => 'Tanda tangan manager wajib dibubuhkan untuk menyetujui PTPP.',
            ]);

        $this->assertDatabaseHas('ptpp_requests', [
            'id' => $ptppRequest->id,
            'approval_status' => 'menunggu_persetujuan',
            'reviewer_signature_path' => null,
        ]);
    }

    public function test_user_cannot_view_another_users_ptpp_signature(): void
    {
        Storage::fake('local');
        $submitter = $this->createUserWithRole('employee');
        $recipient = $this->createUserWithRole('employee');
        $otherUser = $this->createUserWithRole('employee');
        $ptppRequest = $this->createPtppRequest($submitter, $recipient);
        $signaturePath = 'ptpp/signatures/private.png';
        Storage::disk('local')->put($signaturePath, $this->imageContent());
        $ptppRequest->update(['sender_signature_path' => $signaturePath]);

        $this->actingAs($otherUser)
            ->get(route('ptpp.signature', [$ptppRequest, 'sender']))
            ->assertNotFound();
    }

    public function test_unauthenticated_user_is_redirected_to_login_for_ptpp(): void
    {
        $this->get(route('ptpp.index'))
            ->assertRedirect(route('login'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validPayload(User $recipient): array
    {
        return [
            'ptpp_number' => 'PTPP-016/PPNLM0204/2026-55',
            'recipient_id' => $recipient->id,
            'to_function' => 'Fajar / Operasional',
            'from_function' => 'Distribusi',
            'sender_signature_name' => 'Bagus Pratama',
            'sender_short_name' => 'Bagus',
            'sender_signature' => $this->signatureData(),
            'location' => 'Filling shed',
            'source_category' => 'audit',
            'description' => 'Perlu dilakukan pengecekan dan perbaikan peralatan.',
        ];
    }

    private function createUserWithRole(string $roleName): User
    {
        $role = Role::query()->firstOrCreate(
            ['name' => $roleName],
            ['label' => ucfirst($roleName)]
        );

        return User::factory()->create(['role_id' => $role->id]);
    }

    private function fakeImage(string $filename): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($filename, $this->imageContent());
    }

    private function imageContent(): string
    {
        return base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/lT8AAAAASUVORK5CYII=',
            true
        ) ?: '';
    }

    private function signatureData(): string
    {
        return 'data:image/png;base64,'.base64_encode($this->imageContent());
    }

    private function createPtppRequest(
        User $submitter,
        User $recipient,
        string $approvalStatus = 'menunggu_persetujuan',
        ?User $manager = null
    ): PtppRequest {
        return PtppRequest::query()->create([
            ...$this->validPayload($recipient),
            'user_id' => $submitter->id,
            'title' => 'PTPP Pengecekan peralatan',
            'status' => $approvalStatus,
            'approval_status' => $approvalStatus,
            'work_status' => 'menunggu_diterima',
            'progress_percent' => 0,
            'reviewed_by' => $manager?->id,
            'reviewer_name_snapshot' => $manager?->name,
        ]);
    }
}
