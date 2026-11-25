<?php

namespace Tests\Feature;

use App\Models\DocumentRequest;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\InventoryTransaction;
use App\Models\PtppRequest;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_sees_aggregate_leave_requests_for_the_selected_period_and_open_loans(): void
    {
        $admin = $this->userWithRole('admin');
        $employee = $this->userWithRole('employee');
        $this->travelTo(now()->setDate(2026, 11, 24));

        $this->leave($employee, [
            'department' => 'Operasional',
            'employee_name' => 'Nama Pemohon Rahasia',
            'reason' => 'Alasan pribadi rahasia',
            'created_at' => '2026-10-10 09:00:00',
        ]);
        $this->leave($employee);
        $category = InventoryCategory::query()->create(['name' => 'Peralatan']);
        $item = InventoryItem::query()->create([
            'category_id' => $category->id,
            'code' => 'IT-001',
            'name' => 'Kabel',
            'unit' => 'unit',
            'minimum_stock' => 1,
        ]);
        $loan = InventoryTransaction::query()->create(['transaction_code' => 'LOAN-1', 'type' => 'loan']);
        $loanItem = $loan->items()->create(['inventory_item_id' => $item->id, 'quantity' => 2]);
        $returned = InventoryTransaction::query()->create(['transaction_code' => 'RETURN-1', 'type' => 'return']);
        $returned->items()->create([
            'inventory_item_id' => $item->id,
            'related_loan_item_id' => $loanItem->id,
            'quantity' => 1,
        ]);

        $this->actingAs($admin)->get('/dashboard?period=2026-10')
            ->assertOk()
            ->assertDontSee('Nama Pemohon Rahasia')
            ->assertDontSee('Alasan pribadi rahasia')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('dashboard.role', 'Admin')
                ->where('dashboard.period', '2026-10')
                ->where('dashboard.highlight.value', 1)
                ->where('dashboard.chart.series.0.values.5', 1)
                ->where('dashboard.panel.rows.0.title', 'Operasional')
                ->missing('dashboard.panel.rows.0.href')
                ->where('dashboard.metrics.0.value', 1)
                ->where('dashboard.metrics.1.value', 1)
                ->where('dashboard.metrics.2.value', 0)
                ->where('dashboard.metrics.3.value', 2));

        $returned->items()->firstOrFail()->update(['quantity' => 2]);

        $this->get('/dashboard')->assertInertia(fn (Assert $page) => $page
            ->where('dashboard.metrics.1.value', 0)
            ->etc());
    }

    public function test_manager_sees_the_review_queue_and_recorded_decisions(): void
    {
        $manager = $this->userWithRole('manager');
        $employee = $this->userWithRole('employee');
        $waiting = $this->leave($employee);
        $approved = $this->leave($employee, ['status' => 'disetujui']);
        $approved->histories()->create([
            'actor_id' => $manager->id,
            'action' => 'disetujui',
            'new_status' => 'disetujui',
        ]);
        $ptpp = PtppRequest::query()->create(['user_id' => $employee->id, 'title' => 'PTPP untuk ditinjau', 'description' => 'Deskripsi']);

        $this->actingAs($manager)->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('dashboard.role', 'Manager')
                ->where('dashboard.highlight.value', 2)
                ->where('dashboard.chart.series.1.values.5', 1)
                ->has('dashboard.panel.rows', 2)
                ->where('dashboard.panel.rows.0.href', route('leave-requests.show', $waiting))
                ->where('dashboard.panel.rows.1.href', route('ptpp.show', $ptpp)));
    }

    public function test_employee_receives_only_personal_requests_and_approved_assigned_ptpp(): void
    {
        $employee = $this->userWithRole('employee');
        $other = $this->userWithRole('karyawan');
        $this->leave($employee);
        $this->leave($other, ['status' => 'perlu_revisi', 'review_notes' => 'Catatan privat pengguna lain']);

        foreach ([
            'Pekerjaan saya' => 'disetujui',
            'PTPP belum boleh ditampilkan' => 'menunggu_persetujuan',
        ] as $title => $status) {
            PtppRequest::query()->create([
                'user_id' => $other->id,
                'recipient_id' => $employee->id,
                'title' => $title,
                'description' => 'Deskripsi',
                'approval_status' => $status,
            ]);
        }

        $this->actingAs($employee)->get('/dashboard')
            ->assertOk()
            ->assertDontSee('Catatan privat pengguna lain')
            ->assertDontSee('PTPP belum boleh ditampilkan')
            ->assertInertia(fn (Assert $page) => $page
                ->where('dashboard.role', 'Karyawan')
                ->where('dashboard.highlight.value', 1)
                ->where('dashboard.metrics.0.value', 1)
                ->where('dashboard.chart.series.0.values.5', 1)
                ->where('dashboard.chart.series.1.values.5', 0)
                ->has('dashboard.panel.rows', 2));
    }

    private function userWithRole(string $role): User
    {
        return User::factory()->create([
            'role_id' => Role::query()->create(['name' => $role, 'label' => $role])->id,
            'is_active' => true,
        ]);
    }

    private function leave(User $user, array $attributes = []): DocumentRequest
    {
        $leave = new DocumentRequest;
        $leave->forceFill($attributes + [
            'user_id' => $user->id,
            'type' => 'cuti',
            'status' => 'diajukan',
            'reason' => 'Keperluan pribadi',
        ])->save();

        return $leave;
    }
}
