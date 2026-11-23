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
        string $division,
        string $borrowerPhone,
        ?Authenticatable $actor = null,
        ?string $notes = null,
    ): InventoryTransaction {
        return DB::transaction(function () use (
            $item,
            $quantity,
            $borrowerName,
            $division,
            $borrowerPhone,
            $actor,
            $notes,
        ): InventoryTransaction {
            $lockedItem = InventoryItem::query()
                ->lockForUpdate()
                ->findOrFail($item->id);

            if (! $lockedItem->is_active) {
                throw ValidationException::withMessages([
                    'inventory_item_id' => 'Barang ini sedang tidak aktif.',
                ]);
            }

            if (! $lockedItem->is_borrowable) {
                throw ValidationException::withMessages([
                    'inventory_item_id' => 'Barang ini tidak dapat dipinjam.',
                ]);
            }

            if ($quantity < 1) {
                throw ValidationException::withMessages([
                    'quantity' => 'Jumlah peminjaman harus lebih dari 0.',
                ]);
            }

            if ($quantity > $lockedItem->stock_on_hand) {
                throw ValidationException::withMessages([
                    'quantity' => 'Jumlah peminjaman melebihi stok yang tersedia.',
                ]);
            }

            $lockedItem->decrement('stock_on_hand', $quantity);

            $transaction = InventoryTransaction::query()->create([
                'transaction_code' => $this->newTransactionCode(),
                'type' => 'loan',
                'borrower_name' => trim($borrowerName),
                'division' => trim($division),
                'borrower_phone' => $this->normalizePhone($borrowerPhone),
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

    public function returnLoan(
        InventoryTransactionItem $loanItem,
        int $quantity,
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

            $returnedQuantity = (int) InventoryTransactionItem::query()
                ->where('related_loan_item_id', $lockedLoanItem->id)
                ->sum('quantity');
            $remainingQuantity = $lockedLoanItem->quantity - $returnedQuantity;

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
