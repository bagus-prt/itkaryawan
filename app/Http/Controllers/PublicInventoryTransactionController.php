<?php

namespace App\Http\Controllers;

use App\Models\InventoryItem;
use App\Models\InventoryTransactionItem;
use App\Services\InventoryTransactionService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
                    'transaction:id,transaction_code,type,borrower_name,division,borrower_phone',
                    'inventoryItem:id,code,name,unit',
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
                        'transaction_code' => $loanItem->transaction->transaction_code,
                        'borrower_name' => $loanItem->transaction->borrower_name,
                        'division' => $loanItem->transaction->division,
                        'item' => [
                            'name' => $loanItem->inventoryItem->name,
                            'code' => $loanItem->inventoryItem->code,
                            'unit' => $loanItem->inventoryItem->unit,
                        ],
                        'quantity' => $loanItem->quantity,
                        'outstanding_quantity' => $outstandingQuantity,
                    ];
                })
                ->filter()
                ->values();
        }

        $items = InventoryItem::query()
            ->with('category:id,name')
            ->where('is_active', true)
            ->where('is_borrowable', true)
            ->where('stock_on_hand', '>', 0)
            ->orderBy('name')
            ->get()
            ->map(fn (InventoryItem $item): array => [
                'id' => $item->id,
                'name' => $item->name,
                'code' => $item->code,
                'category' => $item->category->name,
                'unit' => $item->unit,
                'stock_on_hand' => $item->stock_on_hand,
            ]);

        return Inertia::render('Inventory/PublicTransaction', [
            'items' => $items,
            'activeLoans' => $activeLoans,
            'borrowerPhoneLookup' => $lookupPhone,
            'receipt' => session()->pull('inventory_receipt'),
        ]);
    }

    public function borrow(
        Request $request,
        InventoryTransactionService $transactionService,
    ): RedirectResponse {
        $validated = $request->validate([
            'inventory_item_id' => ['required', 'integer', 'exists:inventory_items,id'],
            'borrower_name' => ['required', 'string', 'max:150'],
            'division' => ['required', 'string', 'max:100'],
            'borrower_phone' => [
                'required',
                'string',
                'max:30',
                'regex:/^[0-9+()\\-\\s]{7,30}$/',
                'regex:/\\d/',
            ],
            'quantity' => ['required', 'integer', 'min:1', 'max:4294967295'],
        ]);

        $item = InventoryItem::query()->findOrFail($validated['inventory_item_id']);
        $transaction = $transactionService->borrow(
            $item,
            $validated['quantity'],
            $validated['borrower_name'],
            $validated['division'],
            $validated['borrower_phone'],
        );

        return redirect()
            ->route('inventory.public')
            ->with('inventory_receipt', [
                'type' => 'loan',
                'transaction_code' => $transaction->transaction_code,
                'quantity' => $validated['quantity'],
                'item_name' => $item->name,
                'unit' => $item->unit,
            ]);
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

        $loanItem = InventoryTransactionItem::query()
            ->with('inventoryItem')
            ->findOrFail($validated['loan_item_id']);

        $transaction = $transactionService->returnLoan(
            $loanItem,
            $validated['quantity'],
            null,
            $validated['borrower_phone'],
        );

        return redirect()
            ->route('inventory.public')
            ->with('inventory_receipt', [
                'type' => 'return',
                'transaction_code' => $transaction->transaction_code,
                'quantity' => $validated['quantity'],
                'item_name' => $loanItem->inventoryItem->name,
                'unit' => $loanItem->inventoryItem->unit,
            ]);
    }
}
