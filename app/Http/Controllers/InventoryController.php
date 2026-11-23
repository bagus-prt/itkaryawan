<?php

namespace App\Http\Controllers;

use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Services\InventoryTransactionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class InventoryController extends Controller
{
    private const UNITS = [
        'Pcs',
        'Unit',
        'Set',
        'Box',
        'Rim',
        'Lusin',
        'Pack',
        'Batang',
        'Meter',
        'Liter',
    ];

    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'integer', 'exists:inventory_categories,id'],
            'stock_status' => ['nullable', Rule::in(['available', 'low', 'out'])],
            'status' => ['nullable', Rule::in(['active', 'inactive', 'all'])],
            'per_page' => ['sometimes', 'integer', Rule::in([10, 20, 50])],
        ]);

        $query = InventoryItem::query()
            ->with('category:id,name')
            ->select([
                'id',
                'category_id',
                'code',
                'name',
                'image_path',
                'unit',
                'stock_on_hand',
                'is_borrowable',
                'is_active',
                'description',
            ]);

        if ($filters['search'] ?? null) {
            $search = trim($filters['search']);

            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%");
            });
        }

        if ($filters['category'] ?? null) {
            $query->where('category_id', $filters['category']);
        }

        if ($filters['stock_status'] ?? null) {
            match ($filters['stock_status']) {
                'available' => $query->whereRaw(
                    'stock_on_hand > ?',
                    [InventoryItem::STOCK_STATUS_THRESHOLD],
                ),
                'low' => $query->where('stock_on_hand', '>', 0)
                    ->where('stock_on_hand', '<=', InventoryItem::STOCK_STATUS_THRESHOLD),
                'out' => $query->where('stock_on_hand', 0),
                default => null,
            };
        }

        $status = $filters['status'] ?? 'active';

        if ($status !== 'all') {
            $query->where('is_active', $status === 'active');
        }

        $items = $query
            ->orderBy('name')
            ->paginate((int) ($filters['per_page'] ?? 10))
            ->withQueryString()
            ->through(function (InventoryItem $item) {
                $item->stock_status = match (true) {
                    $item->stock_on_hand === 0 => 'out',
                    $item->stock_on_hand <= InventoryItem::STOCK_STATUS_THRESHOLD => 'low',
                    default => 'available',
                };
                $item->image_url = $item->image_path
                    ? Storage::disk('public')->url($item->image_path)
                    : null;

                return $item;
            });

        return Inertia::render('Inventory/Index', [
            'items' => $items,
            'categories' => InventoryCategory::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name']),
            'units' => self::UNITS,
            'filters' => [
                'search' => $filters['search'] ?? '',
                'category' => $filters['category'] ?? '',
                'stock_status' => $filters['stock_status'] ?? '',
                'status' => $status,
            ],
            'summary' => [
                'total_items' => InventoryItem::where('is_active', true)->count(),
                'available' => InventoryItem::where('is_active', true)->whereRaw(
                    'stock_on_hand > ?',
                    [InventoryItem::STOCK_STATUS_THRESHOLD],
                )->count(),
                'low' => InventoryItem::where('is_active', true)
                    ->where('stock_on_hand', '>', 0)
                    ->where('stock_on_hand', '<=', InventoryItem::STOCK_STATUS_THRESHOLD)
                    ->count(),
                'out' => InventoryItem::where('is_active', true)
                    ->where('stock_on_hand', 0)
                    ->count(),
            ],
        ]);
    }

    public function store(
        Request $request,
        InventoryTransactionService $transactionService,
    ): RedirectResponse {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'category_id' => [
                'required',
                'integer',
                Rule::exists('inventory_categories', 'id')
                    ->where('is_active', true),
            ],
            'unit' => ['required', Rule::in(self::UNITS)],
            'initial_quantity' => ['required', 'integer', 'min:0', 'max:4294967295'],
            'is_borrowable' => ['required', 'boolean'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        $imagePath = $validated['image']->store('inventory', 'public');

        if ($imagePath === false) {
            throw new RuntimeException('Foto inventaris gagal disimpan.');
        }

        $transactionCommitted = false;

        try {
            DB::transaction(function () use ($validated, $request, $transactionService, $imagePath) {
                $lastItem = InventoryItem::query()
                    ->lockForUpdate()
                    ->orderByDesc('id')
                    ->first(['id']);

                $nextNumber = ($lastItem?->id ?? 0) + 1;

                $item = InventoryItem::create([
                    'category_id' => $validated['category_id'],
                    'code' => 'INV-'.str_pad(
                        (string) $nextNumber,
                        5,
                        '0',
                        STR_PAD_LEFT
                    ),
                    'name' => $validated['name'],
                    'image_path' => $imagePath,
                    'unit' => $validated['unit'],
                    'minimum_stock' => InventoryItem::STOCK_STATUS_THRESHOLD,
                    'is_borrowable' => $validated['is_borrowable'],
                    'is_active' => true,
                    'description' => $validated['description'] ?? null,
                ]);

                if ($validated['initial_quantity'] > 0) {
                    $transactionService->recordStockIn(
                        $item,
                        $validated['initial_quantity'],
                        $request->user(),
                        'Stok awal saat barang didaftarkan.',
                    );
                }
            });
            $transactionCommitted = true;
        } finally {
            if (! $transactionCommitted) {
                Storage::disk('public')->delete($imagePath);
            }
        }

        return redirect()
            ->route('inventory.index')
            ->with('success', 'Barang berhasil ditambahkan.');
    }

    public function addStock(
        Request $request,
        InventoryItem $item,
        InventoryTransactionService $transactionService,
    ): RedirectResponse {
        abort_unless($item->is_active, 404);

        $validated = $request->validate([
            'quantity' => ['required', 'integer', 'min:1', 'max:4294967295'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $transactionService->recordStockIn(
            $item,
            $validated['quantity'],
            $request->user(),
            $validated['notes'] ?? null,
        );

        return redirect()
            ->route('inventory.index')
            ->with('success', 'Stok barang berhasil ditambahkan.');
    }

    public function destroy(InventoryItem $item): RedirectResponse
    {
        $item->update(['is_active' => false]);

        return back()
            ->with('success', 'Barang berhasil dihapus dari daftar aktif.');
    }

    public function restore(InventoryItem $item): RedirectResponse
    {
        $item->update(['is_active' => true]);

        return back()
            ->with('success', 'Barang berhasil dipulihkan.');
    }
}
