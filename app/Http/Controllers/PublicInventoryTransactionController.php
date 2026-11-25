<?php

namespace App\Http\Controllers;

use App\Models\InventoryItem;
use App\Models\InventoryTransactionItem;
use App\Services\InventoryTransactionService;
use App\Services\LeaveSignatureService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PublicInventoryTransactionController extends Controller
{
    public function show(
        Request $request,
        InventoryTransactionService $transactionService,
    ): Response {
        $lookupPhone = $request->session()->get('inventory_borrower_phone_lookup');

        if (
            ! is_string($lookupPhone)
            && $request->session()->getOldInput('loan_item_id') !== null
        ) {
            $oldPhone = $request->session()->getOldInput('borrower_phone');

            if (is_string($oldPhone)) {
                $lookupPhone = $transactionService->normalizePhone($oldPhone);
            }
        }

        $activeLoans = collect();

        if (is_string($lookupPhone)) {
            $activeLoans = InventoryTransactionItem::query()
                ->with([
                    'inventoryItem:id,name,unit',
                ])
                ->withSum('returns as returned_quantity', 'quantity')
                ->whereHas('transaction', function (Builder $query) use ($lookupPhone): void {
                    $query->where('type', 'loan')
                        ->where('borrower_phone', $lookupPhone);
                })
                ->latest('id')
                ->get()
                ->map(function (InventoryTransactionItem $loanItem): ?array {
                    $returnedQuantity = (int) ($loanItem->returned_quantity ?? 0);
                    $outstandingQuantity = $loanItem->quantity - $returnedQuantity;

                    if ($outstandingQuantity < 1) {
                        return null;
                    }

                    return [
                        'id' => $loanItem->id,
                        'item' => [
                            'name' => $loanItem->inventoryItem->name,
                            'unit' => $loanItem->inventoryItem->unit,
                        ],
                        'outstanding_quantity' => $outstandingQuantity,
                    ];
                })
                ->filter()
                ->values();
        }

        $catalog = InventoryItem::query()
            ->with('category:id,name')
            ->where('is_active', true)
            ->orderBy('name')
            ->orderBy('id')
            ->get();
        $formatItem = fn (InventoryItem $item): array => [
            'id' => $item->id,
            'name' => $item->name,
            'code' => $item->code,
            'category' => $item->category->name,
            'unit' => $item->unit,
            'stock_on_hand' => $item->stock_on_hand,
            'image_url' => $item->image_path
                ? parse_url(Storage::disk('public')->url($item->image_path), PHP_URL_PATH)
                : null,
        ];

        return Inertia::render('Inventory/PublicTransaction', [
            'items' => $catalog->where('is_borrowable', true)->map($formatItem)->values(),
            'consumables' => $catalog->where('is_borrowable', false)->map($formatItem)->values(),
            'activeLoans' => $activeLoans,
            'borrowerPhoneLookup' => $lookupPhone,
            'successType' => $request->session()->pull('inventory_success_type'),
        ]);
    }

    public function borrow(
        Request $request,
        InventoryTransactionService $transactionService,
        LeaveSignatureService $signatures,
    ): RedirectResponse {
        $validated = $request->validate([
            'type' => ['nullable', Rule::in(['loan', 'stock_out'])],
            'items' => ['required', 'array', 'list', 'min:1'],
            'items.*' => ['required', 'array:inventory_item_id,quantity'],
            'items.*.inventory_item_id' => ['required', 'integer', 'distinct'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:4294967295'],
            'borrower_name' => ['required', 'string', 'max:150'],
            'division' => ['required', 'string', 'max:100'],
            'borrower_phone' => [
                'required',
                'string',
                'max:30',
                'regex:/^[0-9+()\\-\\s]{7,30}$/',
                'regex:/\\d/',
            ],
            'signature' => ['required', 'string', 'max:350000'],
        ], [
            'items.required' => 'Pilih minimal satu barang.',
            'signature.required' => 'Tanda tangan wajib diisi.',
        ]);

        $quantities = collect($validated['items'])->mapWithKeys(fn (array $item) => [
            (int) $item['inventory_item_id'] => (int) $item['quantity'],
        ])->all();
        $signaturePath = $signatures->store($validated['signature'], 'signature', 'inventory-signatures');

        try {
            $transaction = ($validated['type'] ?? 'loan') === 'stock_out'
                ? $transactionService->recordStockOut($quantities, $validated['borrower_name'], $validated['division'], $validated['borrower_phone'], signaturePath: $signaturePath)
                : $transactionService->borrowMultiple($quantities, $validated['borrower_name'], $validated['division'], $validated['borrower_phone'], signaturePath: $signaturePath);
        } catch (\Throwable $exception) {
            $signatures->delete($signaturePath);

            throw $exception;
        }

        return redirect()
            ->route('inventory.public')
            ->with('inventory_success_type', $transaction->type);
    }

    public function searchLoans(
        Request $request,
        InventoryTransactionService $transactionService,
    ): RedirectResponse {
        $validated = $request->validate([
            'borrower_phone' => [
                'required',
                'string',
                'max:30',
                'regex:/^[0-9+()\\-\\s]{7,30}$/',
                'regex:/\\d/',
            ],
        ]);

        return redirect()
            ->route('inventory.public')
            ->with(
                'inventory_borrower_phone_lookup',
                $transactionService->normalizePhone($validated['borrower_phone']),
            );
    }

    public function recordReturn(
        Request $request,
        InventoryTransactionService $transactionService,
    ): RedirectResponse {
        $validated = $request->validate([
            'loan_item_id' => ['required', 'integer', 'exists:inventory_transaction_items,id'],
            'borrower_phone' => [
                'required',
                'string',
                'max:30',
                'regex:/^[0-9+()\\-\\s]{7,30}$/',
                'regex:/\\d/',
            ],
            'quantity' => ['required', 'integer', 'min:1', 'max:4294967295'],
        ]);

        $loanItem = InventoryTransactionItem::query()->findOrFail($validated['loan_item_id']);

        $transactionService->returnLoan(
            $loanItem,
            $validated['quantity'],
            null,
            $validated['borrower_phone'],
        );

        return redirect()
            ->route('inventory.public')
            ->with('inventory_success_type', 'return');
    }
}
