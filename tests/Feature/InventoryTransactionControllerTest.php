<?php

namespace Tests\Feature;

use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\InventoryTransaction;
use App\Models\Role;
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
        $admin = $this->userWithRole('admin');
        $item = $this->createItem(12);

        $this->actingAs($admin)
            ->post('/inventory/transactions', [
                'type' => 'loan',
                'inventory_item_id' => $item->id,
                'quantity' => 3,
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
            'borrower_name' => $admin->name,
            'borrower_phone' => null,
            'notes' => 'Dipakai untuk rapat.',
        ]);

        $this->post('/inventory/transactions', [
            'type' => 'return',
            'loan_ids' => [$loan->id],
            'quantity' => 1,
        ])->assertRedirect('/inventory/transactions');

        $this->assertDatabaseHas('inventory_items', [
            'id' => $item->id,
            'stock_on_hand' => 12,
        ]);
        $this->assertDatabaseHas('inventory_transaction_items', [
            'related_loan_item_id' => $loanItem->id,
            'quantity' => 3,
        ]);
        $this->assertDatabaseCount('inventory_transactions', 2);

        $this->get('/inventory/transactions')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Inventory/Transactions')
                ->has('activeLoans', 0)
            );
    }

    public function test_loan_larger_than_available_stock_is_rejected_without_changing_inventory(): void
    {
        $item = $this->createItem(2);

        $this->actingAs($this->userWithRole('admin'))
            ->from('/inventory/transactions')
            ->post('/inventory/transactions', [
                'type' => 'loan',
                'inventory_item_id' => $item->id,
                'quantity' => 3,
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

        $this->actingAs($this->userWithRole('admin'))
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

    public function test_manager_can_borrow_as_themselves_and_only_see_related_transactions(): void
    {
        $manager = $this->userWithRole('manager');
        $admin = $this->userWithRole('admin');
        $item = $this->createItem(5);
        $url = '/inventory/transactions';
        $data = ['inventory_item_id' => $item->id, 'quantity' => 2];

        $this->actingAs($manager)->post($url, $data + ['type' => 'stock_in'])->assertForbidden();
        $this->post($url, $data + ['type' => 'loan', 'borrower_name' => 'Nama palsu'])->assertRedirect($url);
        $loan = InventoryTransaction::query()->sole();
        $this->assertSame($manager->name, $loan->borrower_name);
        $this->assertNull($loan->borrower_phone);
        $return = ['type' => 'return', 'loan_ids' => [$loan->id]];
        $this->post($url, $return)->assertForbidden();

        $this->actingAs($admin)->post($url, $data + ['type' => 'stock_in'])->assertRedirect($url);
        $this->post($url, $return)->assertRedirect($url);
        $this->get($url)->assertInertia(fn (Assert $page) => $page->where('transactions.total', 3));

        $this->actingAs($manager)->get($url)->assertInertia(fn (Assert $page) => $page
            ->where('isAdmin', false)
            ->has('activeLoans', 0)
            ->where('transactions.total', 2));
        $this->get($url.'?type=return')->assertInertia(fn (Assert $page) => $page
            ->where('typeFilter', 'return')
            ->where('transactions.total', 1)
            ->where('transactions.data.0.type', 'return'));
        $this->actingAs($this->userWithRole('employee'))->get($url)->assertForbidden();
    }

    private function userWithRole(string $name): User
    {
        return User::factory()->create([
            'role_id' => Role::query()->firstOrCreate(['name' => $name], ['label' => $name])->id,
        ]);
    }

    public function test_one_internal_loan_contains_multiple_items_and_return_covers_all_remaining_items(): void
    {
        $admin = $this->userWithRole('admin');
        $first = $this->createItem(5);
        $second = $this->createItem(4, 'INV-TEST-2');
        $url = '/inventory/transactions';

        $this->actingAs($admin)->post($url, [
            'type' => 'loan',
            'items' => [
                ['inventory_item_id' => $first->id, 'quantity' => 2],
                ['inventory_item_id' => $second->id, 'quantity' => 3],
            ],
        ])->assertRedirect($url);

        $loan = InventoryTransaction::query()->sole();
        $this->assertSame(2, $loan->items()->count());
        $this->assertSame(3, $first->fresh()->stock_on_hand);
        $this->assertSame(1, $second->fresh()->stock_on_hand);
        $this->get($url)->assertInertia(fn (Assert $page) => $page
            ->has('activeLoans', 2)
            ->where('activeLoans.0.transaction_id', $loan->id)
            ->where('activeLoans.1.transaction_id', $loan->id));

        $this->post($url, ['type' => 'return', 'loan_ids' => [$loan->id]])->assertRedirect($url);
        $this->assertSame(5, $first->fresh()->stock_on_hand);
        $this->assertSame(4, $second->fresh()->stock_on_hand);
        $this->get($url)->assertInertia(fn (Assert $page) => $page->has('activeLoans', 0));
    }

    public function test_admin_and_manager_record_consumable_stock_out_using_their_own_accounts(): void
    {
        $admin = $this->userWithRole('admin');
        $manager = $this->userWithRole('manager');
        $paper = $this->createItem(5);
        $paper->update(['is_borrowable' => false]);
        $equipment = $this->createItem(4, 'INV-TEST-2');
        $url = '/inventory/transactions';
        $data = ['type' => 'stock_out', 'items' => [['inventory_item_id' => $paper->id, 'quantity' => 2]]];
        $this->actingAs($manager)->post($url, $data + ['recipient_name' => 'Nama palsu'])->assertRedirect($url);
        $this->assertSame(3, $paper->fresh()->stock_on_hand);

        $this->actingAs($admin)->from($url)->post($url, array_replace($data, [
            'items' => [...$data['items'], ['inventory_item_id' => $equipment->id, 'quantity' => 1]],
        ]))->assertSessionHasErrors('inventory_item_id');
        $this->assertSame(3, $paper->fresh()->stock_on_hand);
        $this->post($url, $data)->assertRedirect($url);
        $this->assertSame(1, $paper->fresh()->stock_on_hand);
        $this->get($url.'?type=stock_out')->assertInertia(fn (Assert $page) => $page
            ->where('transactions.total', 2)
            ->where('transactions.data.0.type', 'stock_out')
            ->where('transactions.data.0.borrower_name', $admin->name)
            ->where('transactions.data.0.division', null)
            ->has('activeLoans', 0));
        $this->get('/reports?report=transactions&type=stock_out')->assertInertia(fn (Assert $page) => $page
            ->where('rows.total', 2)
            ->where('rows.data.0.returned_at', null));
        $this->actingAs($manager)->get($url.'?type=stock_out')->assertInertia(fn (Assert $page) => $page
            ->where('transactions.total', 1)
            ->where('transactions.data.0.borrower_name', $manager->name));
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
