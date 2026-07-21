<?php

use App\Http\Controllers\Bookings\BookingController;
use App\Http\Controllers\Bookings\BookingDiscountController;
use App\Http\Controllers\Bookings\BookingStayAdjustmentController;
use App\Http\Controllers\Bookings\StayQuoteController;
use App\Http\Controllers\Dashboard\DashboardController;
use App\Http\Controllers\Departments\DepartmentController;
use App\Http\Controllers\Expenses\ExpenseController;
use App\Http\Controllers\Forecasts\ForecastController;
use App\Http\Controllers\Guests\GuestController;
use App\Http\Controllers\Incidents\IncidentController;
use App\Http\Controllers\Inventory\InventoryController;
use App\Http\Controllers\Invoices\InvoiceController;
use App\Http\Controllers\Payments\PaymentController;
use App\Http\Controllers\Pos\PosController;
use App\Http\Controllers\Pos\PosMenuController;
use App\Http\Controllers\Pos\PosOrderController;
use App\Http\Controllers\Pos\PosOutletController;
use App\Http\Controllers\Reports\ReportController;
use App\Http\Controllers\Rooms\RoomAvailabilityController;
use App\Http\Controllers\Rooms\RoomController;
use App\Http\Controllers\Rooms\RoomTypeController;
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
         * Incidents sit outside the hotel-modules gate on purpose: bar, kitchen
         * and other POS-only departments must be able to report what happened
         * on their shift.
         */
        Route::middleware(EnsureTeamMembership::class.':incidents.view')->group(function () {
            Route::get('incidents', [IncidentController::class, 'index'])->name('incidents.index');
        });

        Route::middleware(EnsureTeamMembership::class.':incidents.report')->group(function () {
            Route::post('incidents', [IncidentController::class, 'store'])->name('incidents.store');
        });

        Route::middleware(EnsureTeamMembership::class.':incidents.resolve')->group(function () {
            Route::patch('incidents/{incident}', [IncidentController::class, 'resolve'])->name('incidents.resolve');
        });

        /*
         * Everything below requires at least Member access, which keeps the POS-only staff
         * role out of the core hotel modules (bookings, rooms, guests, reports, ...).
         */
        Route::middleware(EnsureTeamMembership::class.':member')->group(function () {
            Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

            Route::get('rooms', [RoomController::class, 'index'])->name('rooms.index');

            // Powers the booking wizard's room picker: which rooms are free for
            // a chosen date range.
            Route::get('rooms/availability', RoomAvailabilityController::class)->name('rooms.availability');

            Route::get('bookings', [BookingController::class, 'index'])->name('bookings.index');

            // Live "what would this cost" for the booking form.
            Route::get('bookings/quote', StayQuoteController::class)->name('bookings.quote');

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

            // The desk asks for a different night count; a manager signs it off.
            Route::post('bookings/{booking}/stay-adjustments', [BookingStayAdjustmentController::class, 'store'])->name('bookings.stay-adjustments.store');

            Route::post('payments', [PaymentController::class, 'store'])->name('payments.store');
            Route::get('payments/{payment}/receipt', [PaymentController::class, 'receipt'])->name('payments.receipt');

            Route::post('guests', [GuestController::class, 'store'])->name('guests.store');
            Route::get('guests/{guest}/edit', [GuestController::class, 'edit'])->name('guests.edit');
            Route::patch('guests/{guest}', [GuestController::class, 'update'])->name('guests.update');

            /*
             * Everything below is gated on a specific ability rather than a role,
             * so a manager can hand a module to whoever they like from the role
             * editor without a code change here.
             */
            Route::middleware(EnsureTeamMembership::class.':reports.view')->group(function () {
                Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
            });

            Route::middleware(EnsureTeamMembership::class.':forecasts.view')->group(function () {
                Route::get('forecasts', [ForecastController::class, 'index'])->name('forecasts.index');
            });

            Route::middleware(EnsureTeamMembership::class.':departments.view')->group(function () {
                Route::get('departments', [DepartmentController::class, 'index'])->name('departments.index');
            });

            Route::middleware(EnsureTeamMembership::class.':departments.manage')->group(function () {
                Route::post('departments', [DepartmentController::class, 'store'])->name('departments.store');
                Route::get('departments/{department}/edit', [DepartmentController::class, 'edit'])->name('departments.edit');
                Route::patch('departments/{department}', [DepartmentController::class, 'update'])->name('departments.update');
                Route::delete('departments/{department}', [DepartmentController::class, 'destroy'])->name('departments.destroy');
            });

            Route::middleware(EnsureTeamMembership::class.':staff.view')->group(function () {
                Route::get('staff', [StaffController::class, 'index'])->name('staff.index');
            });

            Route::middleware(EnsureTeamMembership::class.':staff.manage')->group(function () {
                Route::post('staff', [StaffController::class, 'store'])->name('staff.store');
                Route::get('staff/{staff}/edit', [StaffController::class, 'edit'])->name('staff.edit');
                Route::patch('staff/{staff}', [StaffController::class, 'update'])->name('staff.update');
                Route::delete('staff/{staff}', [StaffController::class, 'destroy'])->name('staff.destroy');
            });

            Route::middleware(EnsureTeamMembership::class.':rooms.manage')->group(function () {
                // Managers curate the room types their hotel sells.
                Route::post('room-types', [RoomTypeController::class, 'store'])->name('room-types.store');
                Route::patch('room-types/{room_type}', [RoomTypeController::class, 'update'])->name('room-types.update');
                Route::delete('room-types/{room_type}', [RoomTypeController::class, 'destroy'])->name('room-types.destroy');

                Route::post('rooms', [RoomController::class, 'store'])->name('rooms.store');
                // Constrained to numbers so literal paths like rooms/availability
                // can never be swallowed by the {room} wildcard.
                Route::get('rooms/{room}/edit', [RoomController::class, 'edit'])->name('rooms.edit')->whereNumber('room');
                Route::patch('rooms/{room}', [RoomController::class, 'update'])->name('rooms.update')->whereNumber('room');
                Route::delete('rooms/{room}', [RoomController::class, 'destroy'])->name('rooms.destroy')->whereNumber('room');
            });

            Route::middleware(EnsureTeamMembership::class.':bookings.delete')->group(function () {
                Route::delete('bookings/{booking}', [BookingController::class, 'destroy'])->name('bookings.destroy');
            });

            Route::middleware(EnsureTeamMembership::class.':bookings.discounts.review')->group(function () {
                Route::post('bookings/{booking}/discounts/{discount}/approve', [BookingDiscountController::class, 'approve'])->name('bookings.discounts.approve');
                Route::post('bookings/{booking}/discounts/{discount}/reject', [BookingDiscountController::class, 'reject'])->name('bookings.discounts.reject');
            });

            Route::middleware(EnsureTeamMembership::class.':bookings.stay.review')->group(function () {
                Route::post('bookings/{booking}/stay-adjustments/{adjustment}/approve', [BookingStayAdjustmentController::class, 'approve'])->name('bookings.stay-adjustments.approve');
                Route::post('bookings/{booking}/stay-adjustments/{adjustment}/reject', [BookingStayAdjustmentController::class, 'reject'])->name('bookings.stay-adjustments.reject');
            });

            Route::middleware(EnsureTeamMembership::class.':invoices.manage')->group(function () {
                Route::post('invoices', [InvoiceController::class, 'store'])->name('invoices.store');
                Route::get('invoices/{invoice}/edit', [InvoiceController::class, 'edit'])->name('invoices.edit');
                Route::patch('invoices/{invoice}', [InvoiceController::class, 'update'])->name('invoices.update');
                Route::delete('invoices/{invoice}', [InvoiceController::class, 'destroy'])->name('invoices.destroy');
            });

            Route::middleware(EnsureTeamMembership::class.':payments.manage')->group(function () {
                Route::get('payments/{payment}/edit', [PaymentController::class, 'edit'])->name('payments.edit');
                Route::patch('payments/{payment}', [PaymentController::class, 'update'])->name('payments.update');
                Route::delete('payments/{payment}', [PaymentController::class, 'destroy'])->name('payments.destroy');
            });

            Route::middleware(EnsureTeamMembership::class.':expenses.view')->group(function () {
                Route::get('expenses', [ExpenseController::class, 'index'])->name('expenses.index');
            });

            Route::middleware(EnsureTeamMembership::class.':expenses.manage')->group(function () {
                Route::post('expenses', [ExpenseController::class, 'store'])->name('expenses.store');
                Route::get('expenses/{expense}/edit', [ExpenseController::class, 'edit'])->name('expenses.edit');
                Route::patch('expenses/{expense}', [ExpenseController::class, 'update'])->name('expenses.update');
                Route::delete('expenses/{expense}', [ExpenseController::class, 'destroy'])->name('expenses.destroy');
            });

            Route::middleware(EnsureTeamMembership::class.':guests.delete')->group(function () {
                Route::delete('guests/{guest}', [GuestController::class, 'destroy'])->name('guests.destroy');
            });

            Route::middleware(EnsureTeamMembership::class.':inventory.view')->group(function () {
                Route::get('inventory', [InventoryController::class, 'index'])->name('inventory.index');
            });

            Route::middleware(EnsureTeamMembership::class.':inventory.manage')->group(function () {
                Route::post('inventory', [InventoryController::class, 'store'])->name('inventory.store');
                Route::post('inventory/categories', [InventoryController::class, 'storeCategory'])->name('inventory.categories.store');
                Route::get('inventory/categories/{inventory_category}/edit', [InventoryController::class, 'editCategory'])->name('inventory.categories.edit');
                Route::patch('inventory/categories/{inventory_category}', [InventoryController::class, 'updateCategory'])->name('inventory.categories.update');
                Route::delete('inventory/categories/{inventory_category}', [InventoryController::class, 'destroyCategory'])->name('inventory.categories.destroy');
                Route::get('inventory/{inventory_item}/edit', [InventoryController::class, 'edit'])->name('inventory.edit');
                Route::patch('inventory/{inventory_item}', [InventoryController::class, 'update'])->name('inventory.update');
                Route::delete('inventory/{inventory_item}', [InventoryController::class, 'destroy'])->name('inventory.destroy');
            });

            /*
             * Point of Sale — administration (outlets, staff assignment, menus).
             */
            Route::middleware(EnsureTeamMembership::class.':pos.manage')->group(function () {
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
