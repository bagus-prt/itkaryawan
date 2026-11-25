<?php

namespace App\Http\Controllers;

use App\Models\InventoryItem;
use App\Models\InventoryTransaction;
use App\Models\InventoryTransactionItem;
use App\Services\InventoryTransactionService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InventoryTransactionController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $role = $user->role?->name;
        abort_unless(in_array($role, ['admin', 'manager'], true), 403);
        $isAdmin = $role === 'admin';
        $filters = $request->validate([
            'per_page' => ['sometimes', 'integer', Rule::in([10, 20, 50])],
            'type' => ['nullable', Rule::in(['stock_in', 'stock_out', 'loan', 'return'])],
        ]);
        $ownLoan = fn (Builder $loan) => $loan->where('type', 'loan')
            ->where('created_by', $user->id)->whereNull('borrower_phone');

        $transactions = InventoryTransaction::query()
            ->when(! $isAdmin, fn (Builder $query) => $query->where(fn (Builder $scope) => $scope
                ->where(fn (Builder $own) => $own->whereIn('type', ['loan', 'stock_out'])
                    ->where('created_by', $user->id)->whereNull('borrower_phone'))
                ->orWhereHas('items.relatedLoanItem.transaction', $ownLoan)))
            ->when($filters['type'] ?? null, fn (Builder $query, string $type) => $query->where('type', $type))
            ->with([
                'creator:id,name',
                'items.inventoryItem:id,code,name,unit',
                'items.relatedLoanItem.transaction:id,transaction_code',
            ])
            ->orderByDesc('transaction_at')
            ->orderByDesc('id')
            ->paginate((int) ($filters['per_page'] ?? 10))
            ->withQueryString()
            ->through(fn (InventoryTransaction $transaction): array => collect($transaction->toArray())->except(['borrower_phone'])->all() + [
                'signature_url' => $isAdmin && $transaction->borrower_signature_path
                    ? route('inventory.transactions.signature', $transaction)
                    : null,
            ]);

        $items = InventoryItem::query()->with('category:id,name')
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'category_id', 'code', 'name', 'unit', 'stock_on_hand', 'is_borrowable', 'image_path'])
            ->map(fn (InventoryItem $item) => [
                'id' => $item->id,
                'code' => $item->code,
                'name' => $item->name,
                'unit' => $item->unit,
                'stock_on_hand' => $item->stock_on_hand,
                'is_borrowable' => $item->is_borrowable,
                'category' => $item->category?->name,
                'image_url' => $item->image_path
                    ? parse_url(Storage::disk('public')->url($item->image_path), PHP_URL_PATH)
                    : null,
            ]);

        $activeLoans = $isAdmin ? InventoryTransactionItem::query()
            ->select([
                'id',
                'inventory_item_id',
                'inventory_transaction_id',
                'quantity',
            ])
            ->with([
                'inventoryItem:id,code,name,unit',
                'transaction:id,transaction_code,borrower_name,division,transaction_at',
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
                'transaction_id' => $loanItem->inventory_transaction_id,
                'transaction_at' => $loanItem->transaction->transaction_at,
                'transaction_code' => $loanItem->transaction->transaction_code,
                'borrower_name' => $loanItem->transaction->borrower_name,
                'division' => $loanItem->transaction->division,
                'item' => $loanItem->inventoryItem,
                'quantity' => $loanItem->quantity,
                'outstanding_quantity' => $loanItem->quantity - (int) $loanItem->returns_sum_quantity,
            ]) : [];

        return Inertia::render('Inventory/Transactions', [
            'transactions' => $transactions,
            'items' => $items,
            'activeLoans' => $activeLoans,
            'isAdmin' => $isAdmin,
            'typeFilter' => $filters['type'] ?? '',
        ]);
    }

    public function signature(Request $request, InventoryTransaction $transaction): StreamedResponse
    {
        abort_unless($request->user()->role?->name === 'admin', 403);
        $path = $transaction->borrower_signature_path;
        $disk = Storage::disk('local');
        abort_unless($path && $disk->exists($path), 404);

        return $disk->response($path, 'tanda-tangan-peminjam.png', [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function store(
        Request $request,
        InventoryTransactionService $transactionService,
    ): RedirectResponse {
        $user = $request->user();
        $role = $user->role?->name;
        abort_unless(in_array($role, ['admin', 'manager'], true), 403);
        abort_unless($role === 'admin' || in_array($request->input('type'), ['loan', 'stock_out'], true), 403);

        $validated = $request->validate([
            'type' => ['required', Rule::in(['stock_in', 'stock_out', 'loan', 'return'])],
            'inventory_item_id' => [
                'nullable',
                Rule::requiredIf($request->input('type') === 'stock_in' || ($request->input('type') === 'loan' && ! $request->has('items'))),
                'integer',
                Rule::exists('inventory_items', 'id')
                    ->where('is_active', true),
            ],
            'items' => ['exclude_unless:type,loan,stock_out', 'required_if:type,stock_out', 'required_without:inventory_item_id', 'array', 'list', 'min:1'],
            'items.*.inventory_item_id' => ['required', 'integer', 'distinct'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:4294967295'],
            'loan_ids' => ['exclude_unless:type,return', 'required', 'array', 'list', 'min:1'],
            'loan_ids.*' => ['required', 'integer', 'distinct', Rule::exists('inventory_transactions', 'id')->where('type', 'loan')],
            'quantity' => [
                'exclude_if:type,return',
                'nullable',
                Rule::requiredIf($request->input('type') === 'stock_in' || ($request->input('type') === 'loan' && ! $request->has('items'))),
                'integer', 'min:1', 'max:4294967295',
            ],
            'notes' => ['nullable', 'string', 'max:2000'],
            'history_type' => ['nullable', Rule::in(['stock_in', 'stock_out', 'loan', 'return'])],
            'per_page' => ['nullable', Rule::in([10, 20, 50])],
        ]);

        if ($validated['type'] === 'stock_in') {
            $transactionService->recordStockIn(
                InventoryItem::query()->findOrFail($validated['inventory_item_id']),
                $validated['quantity'],
                $request->user(),
                $validated['notes'] ?? null,
            );
        } elseif ($validated['type'] === 'return') {
            $transactionService->returnOutstandingLoans(array_map('intval', $validated['loan_ids']), $user);
        } else {
            $quantities = collect($validated['items'] ?? [[
                'inventory_item_id' => $validated['inventory_item_id'],
                'quantity' => $validated['quantity'],
            ]])->mapWithKeys(fn (array $item) => [(int) $item['inventory_item_id'] => (int) $item['quantity']])->all();

            if ($validated['type'] === 'stock_out') {
                $transactionService->recordStockOut(
                    $quantities,
                    mb_substr($user->name, 0, 150),
                    null,
                    actor: $user,
                    notes: $validated['notes'] ?? null,
                );
            } else {
                $transactionService->borrowMultiple(
                    $quantities,
                    mb_substr($user->name, 0, 150),
                    null,
                    null,
                    $user,
                    $validated['notes'] ?? null,
                );
            }
        }

        return redirect()
            ->route('inventory.transactions.index', array_filter([
                'type' => $validated['history_type'] ?? null,
                'per_page' => $validated['per_page'] ?? null,
            ]))
            ->with('success', 'Transaksi inventaris berhasil dicatat.');
    }
}
