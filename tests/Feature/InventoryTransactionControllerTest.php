<?php

namespace Tests\Feature;

use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\InventoryTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class InventoryTransactionControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_loan_and_return_update_stock_and_create_audit_records(): void
    {
        $admin = User::factory()->create();
        $item = $this->createItem(12);

        $this->actingAs($admin)
            ->post('/inventory/transactions', [
                'type' => 'loan',
                'inventory_item_id' => $item->id,
                'quantity' => 3,
                'borrower_name' => 'Budi',
                'division' => 'Operasional',
                'borrower_phone' => '0812 3456 7890',
                'notes' => 'Dipakai untuk rapat.',
            ])
            ->assertRedirect('/inventory/transactions');

        $loan = InventoryTransaction::query()->where('type', 'loan')->firstOrFail();
        $loanItem = $loan->items()->firstOrFail();

        $this->assertDatabaseHas('inventory_items', [
            'id' => $item->id,
            'stock_on_hand' => 9,
        ]);
        $this->assertDatabaseHas('inventory_transactions', [
            'id' => $loan->id,
            'borrower_phone' => '081234567890',
            'notes' => 'Dipakai untuk rapat.',
        ]);

        $this->post('/inventory/transactions', [
            'type' => 'return',
            'loan_item_id' => $loanItem->id,
            'quantity' => 2,
            'notes' => 'Dikembalikan sebagian.',
        ])->assertRedirect('/inventory/transactions');

        $this->assertDatabaseHas('inventory_items', [
            'id' => $item->id,
            'stock_on_hand' => 11,
        ]);
        $this->assertDatabaseHas('inventory_transaction_items', [
            'related_loan_item_id' => $loanItem->id,
            'quantity' => 2,
        ]);
        $this->assertDatabaseCount('inventory_transactions', 2);

        $this->get('/inventory/transactions')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Inventory/Transactions')
                ->where('activeLoans.0.outstanding_quantity', 1)
            );
    }

    public function test_loan_larger_than_available_stock_is_rejected_without_changing_inventory(): void
    {
        $item = $this->createItem(2);

        $this->actingAs(User::factory()->create())
            ->from('/inventory/transactions')
            ->post('/inventory/transactions', [
                'type' => 'loan',
                'inventory_item_id' => $item->id,
                'quantity' => 3,
                'borrower_name' => 'Budi',
                'division' => 'Operasional',
                'borrower_phone' => '081234567890',
            ])
            ->assertSessionHasErrors('quantity');

        $this->assertDatabaseHas('inventory_items', [
            'id' => $item->id,
            'stock_on_hand' => 2,
        ]);
        $this->assertDatabaseCount('inventory_transactions', 0);
    }

    public function test_admin_cannot_record_stock_for_an_archived_inventory_item(): void
    {
        $item = $this->createItem(2);
        $item->update(['is_active' => false]);

        $this->actingAs(User::factory()->create())
            ->from('/inventory/transactions')
            ->post('/inventory/transactions', [
                'type' => 'stock_in',
                'inventory_item_id' => $item->id,
                'quantity' => 1,
            ])
            ->assertSessionHasErrors('inventory_item_id');

        $this->assertDatabaseHas('inventory_items', [
            'id' => $item->id,
            'stock_on_hand' => 2,
            'is_active' => false,
        ]);
        $this->assertDatabaseCount('inventory_transactions', 0);
    }

    public function test_transaction_page_requires_authentication(): void
    {
        $this->get('/inventory/transactions')->assertRedirect('/login');
    }

    private function createItem(int $stock): InventoryItem
    {
        $category = InventoryCategory::query()->create([
            'name' => 'Elektronik',
            'is_active' => true,
        ]);

        $item = InventoryItem::query()->create([
            'category_id' => $category->id,
            'code' => 'INV-TEST-1',
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
