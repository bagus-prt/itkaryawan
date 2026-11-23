<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserManagementLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_newly_created_user_can_log_in_with_the_password_set_during_creation(): void
    {
        $adminRole = Role::query()->create([
            'name' => 'admin',
            'label' => 'Admin',
        ]);
        $userRole = Role::query()->create([
            'name' => 'employee',
            'label' => 'Karyawan',
        ]);
        $admin = User::factory()->create([
            'role_id' => $adminRole->id,
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->post('/users', [
                'name' => 'New User',
                'email' => 'New.User@Example.com',
                'password' => 'new-user-password',
                'password_confirmation' => 'new-user-password',
                'role_id' => $userRole->id,
            ])
            ->assertRedirect('/users');

        $user = User::query()->where('email', 'new.user@example.com')->firstOrFail();

        $this->assertTrue(Hash::check('new-user-password', $user->password));

        $this->post('/logout')->assertRedirect('/login');

        $this->post('/login', [
            'email' => 'NEW.USER@EXAMPLE.COM',
            'password' => 'new-user-password',
        ])->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($user);
    }

    public function test_a_user_can_log_in_with_the_new_email_and_existing_password_after_an_email_update(): void
    {
        $adminRole = Role::query()->create([
            'name' => 'admin',
            'label' => 'Admin',
        ]);
        $userRole = Role::query()->create([
            'name' => 'employee',
            'label' => 'Karyawan',
        ]);
        $admin = User::factory()->create([
            'role_id' => $adminRole->id,
            'is_active' => true,
        ]);
        $user = User::factory()->create([
            'name' => 'Existing User',
            'email' => 'old.email@example.com',
            'password' => 'existing-user-password',
            'role_id' => $userRole->id,
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->put("/users/{$user->id}", [
                'name' => 'Existing User',
                'email' => 'New.Email@Example.com',
                'role_id' => $userRole->id,
            ])
            ->assertRedirect('/users');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'email' => 'new.email@example.com',
        ]);

        $this->post('/logout')->assertRedirect('/login');

        $this->post('/login', [
            'email' => 'NEW.EMAIL@EXAMPLE.COM',
            'password' => 'existing-user-password',
        ])->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($user->fresh());
    }

    public function test_creating_a_user_rejects_an_email_that_only_differs_by_letter_case(): void
    {
        $adminRole = Role::query()->create([
            'name' => 'admin',
            'label' => 'Admin',
        ]);
        $userRole = Role::query()->create([
            'name' => 'employee',
            'label' => 'Karyawan',
        ]);
        $admin = User::factory()->create([
            'role_id' => $adminRole->id,
            'is_active' => true,
        ]);
        User::factory()->create([
            'email' => 'existing.user@example.com',
            'role_id' => $userRole->id,
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->post('/users', [
                'name' => 'Duplicate User',
                'email' => 'EXISTING.USER@EXAMPLE.COM',
                'password' => 'new-user-password',
                'password_confirmation' => 'new-user-password',
                'role_id' => $userRole->id,
            ])
            ->assertSessionHasErrors('email');

        $this->assertDatabaseCount('users', 2);
    }

    public function test_a_user_with_an_existing_internal_email_can_log_in(): void
    {
        $user = User::factory()->create([
            'email' => 'employee@company',
            'password' => 'existing-user-password',
            'is_active' => true,
        ]);

        $this->post('/login', [
            'email' => 'employee@company',
            'password' => 'existing-user-password',
        ])->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($user);
    }
}
