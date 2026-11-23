<?php

namespace Tests\Feature;

use App\Models\Complaint;
use App\Models\FuelStation;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ComplaintControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_spbu_can_send_a_complaint_through_the_public_form_without_logging_in(): void
    {
        $this->travelTo('2026-10-19 12:00:00');
        $station = FuelStation::query()->create([
            'code' => '31.401.01',
            'name' => 'SPBU Jakarta',
            'address' => 'Jl. Merdeka No. 10, Jakarta',
            'city' => 'Jakarta',
            'public_qr_token' => 'public-token',
            'is_active' => true,
        ]);

        $this->get('/keluhan')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Complaints/PublicCreate')
                ->where('today', '2026-10-19')
            );

        $this->post('/keluhan', [
            'station_code' => '31.401.01',
            'station_address' => 'Jl. Merdeka No. 10, Jakarta',
            'description' => 'Keluhan pasokan BBM.',
            'complaint_date' => '2000-01-01',
            'created_by' => 99,
            'status' => 'selesai',
        ])->assertRedirect('/keluhan');

        $complaint = Complaint::query()->firstOrFail();
        $this->assertDatabaseHas('complaints', [
            'station_code' => '31.401.01',
            'station_address' => 'Jl. Merdeka No. 10, Jakarta',
            'description' => 'Keluhan pasokan BBM.',
            'category' => 'Keluhan SPBU',
            'status' => 'baru',
            'created_by' => null,
            'fuel_station_id' => $station->id,
        ]);
        $this->assertSame('2026-10-19', $complaint->complaint_date->toDateString());
        $this->assertDatabaseCount('complaints', 1);
    }

    public function test_public_form_rejects_missing_required_complaint_fields(): void
    {
        $this->from('/keluhan')
            ->post('/keluhan', [])
            ->assertSessionHasErrors([
                'station_code' => 'Nomor/kode SPBU wajib diisi.',
                'station_address' => 'Lokasi/alamat SPBU wajib diisi.',
                'description' => 'Keterangan keluhan wajib diisi.',
            ]);

        $this->assertDatabaseCount('complaints', 0);
    }

    public function test_admin_can_manually_record_a_complaint_and_print_the_public_qr(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin)
            ->post('/complaints', [
                'station_code' => '34.123.45',
                'station_address' => 'Jalan Sudirman, Bandung',
                'description' => 'Temuan saat sidak.',
            ])
            ->assertRedirect('/complaints');

        $complaint = Complaint::query()->firstOrFail();
        $this->assertDatabaseHas('complaints', [
            'id' => $complaint->id,
            'created_by' => $admin->id,
            'description' => 'Temuan saat sidak.',
        ]);
        $this->assertSame(
            now()->timezone('Asia/Jakarta')->toDateString(),
            $complaint->complaint_date->toDateString(),
        );

        $this->actingAs($admin)
            ->get('/complaints/qr')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Complaints/Qr')
                ->where('qrUrl', route('complaints.public'))
            );
    }

    public function test_admin_can_search_and_filter_complaints_by_month_and_year(): void
    {
        $admin = $this->createAdmin();
        $this->createComplaint('31.401.01', 'Jakarta', 'Pasokan terganggu.', '2026-10-04');
        $this->createComplaint('31.401.02', 'Jakarta', 'Pompa rusak.', '2026-10-14');
        $this->createComplaint('31.401.01', 'Bogor', 'Keluhan lama.', '2025-10-04');
        $this->createComplaint('31.401.01', 'Jakarta', 'Keluhan September.', '2026-09-04');

        $this->actingAs($admin)
            ->get('/complaints?search=31.401.01&month=10&year=2026')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Complaints/Index')
                ->where('filters.search', '31.401.01')
                ->where('filters.month', 10)
                ->where('filters.year', 2026)
                ->where('complaints.total', 1)
                ->where('complaints.data.0.station_code', '31.401.01')
                ->where('complaints.data.0.station_address', 'Jakarta')
                ->where('complaints.data.0.date', '04/10/2026')
            );
    }

    public function test_non_admin_cannot_view_or_manually_record_complaints(): void
    {
        $employeeRole = Role::query()->create([
            'name' => 'employee',
            'label' => 'Karyawan',
        ]);
        $employee = User::factory()->create([
            'role_id' => $employeeRole->id,
        ]);

        $this->actingAs($employee)
            ->get('/complaints')
            ->assertForbidden();

        $this->actingAs($employee)
            ->post('/complaints', [
                'station_code' => '31.401.01',
                'station_address' => 'Jakarta',
                'description' => 'Tidak seharusnya tersimpan.',
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('complaints', 0);
    }

    public function test_manager_can_access_the_complaint_menu_available_to_operations_staff(): void
    {
        $managerRole = Role::query()->create([
            'name' => 'manager',
            'label' => 'Manager',
        ]);
        $manager = User::factory()->create([
            'role_id' => $managerRole->id,
        ]);

        $this->actingAs($manager)
            ->get('/complaints')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Complaints/Index'));
    }

    public function test_guest_is_redirected_from_the_admin_complaint_menu(): void
    {
        $this->get('/complaints')->assertRedirect('/login');
        $this->get('/complaints/qr')->assertRedirect('/login');
    }

    private function createAdmin(): User
    {
        $adminRole = Role::query()->create([
            'name' => 'admin',
            'label' => 'Admin',
        ]);

        return User::factory()->create([
            'role_id' => $adminRole->id,
        ]);
    }

    private function createComplaint(
        string $stationCode,
        string $stationAddress,
        string $description,
        string $date,
    ): Complaint {
        return Complaint::query()->create([
            'station_code' => $stationCode,
            'station_address' => $stationAddress,
            'complaint_date' => $date,
            'category' => 'Keluhan SPBU',
            'description' => $description,
            'status' => 'baru',
            'created_at' => $date.' 12:00:00',
            'updated_at' => $date.' 12:00:00',
        ]);
    }
}
