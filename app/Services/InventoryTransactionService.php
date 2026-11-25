<?php

namespace App\Services;

use App\Models\InventoryItem;
use App\Models\InventoryTransaction;
use App\Models\InventoryTransactionItem;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class InventoryTransactionService
{
    private const MAX_STOCK = 4294967295;

    public function recordStockIn(
        InventoryItem $item,
        int $quantity,
        ?Authenticatable $actor,
        ?string $notes = null,
    ): InventoryTransaction {
        return DB::transaction(function () use ($item, $quantity, $actor, $notes): InventoryTransaction {
            $lockedItem = InventoryItem::query()
                ->lockForUpdate()
                ->findOrFail($item->id);

            if ($quantity < 1 || $quantity > self::MAX_STOCK - $lockedItem->stock_on_hand) {
                throw ValidationException::withMessages([
                    'quantity' => 'Jumlah stok melebihi kapasitas yang dapat dicatat.',
                ]);
            }

            $lockedItem->increment('stock_on_hand', $quantity);

            $transaction = InventoryTransaction::query()->create([
                'transaction_code' => $this->newTransactionCode(),
                'type' => 'stock_in',
                'created_by' => $actor?->getAuthIdentifier(),
                'notes' => $notes,
            ]);

            $transaction->items()->create([
                'inventory_item_id' => $lockedItem->id,
                'quantity' => $quantity,
            ]);

            return $transaction;
        });
    }

    public function borrow(
        InventoryItem $item,
        int $quantity,
        string $borrowerName,
        ?string $division,
        ?string $borrowerPhone,
        ?Authenticatable $actor = null,
        ?string $notes = null,
    ): InventoryTransaction {
        return $this->borrowMultiple(
            [$item->id => $quantity],
            $borrowerName,
            $division,
            $borrowerPhone,
            $actor,
            $notes,
        );
    }

    /**
     * @param  array<int, int>  $quantities  Quantities keyed by inventory item ID.
     */
    public function borrowMultiple(
        array $quantities,
        string $borrowerName,
        ?string $division,
        ?string $borrowerPhone,
        ?Authenticatable $actor = null,
        ?string $notes = null,
        ?string $signaturePath = null,
    ): InventoryTransaction {
        return $this->decreaseStock('loan', $quantities, $borrowerName, $division, $borrowerPhone, $actor, $notes, $signaturePath);
    }

    /** @param array<int, int> $quantities */
    public function recordStockOut(
        array $quantities,
        string $recipientName,
        ?string $division,
        ?string $phone = null,
        ?Authenticatable $actor = null,
        ?string $notes = null,
        ?string $signaturePath = null,
    ): InventoryTransaction {
        return $this->decreaseStock('stock_out', $quantities, $recipientName, $division, $phone, $actor, $notes, $signaturePath);
    }

    private function decreaseStock(
        string $type,
        array $quantities,
        string $borrowerName,
        ?string $division,
        ?string $borrowerPhone,
        ?Authenticatable $actor,
        ?string $notes,
        ?string $signaturePath,
    ): InventoryTransaction {
        return DB::transaction(function () use (
            $type,
            $quantities,
            $borrowerName,
            $division,
            $borrowerPhone,
            $actor,
            $notes,
            $signaturePath,
        ): InventoryTransaction {
            $items = InventoryItem::query()
                ->whereKey(array_keys($quantities))
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($items->isEmpty() || $items->count() !== count($quantities)) {
                throw ValidationException::withMessages([
                    'inventory_item_id' => 'Ada barang pilihan yang sudah tidak tersedia.',
                ]);
            }

            foreach ($items as $item) {
                $quantity = $quantities[$item->id];

                if (! $item->is_active || $item->is_borrowable !== ($type === 'loan')) {
                    throw ValidationException::withMessages([
                        'inventory_item_id' => $item->name.' tidak tersedia untuk jenis transaksi ini.',
                    ]);
                }

                if ($quantity < 1 || $quantity > $item->stock_on_hand) {
                    throw ValidationException::withMessages([
                        'quantity' => 'Jumlah '.$item->name.' tidak sesuai dengan stok yang tersedia.',
                    ]);
                }
            }

            $transaction = InventoryTransaction::query()->create([
                'transaction_code' => $this->newTransactionCode(),
                'type' => $type,
                'borrower_name' => trim($borrowerName),
                'division' => $division === null ? null : trim($division),
                'borrower_phone' => $borrowerPhone === null ? null : $this->normalizePhone($borrowerPhone),
                'borrower_signature_path' => $signaturePath,
                'created_by' => $actor?->getAuthIdentifier(),
                'notes' => $notes,
            ]);

            foreach ($items as $item) {
                $quantity = $quantities[$item->id];
                $item->decrement('stock_on_hand', $quantity);
                $transaction->items()->create([
                    'inventory_item_id' => $item->id,
                    'quantity' => $quantity,
                ]);
            }

            return $transaction;
        });
    }

    public function returnLoan(
        InventoryTransactionItem $loanItem,
        ?int $quantity,
        ?Authenticatable $actor,
        ?string $borrowerPhone = null,
        ?string $transactionCode = null,
        ?string $notes = null,
    ): InventoryTransaction {
        return DB::transaction(function () use (
            $loanItem,
            $quantity,
            $actor,
            $borrowerPhone,
            $transactionCode,
            $notes,
        ): InventoryTransaction {
            $lockedItem = InventoryItem::query()
                ->lockForUpdate()
                ->findOrFail($loanItem->inventory_item_id);

            $lockedLoanItem = InventoryTransactionItem::query()
                ->lockForUpdate()
                ->findOrFail($loanItem->id);

            $loan = InventoryTransaction::query()
                ->lockForUpdate()
                ->findOrFail($lockedLoanItem->inventory_transaction_id);

            if ($loan->type !== 'loan') {
                throw ValidationException::withMessages([
                    'loan_item_id' => 'Transaksi tersebut bukan peminjaman.',
                ]);
            }

            if (
                $transactionCode !== null
                && ! hash_equals(
                    Str::upper($loan->transaction_code),
                    Str::upper(trim($transactionCode)),
                )
            ) {
                throw ValidationException::withMessages([
                    'transaction_code' => 'Kode peminjaman tidak sesuai.',
                ]);
            }

            if (
                $borrowerPhone !== null
                && ! hash_equals(
                    $loan->borrower_phone ?? '',
                    $this->normalizePhone($borrowerPhone),
                )
            ) {
                throw ValidationException::withMessages([
                    'borrower_phone' => 'Nomor HP tidak sesuai dengan data peminjaman.',
                ]);
            }

            $returnedQuantity = InventoryTransactionItem::query()
                ->where('related_loan_item_id', $lockedLoanItem->id)
                ->lockForUpdate()
                ->get(['quantity'])
                ->sum('quantity');
            $remainingQuantity = $lockedLoanItem->quantity - $returnedQuantity;
            $quantity ??= $remainingQuantity;

            if ($quantity < 1 || $quantity > $remainingQuantity) {
                throw ValidationException::withMessages([
                    'quantity' => 'Jumlah pengembalian harus lebih dari 0 dan tidak melebihi sisa barang.',
                ]);
            }

            if ($quantity > self::MAX_STOCK - $lockedItem->stock_on_hand) {
                throw ValidationException::withMessages([
                    'quantity' => 'Jumlah pengembalian melebihi kapasitas stok yang dapat dicatat.',
                ]);
            }

            $lockedItem->increment('stock_on_hand', $quantity);

            $transaction = InventoryTransaction::query()->create([
                'transaction_code' => $this->newTransactionCode(),
                'type' => 'return',
                'borrower_name' => $loan->borrower_name,
                'division' => $loan->division,
                'borrower_phone' => $loan->borrower_phone,
                'created_by' => $actor?->getAuthIdentifier(),
                'notes' => $notes,
            ]);

            $transaction->items()->create([
                'inventory_item_id' => $lockedItem->id,
                'related_loan_item_id' => $lockedLoanItem->id,
                'quantity' => $quantity,
            ]);

            return $transaction;
        });
    }

    /** @param list<int> $loanIds */
    public function returnOutstandingLoans(array $loanIds, Authenticatable $actor): void
    {
        DB::transaction(function () use ($loanIds, $actor): void {
            $loanItems = InventoryTransactionItem::query()
                ->whereIn('inventory_transaction_id', $loanIds)
                ->withSum('returns', 'quantity')
                ->orderBy('inventory_item_id')
                ->orderBy('id')
                ->get()
                ->filter(fn (InventoryTransactionItem $item) => $item->quantity > (int) $item->returns_sum_quantity);

            if ($loanItems->pluck('inventory_transaction_id')->unique()->count() !== count($loanIds)) {
                throw ValidationException::withMessages(['loan_ids' => 'Ada pinjaman yang sudah dikembalikan. Muat ulang pilihan Anda.']);
            }

            foreach ($loanItems as $loanItem) {
                $this->returnLoan($loanItem, null, $actor);
            }
        }, attempts: 3);
    }

    public function normalizePhone(string $phone): string
    {
        return preg_replace('/\D+/', '', $phone) ?? '';
    }

    private function newTransactionCode(): string
    {
        return sprintf(
            'INV-%s-%s',
            now()->format('Ymd'),
            Str::upper(Str::random(8)),
        );
    }
}
