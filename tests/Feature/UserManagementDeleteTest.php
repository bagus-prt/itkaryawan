<?php

namespace Tests\Feature;

use App\Models\DocumentRequest;
use App\Models\PtppRequest;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserManagementDeleteTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_admin_can_delete_a_user_without_request_history(): void
    {
        $admin = $this->createUserWithRole('admin', true);
        $user = $this->createUserWithRole('employee', false);

        $this->actingAs($admin)
            ->delete("/users/{$user->id}")
            ->assertRedirect('/users')
            ->assertSessionHas('success', 'Akun pengguna berhasil dihapus.');

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    public function test_an_admin_cannot_delete_a_user_with_document_request_history(): void
    {
        $admin = $this->createUserWithRole('admin', true);
        $user = $this->createUserWithRole('employee', true);
        DocumentRequest::query()->create([
            'user_id' => $user->id,
            'type' => 'cuti',
            'reason' => 'Permohonan cuti.',
        ]);

        $this->actingAs($admin)
            ->delete("/users/{$user->id}")
            ->assertSessionHasErrors([
                'user' => 'Akun memiliki riwayat pengajuan dokumen atau PTPP. Nonaktifkan akun agar riwayat tetap tersimpan.',
            ]);

        $this->assertModelExists($user);
    }

    public function test_an_admin_cannot_delete_a_user_with_ptpp_request_history(): void
    {
        $admin = $this->createUserWithRole('admin', true);
        $user = $this->createUserWithRole('employee', true);
        PtppRequest::query()->create([
            'user_id' => $user->id,
            'title' => 'Perbaikan jaringan',
            'description' => 'Perlu perbaikan jaringan kantor.',
        ]);

        $this->actingAs($admin)
            ->delete("/users/{$user->id}")
            ->assertSessionHasErrors([
                'user' => 'Akun memiliki riwayat pengajuan dokumen atau PTPP. Nonaktifkan akun agar riwayat tetap tersimpan.',
            ]);

        $this->assertModelExists($user);
    }

    public function test_a_user_cannot_delete_their_own_account(): void
    {
        $admin = $this->createUserWithRole('admin', true);

        $this->actingAs($admin)
            ->delete("/users/{$admin->id}")
            ->assertSessionHasErrors([
                'user' => 'Anda tidak dapat menghapus akun sendiri.',
            ]);

        $this->assertModelExists($admin);
    }

    public function test_an_admin_cannot_delete_the_last_active_admin(): void
    {
        $admin = $this->createUserWithRole('admin', false);
        $lastActiveAdmin = $this->createUserWithRole('admin', true);

        $this->actingAs($admin)
            ->delete("/users/{$lastActiveAdmin->id}")
            ->assertSessionHasErrors([
                'user' => 'Admin aktif terakhir tidak dapat dihapus.',
            ]);

        $this->assertModelExists($lastActiveAdmin);
    }

    public function test_a_non_admin_cannot_delete_an_account(): void
    {
        $user = $this->createUserWithRole('employee', true);
        $target = $this->createUserWithRole('employee', false);

        $this->actingAs($user)
            ->delete("/users/{$target->id}")
            ->assertForbidden();

        $this->assertModelExists($target);
    }

    public function test_an_admin_can_change_a_users_status_from_the_edit_form_endpoint(): void
    {
        $admin = $this->createUserWithRole('admin', true);
        $user = $this->createUserWithRole('employee', true);

        $this->actingAs($admin)
            ->patch("/users/{$user->id}/status")
            ->assertRedirect('/users');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'is_active' => false,
        ]);
    }

    private function createUserWithRole(string $roleName, bool $isActive): User
    {
        $role = Role::query()->firstOrCreate(
            ['name' => $roleName],
            ['label' => ucfirst($roleName)]
        );

        return User::factory()->create([
            'role_id' => $role->id,
            'is_active' => $isActive,
        ]);
    }
}
