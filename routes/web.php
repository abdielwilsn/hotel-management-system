<?php

use App\Http\Controllers\Bookings\BookingController;
use App\Http\Controllers\Bookings\BookingDiscountController;
use App\Http\Controllers\Dashboard\DashboardController;
use App\Http\Controllers\Departments\DepartmentController;
use App\Http\Controllers\Expenses\ExpenseController;
use App\Http\Controllers\Forecasts\ForecastController;
use App\Http\Controllers\Guests\GuestController;
use App\Http\Controllers\Inventory\InventoryController;
use App\Http\Controllers\Invoices\InvoiceController;
use App\Http\Controllers\Payments\PaymentController;
use App\Http\Controllers\Pos\PosController;
use App\Http\Controllers\Pos\PosMenuController;
use App\Http\Controllers\Pos\PosOrderController;
use App\Http\Controllers\Pos\PosOutletController;
use App\Http\Controllers\Reports\ReportController;
use App\Http\Controllers\Rooms\RoomController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\Staff\StaffController;
use App\Http\Controllers\Teams\TeamInvitationController;
use App\Http\Middleware\EnsureTeamMembership;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::prefix('{current_team}')
    ->middleware(['auth', 'verified', EnsureTeamMembership::class])
    ->group(function () {
        /*
         * Point of Sale — Bar & Restaurant operations.
         * Open to any team member, including the restricted POS-staff role. Access to a
         * specific outlet is scoped per user inside the controllers (canAccessPosOutlet).
         */
        Route::get('pos', [PosController::class, 'index'])->name('pos.index');
        Route::get('pos/orders/{pos_order}/receipt', [PosOrderController::class, 'receipt'])->name('pos.orders.receipt');
        Route::get('pos/{pos_outlet}/terminal', [PosController::class, 'terminal'])->name('pos.terminal');
        Route::post('pos/{pos_outlet}/orders', [PosOrderController::class, 'store'])->name('pos.orders.store');
        Route::get('pos/{pos_outlet}/reports', [PosController::class, 'reports'])->name('pos.reports');
        Route::post('pos/{pos_outlet}/stock', [PosController::class, 'storeStock'])->name('pos.stock.store');
        Route::post('pos/{pos_outlet}/stock/receive', [PosController::class, 'receiveStock'])->name('pos.stock.receive');

        /*
         * Everything below requires at least Member access, which keeps the POS-only staff
         * role out of the core hotel modules (bookings, rooms, guests, reports, ...).
         */
        Route::middleware(EnsureTeamMembership::class.':member')->group(function () {
            Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

            Route::get('rooms', [RoomController::class, 'index'])->name('rooms.index');

            Route::get('bookings', [BookingController::class, 'index'])->name('bookings.index');

            Route::get('invoices', [InvoiceController::class, 'index'])->name('invoices.index');

            Route::get('payments', [PaymentController::class, 'index'])->name('payments.index');

            Route::get('guests', [GuestController::class, 'index'])->name('guests.index');

            Route::inertia('usage-guide', 'UsageGuide')->name('usage-guide');

            Route::get('search', [SearchController::class, 'index'])->name('search');

            Route::post('bookings', [BookingController::class, 'store'])->name('bookings.store');
            Route::post('bookings/{booking}/process-payment', [BookingController::class, 'processPayment'])->name('bookings.process-payment');
            Route::post('bookings/{booking}/checkout', [BookingController::class, 'checkout'])->name('bookings.checkout');
            Route::post('bookings/{booking}/extend-stay', [BookingController::class, 'extendStay'])->name('bookings.extend-stay');
            Route::get('bookings/{booking}/edit', [BookingController::class, 'edit'])->name('bookings.edit');
            Route::patch('bookings/{booking}', [BookingController::class, 'update'])->name('bookings.update');

            // Front desk requests a discount; managers approve/reject (in the admin group below).
            Route::post('bookings/{booking}/discounts', [BookingDiscountController::class, 'store'])->name('bookings.discounts.store');

            Route::post('payments', [PaymentController::class, 'store'])->name('payments.store');
            Route::get('payments/{payment}/receipt', [PaymentController::class, 'receipt'])->name('payments.receipt');

            Route::post('guests', [GuestController::class, 'store'])->name('guests.store');
            Route::get('guests/{guest}/edit', [GuestController::class, 'edit'])->name('guests.edit');
            Route::patch('guests/{guest}', [GuestController::class, 'update'])->name('guests.update');

            Route::middleware(EnsureTeamMembership::class.':admin')->group(function () {
                Route::get('reports', [ReportController::class, 'index'])->name('reports.index');

                Route::get('forecasts', [ForecastController::class, 'index'])->name('forecasts.index');

                Route::get('departments', [DepartmentController::class, 'index'])->name('departments.index');
                Route::post('departments', [DepartmentController::class, 'store'])->name('departments.store');
                Route::get('departments/{department}/edit', [DepartmentController::class, 'edit'])->name('departments.edit');
                Route::patch('departments/{department}', [DepartmentController::class, 'update'])->name('departments.update');
                Route::delete('departments/{department}', [DepartmentController::class, 'destroy'])->name('departments.destroy');

                Route::get('staff', [StaffController::class, 'index'])->name('staff.index');
                Route::post('staff', [StaffController::class, 'store'])->name('staff.store');
                Route::get('staff/{staff}/edit', [StaffController::class, 'edit'])->name('staff.edit');
                Route::patch('staff/{staff}', [StaffController::class, 'update'])->name('staff.update');
                Route::delete('staff/{staff}', [StaffController::class, 'destroy'])->name('staff.destroy');

                Route::post('rooms', [RoomController::class, 'store'])->name('rooms.store');
                Route::get('rooms/{room}/edit', [RoomController::class, 'edit'])->name('rooms.edit');
                Route::patch('rooms/{room}', [RoomController::class, 'update'])->name('rooms.update');
                Route::delete('rooms/{room}', [RoomController::class, 'destroy'])->name('rooms.destroy');

                Route::delete('bookings/{booking}', [BookingController::class, 'destroy'])->name('bookings.destroy');

                Route::post('bookings/{booking}/discounts/{discount}/approve', [BookingDiscountController::class, 'approve'])->name('bookings.discounts.approve');
                Route::post('bookings/{booking}/discounts/{discount}/reject', [BookingDiscountController::class, 'reject'])->name('bookings.discounts.reject');

                Route::post('invoices', [InvoiceController::class, 'store'])->name('invoices.store');
                Route::get('invoices/{invoice}/edit', [InvoiceController::class, 'edit'])->name('invoices.edit');
                Route::patch('invoices/{invoice}', [InvoiceController::class, 'update'])->name('invoices.update');
                Route::delete('invoices/{invoice}', [InvoiceController::class, 'destroy'])->name('invoices.destroy');

                Route::get('payments/{payment}/edit', [PaymentController::class, 'edit'])->name('payments.edit');
                Route::patch('payments/{payment}', [PaymentController::class, 'update'])->name('payments.update');
                Route::delete('payments/{payment}', [PaymentController::class, 'destroy'])->name('payments.destroy');

                Route::get('expenses', [ExpenseController::class, 'index'])->name('expenses.index');
                Route::post('expenses', [ExpenseController::class, 'store'])->name('expenses.store');
                Route::get('expenses/{expense}/edit', [ExpenseController::class, 'edit'])->name('expenses.edit');
                Route::patch('expenses/{expense}', [ExpenseController::class, 'update'])->name('expenses.update');
                Route::delete('expenses/{expense}', [ExpenseController::class, 'destroy'])->name('expenses.destroy');

                Route::delete('guests/{guest}', [GuestController::class, 'destroy'])->name('guests.destroy');

                Route::get('inventory', [InventoryController::class, 'index'])->name('inventory.index');
                Route::post('inventory', [InventoryController::class, 'store'])->name('inventory.store');
                Route::post('inventory/categories', [InventoryController::class, 'storeCategory'])->name('inventory.categories.store');
                Route::get('inventory/categories/{inventory_category}/edit', [InventoryController::class, 'editCategory'])->name('inventory.categories.edit');
                Route::patch('inventory/categories/{inventory_category}', [InventoryController::class, 'updateCategory'])->name('inventory.categories.update');
                Route::delete('inventory/categories/{inventory_category}', [InventoryController::class, 'destroyCategory'])->name('inventory.categories.destroy');
                Route::get('inventory/{inventory_item}/edit', [InventoryController::class, 'edit'])->name('inventory.edit');
                Route::patch('inventory/{inventory_item}', [InventoryController::class, 'update'])->name('inventory.update');
                Route::delete('inventory/{inventory_item}', [InventoryController::class, 'destroy'])->name('inventory.destroy');

                /*
                 * Point of Sale — administration (outlets, staff assignment, menus).
                 */
                Route::get('pos/manage', [PosOutletController::class, 'index'])->name('pos.manage');
                Route::post('pos/outlets', [PosOutletController::class, 'store'])->name('pos.outlets.store');
                Route::patch('pos/outlets/{pos_outlet}', [PosOutletController::class, 'update'])->name('pos.outlets.update');
                Route::delete('pos/outlets/{pos_outlet}', [PosOutletController::class, 'destroy'])->name('pos.outlets.destroy');
                Route::post('pos/outlets/{pos_outlet}/staff', [PosOutletController::class, 'assignStaff'])->name('pos.outlets.staff.store');
                Route::delete('pos/outlets/{pos_outlet}/staff/{user}', [PosOutletController::class, 'unassignStaff'])->name('pos.outlets.staff.destroy');

                Route::get('pos/{pos_outlet}/menu', [PosMenuController::class, 'index'])->name('pos.menu');
                Route::post('pos/{pos_outlet}/categories', [PosMenuController::class, 'storeCategory'])->name('pos.categories.store');
                Route::patch('pos/{pos_outlet}/categories/{pos_category}', [PosMenuController::class, 'updateCategory'])->name('pos.categories.update');
                Route::delete('pos/{pos_outlet}/categories/{pos_category}', [PosMenuController::class, 'destroyCategory'])->name('pos.categories.destroy');
                Route::post('pos/{pos_outlet}/items', [PosMenuController::class, 'storeItem'])->name('pos.items.store');
                Route::get('pos/{pos_outlet}/items/{pos_menu_item}/edit', [PosMenuController::class, 'editItem'])->name('pos.items.edit');
                Route::patch('pos/{pos_outlet}/items/{pos_menu_item}', [PosMenuController::class, 'updateItem'])->name('pos.items.update');
                Route::delete('pos/{pos_outlet}/items/{pos_menu_item}', [PosMenuController::class, 'destroyItem'])->name('pos.items.destroy');
            });
        });
    });

Route::middleware(['auth'])->group(function () {
    Route::get('invitations/{invitation}/accept', [TeamInvitationController::class, 'accept'])->name('invitations.accept');
});

require __DIR__.'/settings.php';
