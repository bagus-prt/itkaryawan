<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ComplaintController;
use App\Http\Controllers\InventoryCategoryController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\InventoryTransactionController;
use App\Http\Controllers\InventoryTransactionQrController;
use App\Http\Controllers\LeaveRequestController;
use App\Http\Controllers\LeaveReviewController;
use App\Http\Controllers\PtppController;
use App\Http\Controllers\PublicInventoryTransactionController;
use App\Http\Controllers\UserManagementController;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return redirect()->route('login');
});

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])
        ->name('login');

    Route::post('/login', [AuthController::class, 'login'])
        ->name('login.store');
});

Route::middleware('auth')->group(function () {
    Route::get('/ptpp', [PtppController::class, 'index'])
        ->name('ptpp.index');

    Route::get('/ptpp/create', [PtppController::class, 'create'])
        ->name('ptpp.create');

    Route::post('/ptpp', [PtppController::class, 'store'])
        ->name('ptpp.store');

    Route::get('/ptpp/{ptppRequest}/edit', [PtppController::class, 'edit'])
        ->whereNumber('ptppRequest')
        ->name('ptpp.edit');

    Route::get('/ptpp/{ptppRequest}', [PtppController::class, 'show'])
        ->whereNumber('ptppRequest')
        ->name('ptpp.show');

    Route::put('/ptpp/{ptppRequest}', [PtppController::class, 'update'])
        ->whereNumber('ptppRequest')
        ->name('ptpp.update');

    Route::post('/ptpp/{ptppRequest}/review', [PtppController::class, 'review'])
        ->whereNumber('ptppRequest')
        ->name('ptpp.review');

    Route::patch('/ptpp/{ptppRequest}/accept', [PtppController::class, 'accept'])
        ->whereNumber('ptppRequest')
        ->name('ptpp.accept');

    Route::post('/ptpp/{ptppRequest}/updates', [PtppController::class, 'storeUpdate'])
        ->whereNumber('ptppRequest')
        ->name('ptpp.updates.store');

    Route::get('/ptpp/{ptppRequest}/letter', [PtppController::class, 'letter'])
        ->whereNumber('ptppRequest')
        ->name('ptpp.letter');

    Route::get('/ptpp/{ptppRequest}/signature/{kind}', [PtppController::class, 'signature'])
        ->whereNumber('ptppRequest')
        ->whereIn('kind', ['sender', 'reviewer'])
        ->name('ptpp.signature');

    Route::get('/ptpp/{ptppRequest}/illustration', [PtppController::class, 'illustration'])
        ->whereNumber('ptppRequest')
        ->name('ptpp.illustration');

    Route::get('/ptpp/{ptppRequest}/updates/{update}/photo', [PtppController::class, 'photo'])
        ->whereNumber('ptppRequest')
        ->whereNumber('update')
        ->name('ptpp.updates.photo');

    Route::get('/surat-tugas', function () {
        return Inertia::render('Documents/TaskLetters');
    })->name('task-letters.index');

    Route::get('/leave-requests', [LeaveRequestController::class, 'index'])
        ->name('leave-requests.index');

    Route::get('/leave-requests/create', [LeaveRequestController::class, 'create'])
        ->name('leave-requests.create');

    Route::post('/leave-requests', [LeaveRequestController::class, 'store'])
        ->name('leave-requests.store');

    Route::get('/leave-requests/{leaveRequest}/signature/{kind}', [LeaveRequestController::class, 'signature'])
        ->whereIn('kind', ['employee', 'manager'])
        ->name('leave-requests.signature');

    Route::get('/leave-requests/{leaveRequest}/letter', [LeaveRequestController::class, 'letter'])
        ->name('leave-requests.letter');

    Route::get('/leave-requests/{leaveRequest}/edit', [LeaveRequestController::class, 'edit'])
        ->name('leave-requests.edit');

    Route::get('/leave-requests/{leaveRequest}', [LeaveRequestController::class, 'show'])
        ->name('leave-requests.show');

    Route::put('/leave-requests/{leaveRequest}', [LeaveRequestController::class, 'update'])
        ->name('leave-requests.update');

    Route::get('/leave-reviews', [LeaveReviewController::class, 'index'])
        ->name('leave-reviews.index');

    Route::post('/leave-reviews/{leaveRequest}', [LeaveReviewController::class, 'update'])
        ->name('leave-reviews.update');

    Route::get('/leave-administration', [LeaveRequestController::class, 'administration'])
        ->name('leave-administration.index');

    Route::get('/complaints', [ComplaintController::class, 'index'])
        ->name('complaints.index');

    Route::post('/complaints', [ComplaintController::class, 'store'])
        ->name('complaints.store');

    Route::get('/complaints/qr', [ComplaintController::class, 'qr'])
        ->name('complaints.qr');

    Route::get('/users', [UserManagementController::class, 'index'])
        ->name('users.index');

    Route::post('/users', [UserManagementController::class, 'store'])
        ->name('users.store');

    Route::put('/users/{user}', [UserManagementController::class, 'update'])
        ->name('users.update');

    Route::delete('/users/{user}', [UserManagementController::class, 'destroy'])
        ->name('users.destroy');

    Route::patch('/users/{user}/status', [
        UserManagementController::class,
        'toggleStatus',
    ])->name('users.toggle-status');

    Route::get('/inventory', [InventoryController::class, 'index'])
        ->name('inventory.index');

    Route::post('/inventory', [InventoryController::class, 'store'])
        ->name('inventory.store');

    Route::delete('/inventory/{item}', [InventoryController::class, 'destroy'])
        ->name('inventory.destroy');

    Route::patch('/inventory/{item}/restore', [InventoryController::class, 'restore'])
        ->name('inventory.restore');

    Route::post('/inventory/{item}/stock', [InventoryController::class, 'addStock'])
        ->name('inventory.stock.store');

    Route::get('/inventory/transactions/qr', [InventoryTransactionQrController::class, 'show'])
        ->name('inventory.transactions.qr');

    Route::get('/inventory/transactions', [InventoryTransactionController::class, 'index'])
        ->name('inventory.transactions.index');

    Route::post('/inventory/transactions', [InventoryTransactionController::class, 'store'])
        ->name('inventory.transactions.store');

    Route::get('/inventory/categories', [InventoryCategoryController::class, 'index'])
        ->name('inventory.categories.index');

    Route::post('/inventory/categories', [InventoryCategoryController::class, 'store'])
        ->name('inventory.categories.store');

    Route::put('/inventory/categories/{category}', [InventoryCategoryController::class, 'update'])
        ->name('inventory.categories.update');

    Route::patch('/inventory/categories/{category}/status', [InventoryCategoryController::class, 'toggleStatus'])
        ->name('inventory.categories.toggle-status');

});

Route::get('/keluhan', [ComplaintController::class, 'publicCreate'])
    ->name('complaints.public');

Route::post('/keluhan', [ComplaintController::class, 'publicStore'])
    ->middleware('throttle:5,1')
    ->name('complaints.public.store');

Route::get('/i/transaksi', [PublicInventoryTransactionController::class, 'show'])
    ->name('inventory.public');

Route::post('/i/transaksi', [PublicInventoryTransactionController::class, 'borrow'])
    ->middleware('throttle:10,1')
    ->name('inventory.public.borrow');

Route::post('/i/transaksi/cari-pinjaman', [PublicInventoryTransactionController::class, 'searchLoans'])
    ->middleware('throttle:10,1')
    ->name('inventory.public.loans.search');

Route::post('/i/transaksi/kembali', [PublicInventoryTransactionController::class, 'recordReturn'])
    ->middleware('throttle:10,1')
    ->name('inventory.public.return');

Route::get('/i/{token}', fn (string $token): RedirectResponse => redirect()->route('inventory.public'))
    ->name('inventory.scan.legacy');

Route::post('/logout', [AuthController::class, 'logout'])
    ->middleware('auth')
    ->name('logout');

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware('auth')->name('dashboard');
