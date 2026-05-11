<?php

use App\Http\Controllers\Bookings\BookingController;
use App\Http\Controllers\Departments\DepartmentController;
use App\Http\Controllers\Expenses\ExpenseController;
use App\Http\Controllers\Forecasts\ForecastController;
use App\Http\Controllers\Guests\GuestController;
use App\Http\Controllers\Inventory\InventoryController;
use App\Http\Controllers\Invoices\InvoiceController;
use App\Http\Controllers\Payments\PaymentController;
use App\Http\Controllers\Reports\ReportController;
use App\Http\Controllers\Rooms\RoomController;
use App\Http\Controllers\Staff\StaffController;
use App\Http\Controllers\Teams\TeamInvitationController;
use App\Http\Middleware\EnsureTeamMembership;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::inertia('/', 'Welcome', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('home');

Route::prefix('{current_team}')
    ->middleware(['auth', 'verified', EnsureTeamMembership::class])
    ->group(function () {
        Route::inertia('dashboard', 'Dashboard')->name('dashboard');

        Route::get('departments', [DepartmentController::class, 'index'])->name('departments.index');

        Route::get('staff', [StaffController::class, 'index'])->name('staff.index');

        Route::get('rooms', [RoomController::class, 'index'])->name('rooms.index');

        Route::get('bookings', [BookingController::class, 'index'])->name('bookings.index');

        Route::get('invoices', [InvoiceController::class, 'index'])->name('invoices.index');

        Route::get('payments', [PaymentController::class, 'index'])->name('payments.index');

        Route::get('expenses', [ExpenseController::class, 'index'])->name('expenses.index');

        Route::get('guests', [GuestController::class, 'index'])->name('guests.index');

        Route::get('inventory', [InventoryController::class, 'index'])->name('inventory.index');

        Route::inertia('usage-guide', 'UsageGuide')->name('usage-guide');

        Route::post('bookings', [BookingController::class, 'store'])->name('bookings.store');
        Route::post('bookings/{booking}/process-payment', [BookingController::class, 'processPayment'])->name('bookings.process-payment');
        Route::post('bookings/{booking}/checkout', [BookingController::class, 'checkout'])->name('bookings.checkout');
        Route::post('bookings/{booking}/extend-stay', [BookingController::class, 'extendStay'])->name('bookings.extend-stay');
        Route::get('bookings/{booking}/edit', [BookingController::class, 'edit'])->name('bookings.edit');
        Route::patch('bookings/{booking}', [BookingController::class, 'update'])->name('bookings.update');

        Route::post('payments', [PaymentController::class, 'store'])->name('payments.store');

        Route::post('guests', [GuestController::class, 'store'])->name('guests.store');
        Route::get('guests/{guest}/edit', [GuestController::class, 'edit'])->name('guests.edit');
        Route::patch('guests/{guest}', [GuestController::class, 'update'])->name('guests.update');

        Route::middleware(EnsureTeamMembership::class.':admin')->group(function () {
            Route::get('reports', [ReportController::class, 'index'])->name('reports.index');

            Route::get('forecasts', [ForecastController::class, 'index'])->name('forecasts.index');

            Route::post('departments', [DepartmentController::class, 'store'])->name('departments.store');
            Route::get('departments/{department}/edit', [DepartmentController::class, 'edit'])->name('departments.edit');
            Route::patch('departments/{department}', [DepartmentController::class, 'update'])->name('departments.update');
            Route::delete('departments/{department}', [DepartmentController::class, 'destroy'])->name('departments.destroy');

            Route::post('staff', [StaffController::class, 'store'])->name('staff.store');
            Route::get('staff/{staff}/edit', [StaffController::class, 'edit'])->name('staff.edit');
            Route::patch('staff/{staff}', [StaffController::class, 'update'])->name('staff.update');
            Route::delete('staff/{staff}', [StaffController::class, 'destroy'])->name('staff.destroy');

            Route::post('rooms', [RoomController::class, 'store'])->name('rooms.store');
            Route::get('rooms/{room}/edit', [RoomController::class, 'edit'])->name('rooms.edit');
            Route::patch('rooms/{room}', [RoomController::class, 'update'])->name('rooms.update');
            Route::delete('rooms/{room}', [RoomController::class, 'destroy'])->name('rooms.destroy');

            Route::delete('bookings/{booking}', [BookingController::class, 'destroy'])->name('bookings.destroy');

            Route::post('invoices', [InvoiceController::class, 'store'])->name('invoices.store');
            Route::get('invoices/{invoice}/edit', [InvoiceController::class, 'edit'])->name('invoices.edit');
            Route::patch('invoices/{invoice}', [InvoiceController::class, 'update'])->name('invoices.update');
            Route::delete('invoices/{invoice}', [InvoiceController::class, 'destroy'])->name('invoices.destroy');

            Route::get('payments/{payment}/edit', [PaymentController::class, 'edit'])->name('payments.edit');
            Route::patch('payments/{payment}', [PaymentController::class, 'update'])->name('payments.update');
            Route::delete('payments/{payment}', [PaymentController::class, 'destroy'])->name('payments.destroy');

            Route::post('expenses', [ExpenseController::class, 'store'])->name('expenses.store');
            Route::get('expenses/{expense}/edit', [ExpenseController::class, 'edit'])->name('expenses.edit');
            Route::patch('expenses/{expense}', [ExpenseController::class, 'update'])->name('expenses.update');
            Route::delete('expenses/{expense}', [ExpenseController::class, 'destroy'])->name('expenses.destroy');

            Route::delete('guests/{guest}', [GuestController::class, 'destroy'])->name('guests.destroy');

            Route::post('inventory', [InventoryController::class, 'store'])->name('inventory.store');
            Route::post('inventory/categories', [InventoryController::class, 'storeCategory'])->name('inventory.categories.store');
            Route::get('inventory/categories/{inventory_category}/edit', [InventoryController::class, 'editCategory'])->name('inventory.categories.edit');
            Route::patch('inventory/categories/{inventory_category}', [InventoryController::class, 'updateCategory'])->name('inventory.categories.update');
            Route::delete('inventory/categories/{inventory_category}', [InventoryController::class, 'destroyCategory'])->name('inventory.categories.destroy');
            Route::get('inventory/{inventory_item}/edit', [InventoryController::class, 'edit'])->name('inventory.edit');
            Route::patch('inventory/{inventory_item}', [InventoryController::class, 'update'])->name('inventory.update');
            Route::delete('inventory/{inventory_item}', [InventoryController::class, 'destroy'])->name('inventory.destroy');
        });
    });

Route::middleware(['auth'])->group(function () {
    Route::get('invitations/{invitation}/accept', [TeamInvitationController::class, 'accept'])->name('invitations.accept');
});

require __DIR__.'/settings.php';
