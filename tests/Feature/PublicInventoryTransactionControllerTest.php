<?php

namespace Tests\Feature;

use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\InventoryTransaction;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PublicInventoryTransactionControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_use_the_shared_form_to_borrow_and_return_an_item(): void
    {
        $item = $this->createItem(3);
        Storage::fake('local');

        $this->get('/i/transaksi')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Inventory/PublicTransaction')
                ->where('items.0.name', 'Proyektor')
                ->where('items.0.stock_on_hand', 3)
                ->has('activeLoans', 0)
            );

        $this->post('/i/transaksi', $this->borrowData([$this->line($item, 2)]))
            ->assertRedirect('/i/transaksi');

        $loan = InventoryTransaction::query()->where('type', 'loan')->firstOrFail();
        $loanItem = $loan->items()->firstOrFail();

        $this->assertDatabaseHas('inventory_items', [
            'id' => $item->id,
            'stock_on_hand' => 1,
        ]);
        $this->assertDatabaseHas('inventory_transactions', [
            'id' => $loan->id,
            'borrower_name' => 'Sari',
            'division' => 'Tamu',
            'borrower_phone' => '081234567890',
            'created_by' => null,
        ]);

        $this->from('/i/transaksi')
            ->post('/i/transaksi/cari-pinjaman', [
                'borrower_phone' => '0812-3456-7890',
            ])
            ->assertRedirect('/i/transaksi')
            ->assertSessionHas('inventory_borrower_phone_lookup', '081234567890');

        $this->get('/i/transaksi')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Inventory/PublicTransaction')
                ->where('borrowerPhoneLookup', '081234567890')
                ->has('activeLoans', 1)
                ->where('activeLoans.0.id', $loanItem->id)
                ->where('activeLoans.0.outstanding_quantity', 2)
                ->missing('activeLoans.0.transaction_code')
                ->missing('activeLoans.0.borrower_name')
            );

        $this->post('/i/transaksi/kembali', [
            'loan_item_id' => $loanItem->id,
            'borrower_phone' => '0812-3456-7890',
            'quantity' => 1,
        ])->assertRedirect('/i/transaksi');

        $this->assertDatabaseHas('inventory_items', [
            'id' => $item->id,
            'stock_on_hand' => 2,
        ]);
        $this->assertDatabaseHas('inventory_transaction_items', [
            'related_loan_item_id' => $loanItem->id,
            'quantity' => 1,
        ]);

        $this->post('/i/transaksi/cari-pinjaman', [
            'borrower_phone' => '081234567890',
        ])->assertRedirect('/i/transaksi');

        $this->get('/i/transaksi')
            ->assertInertia(fn (Assert $page) => $page
                ->has('activeLoans', 1)
                ->where('activeLoans.0.outstanding_quantity', 1)
            );

        $this->post('/i/transaksi/kembali', [
            'loan_item_id' => $loanItem->id,
            'borrower_phone' => '081234567890',
            'quantity' => 1,
        ])->assertRedirect('/i/transaksi');

        $this->assertDatabaseHas('inventory_items', [
            'id' => $item->id,
            'stock_on_hand' => 3,
        ]);
        $this->assertDatabaseCount('inventory_transactions', 3);

        $this->post('/i/transaksi/cari-pinjaman', [
            'borrower_phone' => '081234567890',
        ])->assertRedirect('/i/transaksi');

        $this->get('/i/transaksi')
            ->assertInertia(fn (Assert $page) => $page->has('activeLoans', 0));
    }

    public function test_legacy_item_qr_redirects_to_the_shared_transaction_form(): void
    {
        $this->get('/i/old-item-token')
            ->assertRedirect('/i/transaksi');
    }

    public function test_public_return_rejects_a_loan_that_does_not_match_the_phone_number(): void
    {
        $item = $this->createItem(2);
        $loan = InventoryTransaction::query()->create([
            'transaction_code' => 'INV-20261018-ABC12345',
            'type' => 'loan',
            'borrower_name' => 'Sari',
            'division' => 'Tamu',
            'borrower_phone' => '081234567890',
        ]);
        $loanItem = $loan->items()->create([
            'inventory_item_id' => $item->id,
            'quantity' => 1,
        ]);

        $this->from('/i/transaksi')
            ->post('/i/transaksi/cari-pinjaman', [
                'borrower_phone' => '089999999999',
            ])
            ->assertRedirect('/i/transaksi');

        $this->get('/i/transaksi')
            ->assertInertia(fn (Assert $page) => $page
                ->where('borrowerPhoneLookup', '089999999999')
                ->has('activeLoans', 0)
            );

        $this->from('/i/transaksi')
            ->post('/i/transaksi/kembali', [
                'loan_item_id' => $loanItem->id,
                'borrower_phone' => '089999999999',
                'quantity' => 1,
            ])
            ->assertSessionHasErrors('borrower_phone');

        $this->get('/i/transaksi')
            ->assertInertia(fn (Assert $page) => $page
                ->where('borrowerPhoneLookup', '089999999999')
                ->has('activeLoans', 0)
            );

        $this->assertDatabaseHas('inventory_items', [
            'id' => $item->id,
            'stock_on_hand' => 2,
        ]);
        $this->assertDatabaseCount('inventory_transactions', 1);
    }

    public function test_public_borrow_cannot_bypass_item_borrowability(): void
    {
        $item = $this->createItem(2);
        $item->update(['is_borrowable' => false]);

        $this->from('/i/transaksi')
            ->post('/i/transaksi', $this->borrowData([$this->line($item, 1)]))
            ->assertSessionHasErrors('inventory_item_id');

        $this->assertDatabaseHas('inventory_items', [
            'id' => $item->id,
            'stock_on_hand' => 2,
            'is_borrowable' => false,
        ]);
        $this->assertDatabaseCount('inventory_transactions', 0);
    }

    public function test_public_borrow_cannot_use_an_archived_item(): void
    {
        $item = $this->createItem(2);
        $item->update(['is_active' => false]);

        $this->from('/i/transaksi')
            ->post('/i/transaksi', $this->borrowData([$this->line($item, 1)]))
            ->assertSessionHasErrors('inventory_item_id');

        $this->get('/i/transaksi')
            ->assertInertia(fn (Assert $page) => $page->has('items', 0));

        $this->assertDatabaseHas('inventory_items', [
            'id' => $item->id,
            'stock_on_hand' => 2,
            'is_active' => false,
        ]);
        $this->assertDatabaseCount('inventory_transactions', 0);
    }

    public function test_public_return_cannot_exceed_the_remaining_quantity(): void
    {
        $item = $this->createItem(1);
        $loan = InventoryTransaction::query()->create([
            'transaction_code' => 'INV-20261018-XYZ12345',
            'type' => 'loan',
            'borrower_name' => 'Sari',
            'division' => 'Tamu',
            'borrower_phone' => '081234567890',
        ]);
        $loanItem = $loan->items()->create([
            'inventory_item_id' => $item->id,
            'quantity' => 1,
        ]);

        $this->from('/i/transaksi')
            ->post('/i/transaksi/kembali', [
                'loan_item_id' => $loanItem->id,
                'borrower_phone' => '081234567890',
                'quantity' => 2,
            ])
            ->assertSessionHasErrors('quantity');

        $this->get('/i/transaksi')
            ->assertInertia(fn (Assert $page) => $page
                ->where('borrowerPhoneLookup', '081234567890')
                ->has('activeLoans', 1)
                ->where('activeLoans.0.outstanding_quantity', 1)
            );

        $this->assertDatabaseHas('inventory_items', [
            'id' => $item->id,
            'stock_on_hand' => 1,
        ]);
        $this->assertDatabaseCount('inventory_transactions', 1);
    }

    public function test_public_borrow_records_one_signed_transaction_with_multiple_items(): void
    {
        Storage::fake('local');
        $first = $this->createItem(2);
        $second = $this->createItem(3, 'INV-TEST-2');

        $this->post('/i/transaksi', $this->borrowData([$this->line($first, 2), $this->line($second, 1)]))
            ->assertRedirect('/i/transaksi')
            ->assertSessionHas('inventory_success_type', 'loan')
            ->assertSessionMissing('inventory_receipt');

        $transaction = InventoryTransaction::query()->sole();
        $this->get('/i/transaksi')->assertDontSee($transaction->transaction_code)
            ->assertInertia(fn (Assert $page) => $page->where('successType', 'loan')->missing('receipt'));
        $this->assertSame(2, $transaction->items()->count());
        $this->assertSame(0, $first->fresh()->stock_on_hand);
        $this->assertSame(2, $second->fresh()->stock_on_hand);
        Storage::disk('local')->assertExists($transaction->borrower_signature_path);
        $url = route('inventory.transactions.signature', $transaction);
        $this->get($url)->assertRedirect('/login');
        $employeeRole = Role::query()->create(['name' => 'employee', 'label' => 'Karyawan']);
        $this->actingAs(User::factory()->create(['role_id' => $employeeRole->id]))->get($url)->assertForbidden();
        $adminRole = Role::query()->create(['name' => 'admin', 'label' => 'Admin']);
        $this->actingAs(User::factory()->create(['role_id' => $adminRole->id]))->get($url)->assertOk();
    }

    public function test_public_borrow_requires_signature_and_rolls_back_if_any_item_is_out_of_stock(): void
    {
        Storage::fake('local');
        $first = $this->createItem(2);
        $second = $this->createItem(0, 'INV-TEST-2');
        $data = $this->borrowData([$this->line($first, 1), $this->line($second, 1)]);

        $this->get('/i/transaksi')->assertInertia(fn (Assert $page) => $page
            ->has('items', 2)
            ->where('items.1.stock_on_hand', 0));
        $this->from('/i/transaksi')->post('/i/transaksi', array_replace($data, ['signature' => '']))
            ->assertSessionHasErrors('signature');
        $this->from('/i/transaksi')->post('/i/transaksi', $data)->assertSessionHasErrors('quantity');
        $this->assertSame(2, $first->fresh()->stock_on_hand);
        $this->assertDatabaseCount('inventory_transactions', 0);
        $this->assertSame([], Storage::disk('local')->allFiles('inventory-signatures'));
    }

    public function test_a_guest_can_take_consumables_without_creating_a_returnable_loan(): void
    {
        $item = $this->createItem(3);
        $item->update(['is_borrowable' => false]);
        $this->get('/i/transaksi')->assertInertia(fn (Assert $page) => $page
            ->has('items', 0)
            ->where('consumables.0.id', $item->id));
        $data = $this->borrowData([$this->line($item, 2)]) + ['type' => 'stock_out'];

        $this->post('/i/transaksi', $data)->assertRedirect('/i/transaksi')
            ->assertSessionHas('inventory_success_type', 'stock_out');
        $transaction = InventoryTransaction::query()->sole();
        $this->assertSame('stock_out', $transaction->type);
        $this->assertSame('Sari', $transaction->borrower_name);
        $this->assertSame(1, $item->fresh()->stock_on_hand);
        Storage::disk('local')->assertExists($transaction->borrower_signature_path);

        $this->post('/i/transaksi/cari-pinjaman', ['borrower_phone' => '081234567890'])->assertRedirect('/i/transaksi');
        $this->get('/i/transaksi')->assertInertia(fn (Assert $page) => $page->has('activeLoans', 0));
        $this->from('/i/transaksi')->post('/i/transaksi', $data)->assertSessionHasErrors('quantity');
        $this->assertDatabaseCount('inventory_transactions', 1);
        $this->assertSame(1, $item->fresh()->stock_on_hand);
    }

    private function borrowData(array $items): array
    {
        Storage::fake('local');

        return [
            'items' => $items,
            'borrower_name' => 'Sari',
            'division' => 'Tamu',
            'borrower_phone' => '(0812) 3456-7890',
            'signature' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/U3sAAAAASUVORK5CYII=',
        ];
    }

    private function line(InventoryItem $item, int $quantity): array
    {
        return ['inventory_item_id' => $item->id, 'quantity' => $quantity];
    }

    private function createItem(int $stock, string $code = 'INV-TEST-1'): InventoryItem
    {
        $category = InventoryCategory::query()->firstOrCreate([
            'name' => 'Elektronik',
            'is_active' => true,
        ]);

        $item = InventoryItem::query()->create([
            'category_id' => $category->id,
            'code' => $code,
            'name' => 'Proyektor',
            'unit' => 'Unit',
            'minimum_stock' => 10,
            'is_borrowable' => true,
            'is_active' => true,
        ]);

        DB::table('inventory_items')
            ->where('id', $item->id)
            ->update(['stock_on_hand' => $stock]);

        return $item->fresh();
    }
}
