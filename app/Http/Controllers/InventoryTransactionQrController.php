<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class InventoryTransactionQrController extends Controller
{
    public function show(): Response
    {
        return Inertia::render('Inventory/TransactionQr', [
            'qrUrl' => route('inventory.public'),
        ]);
    }
}
