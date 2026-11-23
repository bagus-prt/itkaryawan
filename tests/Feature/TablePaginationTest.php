<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TablePaginationTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_information_tables_default_to_ten_rows_and_accept_twenty_or_fifty(): void
    {
        $adminRole = Role::query()->create([
            'name' => 'admin',
            'label' => 'Admin',
        ]);
        $admin = User::factory()->create([
            'role_id' => $adminRole->id,
            'is_active' => true,
        ]);
        $this->actingAs($admin);

        $tables = [
            ['/inventory', 'Inventory/Index', 'items'],
            ['/inventory/categories', 'Inventory/Categories', 'categories'],
            ['/inventory/transactions', 'Inventory/Transactions', 'transactions'],
            ['/users', 'Users/Index', 'users'],
            ['/complaints', 'Complaints/Index', 'complaints'],
        ];

        foreach ($tables as [$url, $component, $prop]) {
            $this->get($url)
                ->assertInertia(fn (Assert $page) => $page
                    ->component($component)
                    ->where("{$prop}.per_page", 10)
                );

            foreach ([20, 50] as $perPage) {
                $this->get($url.'?per_page='.$perPage)
                    ->assertInertia(fn (Assert $page) => $page
                        ->component($component)
                        ->where("{$prop}.per_page", $perPage)
                    );
            }
        }
    }

    public function test_information_tables_reject_unsupported_page_sizes(): void
    {
        $adminRole = Role::query()->create([
            'name' => 'admin',
            'label' => 'Admin',
        ]);
        $this->actingAs(User::factory()->create([
            'role_id' => $adminRole->id,
            'is_active' => true,
        ]));

        foreach ([
            '/inventory',
            '/inventory/categories',
            '/inventory/transactions',
            '/users',
            '/complaints',
        ] as $url) {
            $this->get($url.'?per_page=30')
                ->assertSessionHasErrors('per_page');
        }
    }
}
