<?php

namespace App\Http\Controllers;

use App\Models\InventoryItem;
use App\Models\InventoryTransaction;
use App\Models\InventoryTransactionItem;
use App\Services\InventoryTransactionService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class InventoryTransactionController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'per_page' => ['sometimes', 'integer', Rule::in([10, 20, 50])],
        ]);

        $transactions = InventoryTransaction::query()
            ->with([
                'creator:id,name',
                'items.inventoryItem:id,code,name,unit',
                'items.relatedLoanItem.transaction:id,transaction_code',
            ])
            ->orderByDesc('transaction_at')
            ->orderByDesc('id')
            ->paginate((int) ($filters['per_page'] ?? 10))
            ->withQueryString();

        $items = InventoryItem::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'unit', 'stock_on_hand', 'is_borrowable']);

        $activeLoans = InventoryTransactionItem::query()
            ->select([
                'id',
                'inventory_item_id',
                'inventory_transaction_id',
                'quantity',
            ])
            ->with([
                'inventoryItem:id,code,name,unit',
                'transaction:id,transaction_code,borrower_name,division,borrower_phone',
            ])
            ->withSum('returns', 'quantity')
            ->whereHas('transaction', fn (Builder $query) => $query->where('type', 'loan'))
            ->whereRaw(
                'quantity > COALESCE((SELECT SUM(returned.quantity) FROM inventory_transaction_items AS returned WHERE returned.related_loan_item_id = inventory_transaction_items.id), 0)'
            )
            ->orderByDesc('id')
            ->get()
            ->map(fn (InventoryTransactionItem $loanItem): array => [
                'id' => $loanItem->id,
                'transaction_code' => $loanItem->transaction->transaction_code,
                'borrower_name' => $loanItem->transaction->borrower_name,
                'division' => $loanItem->transaction->division,
                'item' => $loanItem->inventoryItem,
                'quantity' => $loanItem->quantity,
                'outstanding_quantity' => $loanItem->quantity - (int) $loanItem->returns_sum_quantity,
            ]);

        return Inertia::render('Inventory/Transactions', [
            'transactions' => $transactions,
            'items' => $items,
            'activeLoans' => $activeLoans,
        ]);
    }

    public function store(
        Request $request,
        InventoryTransactionService $transactionService,
    ): RedirectResponse {
        $validated = $request->validate([
            'type' => ['required', Rule::in(['stock_in', 'loan', 'return'])],
            'inventory_item_id' => [
                'nullable',
                'required_if:type,stock_in,loan',
                'integer',
                Rule::exists('inventory_items', 'id')
                    ->where('is_active', true),
            ],
            'loan_item_id' => [
                'nullable',
                'required_if:type,return',
                'integer',
                'exists:inventory_transaction_items,id',
            ],
            'quantity' => ['required', 'integer', 'min:1', 'max:4294967295'],
            'borrower_name' => [
                'nullable',
                'required_if:type,loan',
                'string',
                'max:150',
            ],
            'division' => [
                'nullable',
                'required_if:type,loan',
                'string',
                'max:100',
            ],
            'borrower_phone' => [
                'nullable',
                'required_if:type,loan',
                'string',
                'max:30',
                'regex:/^[0-9+()\\-\\s]{7,30}$/',
                'regex:/\\d/',
            ],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        if ($validated['type'] === 'stock_in') {
            $transactionService->recordStockIn(
                InventoryItem::query()->findOrFail($validated['inventory_item_id']),
                $validated['quantity'],
                $request->user(),
                $validated['notes'] ?? null,
            );
        } elseif ($validated['type'] === 'loan') {
            $transactionService->borrow(
                InventoryItem::query()->findOrFail($validated['inventory_item_id']),
                $validated['quantity'],
                $validated['borrower_name'],
                $validated['division'],
                $validated['borrower_phone'],
                $request->user(),
                $validated['notes'] ?? null,
            );
        } else {
            $transactionService->returnLoan(
                InventoryTransactionItem::query()->findOrFail($validated['loan_item_id']),
                $validated['quantity'],
                $request->user(),
                notes: $validated['notes'] ?? null,
            );
        }

        return redirect()
            ->route('inventory.transactions.index')
            ->with('success', 'Transaksi inventaris berhasil dicatat.');
    }
}
