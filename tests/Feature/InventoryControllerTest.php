<?php

namespace Tests\Feature;

use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\InventoryTransaction;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class InventoryControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_item_creation_requires_an_explicit_type(): void
    {
        $this->actingAs(User::factory()->create())->post('/inventory', [])
            ->assertSessionHasErrors(['is_borrowable' => 'Pilih jenis barang.']);
    }

    public function test_admin_can_change_item_type_after_outstanding_stock_is_returned(): void
    {
        $role = Role::query()->create(['name' => 'admin', 'label' => 'Admin']);
        $admin = User::factory()->create(['role_id' => $role->id]);
        $category = InventoryCategory::query()->create(['name' => 'Perlengkapan']);
        $item = InventoryItem::query()->create([
            'category_id' => $category->id,
            'code' => 'INV-TYPE-1',
            'name' => 'Perlengkapan uji',
            'unit' => 'Unit',
            'is_borrowable' => true,
        ]);
        $loan = InventoryTransaction::query()->create(['type' => 'loan', 'transaction_code' => 'LOAN-TYPE-1']);
        $line = $loan->items()->create(['inventory_item_id' => $item->id, 'quantity' => 2]);
        $url = '/inventory/'.$item->id.'/type';

        $this->actingAs(User::factory()->create())->patch($url, ['is_borrowable' => false])->assertForbidden();
        $this->actingAs($admin)->from('/inventory')->patch($url, ['is_borrowable' => false])
            ->assertSessionHasErrors('is_borrowable');
        $this->assertTrue($item->fresh()->is_borrowable);

        $returned = InventoryTransaction::query()->create(['type' => 'return', 'transaction_code' => 'RETURN-TYPE-1']);
        $returned->items()->create(['inventory_item_id' => $item->id, 'related_loan_item_id' => $line->id, 'quantity' => 2]);
        $this->from('/inventory')->patch($url, ['is_borrowable' => false])->assertRedirect('/inventory')
            ->assertSessionHasNoErrors();
        $this->assertFalse($item->fresh()->is_borrowable);
        $this->get('/i/transaksi')->assertInertia(fn (Assert $page) => $page
            ->has('items', 0)
            ->where('consumables.0.id', $item->id));
    }

    public function test_creating_an_item_records_initial_stock_and_stock_in_transaction(): void
    {
        Storage::fake('public');

        $category = InventoryCategory::query()->create([
            'name' => 'Elektronik',
            'is_active' => true,
        ]);

        $this->actingAs(User::factory()->create())
            ->post('/inventory', [
                'name' => 'Proyektor',
                'image' => UploadedFile::fake()->createWithContent(
                    'proyektor.png',
                    base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/U3sAAAAASUVORK5CYII='),
                ),
                'category_id' => $category->id,
                'unit' => 'Unit',
                'initial_quantity' => 11,
                'is_borrowable' => true,
                'description' => null,
            ])
            ->assertRedirect('/inventory');

        $item = InventoryItem::query()->firstOrFail();

        $this->assertSame(11, $item->stock_on_hand);
        Storage::disk('public')->assertExists($item->image_path);
        $this->get('/inventory')
            ->assertInertia(fn (Assert $page) => $page
                ->where('items.data.0.image_url', Storage::disk('public')->url($item->image_path))
            );
        $this->assertDatabaseHas('inventory_transactions', [
            'type' => 'stock_in',
            'notes' => 'Stok awal saat barang didaftarkan.',
        ]);
        $this->assertDatabaseHas('inventory_transaction_items', [
            'inventory_item_id' => $item->id,
            'quantity' => 11,
        ]);
    }

    public function test_stock_status_uses_fixed_stock_thresholds(): void
    {
        $user = User::factory()->create();
        $category = InventoryCategory::query()->create([
            'name' => 'Perlengkapan',
            'is_active' => true,
        ]);

        foreach ([
            ['name' => 'A-Habis', 'stock_on_hand' => 0],
            ['name' => 'B-Menipis', 'stock_on_hand' => 8],
            ['name' => 'C-Menipis', 'stock_on_hand' => 10],
            ['name' => 'D-Tersedia', 'stock_on_hand' => 11],
        ] as $itemData) {
            $item = InventoryItem::query()->create([
                'category_id' => $category->id,
                'code' => 'INV-'.$itemData['name'],
                'name' => $itemData['name'],
                'unit' => 'Pcs',
                'minimum_stock' => 0,
                'is_active' => true,
            ]);

            DB::table('inventory_items')
                ->where('id', $item->id)
                ->update(['stock_on_hand' => $itemData['stock_on_hand']]);
        }

        $this->actingAs($user)
            ->get('/inventory')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Inventory/Index')
                ->where('items.data.0.stock_status', 'out')
                ->where('items.data.1.stock_status', 'low')
                ->where('items.data.2.stock_status', 'low')
                ->where('items.data.3.stock_status', 'available')
            );
    }

    public function test_creating_an_item_requires_a_valid_image(): void
    {
        $category = InventoryCategory::query()->create([
            'name' => 'Elektronik',
            'is_active' => true,
        ]);

        $this->actingAs(User::factory()->create())
            ->post('/inventory', [
                'name' => 'Proyektor',
                'image' => UploadedFile::fake()->create('proyektor.txt', 10, 'text/plain'),
                'category_id' => $category->id,
                'unit' => 'Unit',
                'initial_quantity' => 0,
                'is_borrowable' => true,
            ])
            ->assertSessionHasErrors('image');

        $this->assertDatabaseCount('inventory_items', 0);
    }

    public function test_inventory_table_supports_ten_twenty_and_fifty_rows_per_page(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/inventory')
            ->assertInertia(fn (Assert $page) => $page
                ->where('items.per_page', 10)
            );

        foreach ([20, 50] as $perPage) {
            $this->get('/inventory?per_page='.$perPage)
                ->assertInertia(fn (Assert $page) => $page
                    ->where('items.per_page', $perPage)
                );
        }

        $this->get('/inventory?per_page=30')
            ->assertSessionHasErrors('per_page');
    }

    public function test_removing_an_item_archives_it_and_preserves_its_transaction_history(): void
    {
        $user = User::factory()->create();
        $category = InventoryCategory::query()->create([
            'name' => 'Perlengkapan',
            'is_active' => true,
        ]);
        $item = InventoryItem::query()->create([
            'category_id' => $category->id,
            'code' => 'INV-ARCHIVE-1',
            'name' => 'Kabel jaringan',
            'unit' => 'Meter',
            'minimum_stock' => 0,
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->post('/inventory/'.$item->id.'/stock', ['quantity' => 5])
            ->assertRedirect('/inventory');

        $this->from('/inventory')
            ->delete('/inventory/'.$item->id)
            ->assertRedirect('/inventory');

        $this->assertDatabaseHas('inventory_items', [
            'id' => $item->id,
            'is_active' => false,
        ]);
        $this->assertDatabaseHas('inventory_transactions', [
            'type' => 'stock_in',
        ]);
        $this->post('/inventory/'.$item->id.'/stock', ['quantity' => 1])
            ->assertNotFound();

        $this->from('/inventory')
            ->patch('/inventory/'.$item->id.'/restore')
            ->assertRedirect('/inventory');

        $this->assertDatabaseHas('inventory_items', [
            'id' => $item->id,
            'is_active' => true,
        ]);
    }

    public function test_admin_can_add_stock_and_open_the_shared_transaction_qr(): void
    {
        $admin = User::factory()->create();
        $category = InventoryCategory::query()->create([
            'name' => 'Perlengkapan',
            'is_active' => true,
        ]);
        $item = InventoryItem::query()->create([
            'category_id' => $category->id,
            'code' => 'INV-STOCK-1',
            'name' => 'Kabel HDMI',
            'unit' => 'Pcs',
            'minimum_stock' => 10,
            'is_active' => true,
        ]);
        $this->actingAs($admin)
            ->post('/inventory/'.$item->id.'/stock', [
                'quantity' => 4,
                'notes' => 'Pembelian baru.',
            ])
            ->assertRedirect('/inventory');

        $this->assertDatabaseHas('inventory_items', [
            'id' => $item->id,
            'stock_on_hand' => 4,
        ]);
        $this->assertDatabaseHas('inventory_transactions', [
            'type' => 'stock_in',
            'notes' => 'Pembelian baru.',
        ]);

        $this->get('/inventory/transactions/qr')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Inventory/TransactionQr')
                ->where('qrUrl', route('inventory.public'))
            );
    }

    public function test_inventory_pages_require_authentication(): void
    {
        $this->get('/inventory')->assertRedirect('/login');
        $this->get('/inventory/transactions/qr')->assertRedirect('/login');
    }
}
