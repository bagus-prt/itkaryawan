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

class ReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_reports_are_available_only_to_admins_in_every_format(): void
    {
        $this->get('/reports')->assertRedirect('/login');
        $employee = $this->userWithRole('employee');
        foreach (['/reports', '/reports?format=print', '/reports?format=csv'] as $url) {
            $this->actingAs($employee)->get($url)->assertForbidden();
        }
    }

    public function test_admin_can_load_each_report(): void
    {
        $this->actingAs($this->userWithRole('admin'));

        foreach (['stock', 'transactions', 'leave', 'ptpp', 'complaints'] as $kind) {
            $this->get('/reports?report='.$kind)->assertOk()->assertInertia(fn (Assert $page) => $page
                ->component('Reports/Index')
                ->where('report', $kind)
                ->where('rows.total', 0));
        }
    }

    public function test_leave_report_prints_requested_fields_for_the_selected_leave_period(): void
    {
        $admin = $this->userWithRole('admin');
        $employee = $this->userWithRole('employee');
        foreach (['2026-11-01', '2026-12-01'] as $endDate) {
            DocumentRequest::query()->create([
                'user_id' => $employee->id,
                'type' => 'cuti',
                'status' => 'disetujui',
                'department' => 'Operasional',
                'employee_name' => 'Nama Rahasia Pemohon',
                'reason' => 'Alasan rahasia',
                'start_date' => $endDate === '2026-11-01' ? '2026-10-31' : '2026-12-01',
                'end_date' => $endDate,
            ]);
        }
        $url = '/reports?report=leave&date_basis=leave_period&start=2026-11-01&end=2026-11-30';
        $this->actingAs($admin)->get($url)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('rows.total', 1)
            ->where('rows.data.0.department', 'Operasional')
            ->where('rows.data.0.employee', 'Nama Rahasia Pemohon')
            ->where('rows.data.0.duration', 2)
            ->where('rows.data.0.start_date', '31/10/2026')
            ->where('rows.data.0.end_date', '01/11/2026')
            ->missing('rows.data.0.reason'));

        $this->get($url.'&format=print')->assertOk()
            ->assertSee('Laporan Rekap Cuti Karyawan')
            ->assertSee('Pertamina Patra Niaga')
            ->assertSee('Operasional')
            ->assertSee('Nama Rahasia Pemohon')
            ->assertDontSee('Jumlah baris')
            ->assertDontSee('Total pengajuan')
            ->assertDontSee('Dicetak')
            ->assertDontSee('Alasan rahasia');
    }

    public function test_csv_and_print_include_filtered_rows_beyond_the_first_page(): void
    {
        $this->actingAs($this->userWithRole('admin'));
        $category = InventoryCategory::query()->create(['name' => 'Peralatan']);
        foreach (range(1, 12) as $index) {
            InventoryItem::query()->create([
                'category_id' => $category->id,
                'code' => 'K-'.$index,
                'name' => $index === 12 ? 'Baterai' : sprintf('Kabel %02d', $index),
                'unit' => 'unit',
            ]);
        }
        $url = '/reports?report=stock&search=Kabel';
        $this->get($url)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('rows.total', 11)
            ->has('rows.data', 10));
        $this->get($url.'&format=print')->assertOk()->assertSee('Kabel 11')->assertDontSee('Baterai');
        $csv = $this->get($url.'&format=csv')->assertOk()->streamedContent();
        $this->assertStringContainsString('Kabel 11', $csv);
        $this->assertStringNotContainsString('Baterai', $csv);
    }

    public function test_transaction_report_uses_original_borrower_and_full_return_date(): void
    {
        $this->actingAs($this->userWithRole('admin'));
        $category = InventoryCategory::query()->create(['name' => 'Peralatan']);
        $item = InventoryItem::query()->create(['category_id' => $category->id, 'code' => 'L-001', 'name' => 'Laptop', 'unit' => 'unit', 'is_borrowable' => true]);
        $loan = InventoryTransaction::query()->create([
            'transaction_code' => 'LOAN-1', 'type' => 'loan', 'borrower_name' => 'Nama Peminjam', 'transaction_at' => '2026-11-10',
        ])->items()->create(['inventory_item_id' => $item->id, 'quantity' => 2]);

        foreach ([11, 12] as $day) {
            InventoryTransaction::query()->create([
                'transaction_code' => 'RETURN-'.$day, 'type' => 'return', 'transaction_at' => '2026-11-'.$day,
            ])->items()->create([
                'inventory_item_id' => $item->id, 'quantity' => 1, 'related_loan_item_id' => $loan->id,
            ]);
        }

        $this->get('/reports?report=transactions&type=loan&return_status=closed')->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('rows.total', 1)
                ->where('rows.data.0.borrower', 'Nama Peminjam')
                ->where('rows.data.0.returned_at', '12/11/2026'));

        $this->get('/reports?report=transactions&type=return&search=Nama+Peminjam')->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('rows.total', 2)
                ->where('rows.data.0.borrower', 'Nama Peminjam'));
    }

    public function test_ptpp_report_displays_two_progress_states(): void
    {
        $admin = $this->userWithRole('admin');
        foreach ([0, 75, 100] as $percent) {
            PtppRequest::query()->create([
                'user_id' => $admin->id, 'title' => 'Judul PTPP', 'description' => 'Temuan '.$percent,
                'progress_percent' => $percent, 'from_function' => 'Operasi', 'to_function' => 'Perawatan',
            ]);
        }

        $this->actingAs($admin)->get('/reports?report=ptpp&progress=finished')->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('rows.total', 1)
                ->where('rows.data.0.description', 'Temuan 100')
                ->where('rows.data.0.progress', 'Selesai'));
        $this->get('/reports?report=ptpp&progress=unfinished')->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('rows.total', 2)
                ->where('rows.data.0.progress', 'Belum selesai'));
    }

    private function userWithRole(string $name): User
    {
        return User::factory()->create([
            'role_id' => Role::query()->create(['name' => $name, 'label' => $name])->id,
            'is_active' => true,
        ]);
    }
}
