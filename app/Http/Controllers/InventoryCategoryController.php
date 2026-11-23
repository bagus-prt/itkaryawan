<?php

namespace App\Http\Controllers;

use App\Models\InventoryCategory;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class InventoryCategoryController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
            'per_page' => ['sometimes', 'integer', Rule::in([10, 20, 50])],
        ]);

        $query = InventoryCategory::query()
            ->withCount('items');

        if ($filters['search'] ?? null) {
            $search = trim($filters['search']);

            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if (($filters['status'] ?? null) === 'active') {
            $query->where('is_active', true);
        } elseif (($filters['status'] ?? null) === 'inactive') {
            $query->where('is_active', false);
        }

        return Inertia::render('Inventory/Categories', [
            'categories' => $query
                ->orderBy('name')
                ->paginate((int) ($filters['per_page'] ?? 10))
                ->withQueryString(),

            'filters' => [
                'search' => $filters['search'] ?? '',
                'status' => $filters['status'] ?? '',
            ],

            'summary' => [
                'total' => InventoryCategory::count(),
                'active' => InventoryCategory::where('is_active', true)->count(),
                'inactive' => InventoryCategory::where('is_active', false)->count(),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('inventory_categories', 'name'),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        InventoryCategory::create([
            'name' => trim($validated['name']),
            'description' => $validated['description'] ?? null,
            'is_active' => true,
        ]);

        return redirect()
            ->route('inventory.categories.index')
            ->with('success', 'Kategori berhasil ditambahkan.');
    }

    public function update(Request $request, InventoryCategory $category)
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('inventory_categories', 'name')
                    ->ignore($category->id),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        $category->update([
            'name' => trim($validated['name']),
            'description' => $validated['description'] ?? null,
        ]);

        return redirect()
            ->route('inventory.categories.index')
            ->with('success', 'Kategori berhasil diperbarui.');
    }

    public function toggleStatus(InventoryCategory $category)
    {
        $category->update([
            'is_active' => ! $category->is_active,
        ]);

        $message = $category->is_active
            ? 'Kategori berhasil diaktifkan.'
            : 'Kategori berhasil dinonaktifkan.';

        return redirect()
            ->route('inventory.categories.index')
            ->with('success', $message);
    }
}
