<?php

namespace App\Support;

use App\Models\Booking;
use App\Models\Department;
use App\Models\Guest;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\InventorySale;
use App\Models\InventoryStockRecord;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Room;
use App\Models\Staff;
use App\Models\Team;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class AnnLedgerImporter
{
    /**
     * @return array<string, int>
     */
    public function import(?string $teamSlug = null): array
    {
        $team = $teamSlug
            ? Team::query()->where('slug', $teamSlug)->firstOrFail()
            : Team::query()->firstOrFail();

        $source = DB::connection('ann_ledger');

        try {
            $existingTables = $this->sourceTables();
        } catch (Throwable) {
            $existingTables = [];
        }

        $table = fn (string $primary) => $existingTables[$primary] ?? null;

        $receptionDepartment = Department::query()->firstOrCreate([
            'team_id' => $team->id,
            'name' => 'Front Desk',
        ], [
            'description' => 'Imported operational front office staff',
            'status' => 'active',
        ]);

        $managementDepartment = Department::query()->firstOrCreate([
            'team_id' => $team->id,
            'name' => 'Management',
        ], [
            'description' => 'Imported management and administration',
            'status' => 'active',
        ]);

        $summary = [
            'staff' => 0,
            'rooms' => 0,
            'bookings' => 0,
            'guests' => 0,
            'invoices' => 0,
            'payments' => 0,
            'inventory_categories' => 0,
            'inventory_items' => 0,
            'inventory_stock_records' => 0,
            'inventory_sales' => 0,
        ];

        $sourceUsers = collect();

        if ($table('User')) {
            $sourceUsers = collect($source->table($table('User'))->get());

            foreach ($sourceUsers as $sourceUser) {
                if (! isset($sourceUser->email)) {
                    continue;
                }

                $role = strtolower((string) ($sourceUser->role ?? 'RECEPTIONIST'));
                $staffRole = $role === 'admin' ? 'admin' : 'receptionist';
                $departmentId = $role === 'admin' ? $managementDepartment->id : $receptionDepartment->id;

                Staff::query()->updateOrCreate(
                    [
                        'team_id' => $team->id,
                        'email' => (string) $sourceUser->email,
                    ],
                    [
                        'department_id' => $departmentId,
                        'full_name' => (string) ($sourceUser->name ?? 'Unknown Staff'),
                        'role' => $staffRole,
                        'phone' => null,
                        'status' => (bool) ($sourceUser->isActive ?? true) ? 'active' : 'inactive',
                        'employment_date' => now()->toDateString(),
                    ],
                );

                $summary['staff']++;
            }
        }

        $sourceRooms = collect();
        $roomIdMap = [];

        if ($table('Room')) {
            $sourceRooms = collect($source->table($table('Room'))->get());

            foreach ($sourceRooms as $sourceRoom) {
                if (! isset($sourceRoom->number)) {
                    continue;
                }

                $roomNumber = (string) $sourceRoom->number;
                $floor = $this->deriveFloorFromRoomNumber($roomNumber);

                $room = Room::query()->updateOrCreate(
                    [
                        'team_id' => $team->id,
                        'room_number' => $roomNumber,
                    ],
                    [
                        'floor' => $floor,
                        'room_type' => $this->mapRoomType((string) ($sourceRoom->category ?? 'STANDARD')),
                        'capacity' => $this->inferCapacity((string) ($sourceRoom->category ?? 'STANDARD')),
                        'price_per_night' => $this->safeAmount($sourceRoom->rackRate ?? 0),
                        'status' => $this->mapRoomStatus((string) ($sourceRoom->status ?? 'AVAILABLE')),
                        'description' => (string) ($sourceRoom->name ?? 'Imported room'),
                        'features' => [
                            'source_code' => (string) ($sourceRoom->code ?? ''),
                            'source_category' => (string) ($sourceRoom->category ?? ''),
                        ],
                    ],
                );

                if (isset($sourceRoom->id)) {
                    $roomIdMap[(string) $sourceRoom->id] = $room->id;
                }

                $summary['rooms']++;
            }
        }

        $bookingMap = [];

        if ($table('Booking')) {
            $sourceBookings = collect($source->table($table('Booking'))->get());

            foreach ($sourceBookings as $sourceBooking) {
                $sourceRoomId = (string) ($sourceBooking->roomId ?? '');
                $targetRoomId = $roomIdMap[$sourceRoomId] ?? null;
                if (! $targetRoomId) {
                    continue;
                }

                $guestName = trim((string) ($sourceBooking->guestName ?? 'Guest'));
                $guestPhone = $this->nullOrString($sourceBooking->guestPhone ?? null);
                $guestEmail = $this->importGuestEmail($guestName, (string) ($sourceBooking->id ?? Str::uuid()));

                $checkIn = $this->safeDate($sourceBooking->checkInDate ?? now());
                $checkOut = $this->safeDate($sourceBooking->checkOutDate ?? now()->addDay());

                $booking = Booking::query()->firstOrCreate(
                    [
                        'team_id' => $team->id,
                        'room_id' => $targetRoomId,
                        'guest_name' => $guestName,
                        'check_in_date' => $checkIn,
                        'check_out_date' => $checkOut,
                        'total_amount' => $this->safeAmount($sourceBooking->totalAmount ?? 0),
                    ],
                    [
                        'guest_email' => $guestEmail,
                        'guest_phone' => $guestPhone,
                        'number_of_guests' => 1,
                        'price_per_night' => $this->safeAmount($sourceBooking->ratePerNight ?? 0),
                        'status' => $this->mapBookingStatus($sourceBooking->checkInDate ?? null, $sourceBooking->actualCheckOut ?? null),
                        'notes' => trim('[ANN_LEDGER] '.(string) ($sourceBooking->remarks ?? '')),
                        'created_at' => $this->safeDateTime($sourceBooking->createdAt ?? now()),
                        'updated_at' => $this->safeDateTime($sourceBooking->updatedAt ?? now()),
                    ],
                );

                if (isset($sourceBooking->id)) {
                    $bookingMap[(string) $sourceBooking->id] = $booking;
                }

                Guest::query()->firstOrCreate(
                    [
                        'team_id' => $team->id,
                        'first_name' => $this->firstName($guestName),
                        'last_name' => $this->lastName($guestName),
                        'phone' => $guestPhone,
                    ],
                    [
                        'email' => null,
                        'loyalty_tier' => 'standard',
                        'loyalty_points' => 0,
                        'last_stay_date' => $checkOut,
                    ],
                );

                $summary['bookings']++;
                $summary['guests']++;
            }
        }

        if ($table('Booking')) {
            $sourceBookings = collect($source->table($table('Booking'))->get());

            foreach ($sourceBookings as $sourceBooking) {
                $sourceBookingId = (string) ($sourceBooking->id ?? '');
                $targetBooking = $bookingMap[$sourceBookingId] ?? null;

                if (! $targetBooking) {
                    continue;
                }

                $invoiceNumber = (string) ($sourceBooking->receiptNumber ?: 'INV-'.$targetBooking->id);
                $guestName = trim((string) ($sourceBooking->guestName ?? 'Guest'));
                $guestEmail = $this->importGuestEmail($guestName, $sourceBookingId);

                $invoice = Invoice::query()->updateOrCreate(
                    [
                        'team_id' => $team->id,
                        'invoice_number' => $invoiceNumber,
                    ],
                    [
                        'booking_id' => $targetBooking->id,
                        'guest_name' => $guestName,
                        'guest_email' => $guestEmail,
                        'issue_date' => $this->safeDate($sourceBooking->checkInDate ?? now()),
                        'due_date' => $this->safeDate($sourceBooking->checkOutDate ?? now()->addDay()),
                        'subtotal' => $this->safeAmount($sourceBooking->totalAmount ?? 0),
                        'tax_amount' => 0,
                        'discount_amount' => 0,
                        'total_amount' => $this->safeAmount($sourceBooking->totalAmount ?? 0),
                        'paid_amount' => $this->safeAmount($sourceBooking->amountPaid ?? 0),
                        'status' => $this->mapInvoiceStatus((string) ($sourceBooking->paymentStatus ?? 'NOT_PAID')),
                        'notes' => trim('[ANN_LEDGER_BOOKING_ID] '.$sourceBookingId),
                    ],
                );

                $summary['invoices']++;

                $initialPaid = (float) ($sourceBooking->amountPaid ?? 0);

                if ($initialPaid > 0) {
                    Payment::query()->updateOrCreate(
                        [
                            'team_id' => $team->id,
                            'payment_number' => 'ANNINIT-'.Str::upper(Str::substr($sourceBookingId, 0, 10)),
                        ],
                        [
                            'invoice_id' => $invoice->id,
                            'payment_date' => $this->safeDate($sourceBooking->checkInDate ?? now()),
                            'amount' => $initialPaid,
                            'method' => $this->mapPaymentMethod((string) ($sourceBooking->paymentMode ?? 'CASH')),
                            'status' => 'completed',
                            'reference' => null,
                            'notes' => 'Imported initial booking payment from Ann\'s Ledger.',
                        ],
                    );

                    $summary['payments']++;
                }
            }
        }

        if ($table('BookingPayment')) {
            $sourcePayments = collect($source->table($table('BookingPayment'))->get());

            foreach ($sourcePayments as $sourcePayment) {
                $sourceBookingId = (string) ($sourcePayment->bookingId ?? '');
                $targetBooking = $bookingMap[$sourceBookingId] ?? null;

                if (! $targetBooking) {
                    continue;
                }

                $invoice = Invoice::query()
                    ->where('team_id', $team->id)
                    ->where('booking_id', $targetBooking->id)
                    ->first();

                if (! $invoice) {
                    continue;
                }

                $paymentNumber = 'ANNPAY-'.Str::upper(Str::substr((string) ($sourcePayment->id ?? Str::uuid()), 0, 12));

                Payment::query()->updateOrCreate(
                    [
                        'team_id' => $team->id,
                        'payment_number' => $paymentNumber,
                    ],
                    [
                        'invoice_id' => $invoice->id,
                        'payment_date' => $this->safeDate($sourcePayment->date ?? now()),
                        'amount' => $this->safeAmount($sourcePayment->amount ?? 0),
                        'method' => $this->mapPaymentMethod((string) ($sourcePayment->paymentMode ?? 'CASH')),
                        'status' => 'completed',
                        'reference' => null,
                        'notes' => 'Imported from Ann\'s Ledger booking payments.',
                    ],
                );

                $summary['payments']++;
            }
        }

        $sourceCategoryMap = [];

        if ($table('InventoryCategory')) {
            $categories = collect($source->table($table('InventoryCategory'))->get());

            foreach ($categories as $sourceCategory) {
                $category = InventoryCategory::query()->updateOrCreate(
                    [
                        'team_id' => $team->id,
                        'source_external_id' => (string) ($sourceCategory->id ?? ''),
                    ],
                    [
                        'type' => (string) ($sourceCategory->type ?? ''),
                        'name' => (string) ($sourceCategory->displayName ?? 'Inventory'),
                        'description' => $this->nullOrString($sourceCategory->description ?? null),
                    ],
                );

                $sourceCategoryMap[(string) ($sourceCategory->id ?? '')] = $category->id;
                $summary['inventory_categories']++;
            }
        }

        $sourceItemMap = [];

        if ($table('InventoryItem')) {
            $items = collect($source->table($table('InventoryItem'))->get());

            foreach ($items as $sourceItem) {
                $targetCategoryId = $sourceCategoryMap[(string) ($sourceItem->categoryId ?? '')] ?? null;

                if (! $targetCategoryId) {
                    continue;
                }

                $item = InventoryItem::query()->updateOrCreate(
                    [
                        'team_id' => $team->id,
                        'source_external_id' => (string) ($sourceItem->id ?? ''),
                    ],
                    [
                        'inventory_category_id' => $targetCategoryId,
                        'name' => (string) ($sourceItem->name ?? 'Unnamed Item'),
                        'unit_price' => $this->safeAmount($sourceItem->unitPrice ?? 0),
                        'unit' => (string) ($sourceItem->unit ?? 'piece'),
                        'is_active' => (bool) ($sourceItem->isActive ?? true),
                    ],
                );

                $sourceItemMap[(string) ($sourceItem->id ?? '')] = $item->id;
                $summary['inventory_items']++;
            }
        }

        if ($table('StockRecord')) {
            $stockRecords = collect($source->table($table('StockRecord'))->get());

            foreach ($stockRecords as $sourceRecord) {
                $targetItemId = $sourceItemMap[(string) ($sourceRecord->itemId ?? '')] ?? null;

                if (! $targetItemId) {
                    continue;
                }

                InventoryStockRecord::query()->updateOrCreate(
                    [
                        'team_id' => $team->id,
                        'source_external_id' => (string) ($sourceRecord->id ?? ''),
                    ],
                    [
                        'inventory_item_id' => $targetItemId,
                        'business_date' => $this->safeDate($sourceRecord->date ?? now()),
                        'opening_stock' => (int) ($sourceRecord->openingStock ?? 0),
                        'new_stock' => (int) ($sourceRecord->newStock ?? 0),
                        'total_stock' => (int) ($sourceRecord->totalStock ?? 0),
                        'sales_qty' => (int) ($sourceRecord->salesQty ?? 0),
                        'closing_stock' => (int) ($sourceRecord->closingStock ?? 0),
                        'damaged' => (int) ($sourceRecord->damaged ?? 0),
                        'shortage' => (int) ($sourceRecord->shortage ?? 0),
                        'excess' => (int) ($sourceRecord->excess ?? 0),
                        'sales_value' => $this->safeAmount($sourceRecord->salesValue ?? 0),
                        'closing_value' => $this->safeAmount($sourceRecord->closingValue ?? 0),
                        'recorded_by' => $this->nullOrString($sourceRecord->recordedBy ?? null),
                        'notes' => $this->nullOrString($sourceRecord->notes ?? null),
                        'is_closed' => (bool) ($sourceRecord->isClosed ?? false),
                    ],
                );

                $summary['inventory_stock_records']++;
            }
        }

        if ($table('InventorySale')) {
            $sales = collect($source->table($table('InventorySale'))->get());
            $officerNames = $sourceUsers->mapWithKeys(fn ($user) => [(string) $user->id => (string) $user->name]);

            foreach ($sales as $sourceSale) {
                $targetItemId = $sourceItemMap[(string) ($sourceSale->itemId ?? '')] ?? null;

                if (! $targetItemId) {
                    continue;
                }

                InventorySale::query()->updateOrCreate(
                    [
                        'team_id' => $team->id,
                        'source_external_id' => (string) ($sourceSale->id ?? ''),
                    ],
                    [
                        'inventory_item_id' => $targetItemId,
                        'booking_id' => null,
                        'room_number' => isset($sourceSale->roomNumber) ? (int) $sourceSale->roomNumber : null,
                        'guest_name' => $this->nullOrString($sourceSale->guestName ?? null),
                        'quantity' => max((int) ($sourceSale->quantity ?? 0), 0),
                        'unit_price' => $this->safeAmount($sourceSale->unitPrice ?? 0),
                        'total_amount' => $this->safeAmount($sourceSale->totalAmount ?? 0),
                        'payment_mode' => Str::lower((string) ($sourceSale->paymentMode ?? 'cash')),
                        'business_date' => $this->safeDate($sourceSale->businessDate ?? now()),
                        'officer_name' => $officerNames[(string) ($sourceSale->officerId ?? '')] ?? null,
                        'sold_at' => $this->safeDateTime($sourceSale->createdAt ?? now()),
                    ],
                );

                $summary['inventory_sales']++;
            }
        }

        if ($this->shouldUseFallback($summary, $existingTables)) {
            $summary = $this->importFallbackSeedData($team, $receptionDepartment->id, $managementDepartment->id, $summary);
        }

        return $summary;
    }

    /**
     * @param  array<string, int>  $summary
     * @param  array<string, string>  $existingTables
     */
    private function shouldUseFallback(array $summary, array $existingTables): bool
    {
        if ($existingTables !== []) {
            return false;
        }

        return array_sum($summary) === 0;
    }

    /**
     * @param  array<string, int>  $summary
     * @return array<string, int>
     */
    private function importFallbackSeedData(Team $team, int $receptionDepartmentId, int $managementDepartmentId, array $summary): array
    {
        $seedUsers = [
            ['name' => 'Ayakang', 'email' => 'ayakang@annshaven.com', 'role' => 'receptionist', 'active' => true],
            ['name' => 'Edidiong', 'email' => 'edidiong@annshaven.com', 'role' => 'receptionist', 'active' => true],
            ['name' => 'Otobong', 'email' => 'otobong@annshaven.com', 'role' => 'receptionist', 'active' => true],
            ['name' => 'Ima-Obong', 'email' => 'imaobong@annshaven.com', 'role' => 'receptionist', 'active' => true],
            ['name' => 'Mr. Ndiana', 'email' => 'admin@annshaven.com', 'role' => 'admin', 'active' => true],
        ];

        foreach ($seedUsers as $seedUser) {
            Staff::query()->updateOrCreate(
                [
                    'team_id' => $team->id,
                    'email' => $seedUser['email'],
                ],
                [
                    'department_id' => $seedUser['role'] === 'admin' ? $managementDepartmentId : $receptionDepartmentId,
                    'full_name' => $seedUser['name'],
                    'role' => $seedUser['role'],
                    'status' => $seedUser['active'] ? 'active' : 'inactive',
                    'employment_date' => now()->toDateString(),
                ],
            );

            $summary['staff']++;
        }

        foreach ($this->fallbackRooms() as $sourceRoom) {
            Room::query()->updateOrCreate(
                [
                    'team_id' => $team->id,
                    'room_number' => (string) $sourceRoom['number'],
                ],
                [
                    'floor' => $this->deriveFloorFromRoomNumber((string) $sourceRoom['number']),
                    'room_type' => $this->mapRoomType($sourceRoom['category']),
                    'capacity' => $this->inferCapacity($sourceRoom['category']),
                    'price_per_night' => $this->safeAmount($sourceRoom['rate']),
                    'status' => 'available',
                    'description' => $sourceRoom['name'],
                    'features' => [
                        'source_code' => $sourceRoom['code'],
                        'source_category' => $sourceRoom['category'],
                    ],
                ],
            );

            $summary['rooms']++;
        }

        $csvCatalog = [
            ['type' => 'BAR', 'name' => 'Bar', 'path' => '/Users/abdiel/ann-s-haven-ledger/ann\'s BAR.csv'],
            ['type' => 'KITCHEN', 'name' => 'Kitchen', 'path' => '/Users/abdiel/ann-s-haven-ledger/ann\'s kitchen.csv'],
            ['type' => 'STORE', 'name' => 'Store', 'path' => '/Users/abdiel/ann-s-haven-ledger/ann\'s STORE.csv'],
            ['type' => 'MINI_SHOP', 'name' => 'Mini-Shop', 'path' => '/Users/abdiel/ann-s-haven-ledger/ANN\'S MINI-SHOP.csv'],
        ];

        foreach ($csvCatalog as $catalog) {
            $category = InventoryCategory::query()->firstOrCreate(
                [
                    'team_id' => $team->id,
                    'name' => $catalog['name'],
                ],
                [
                    'type' => $catalog['type'],
                    'description' => 'Imported from Ann\'s Haven ledger CSV seed.',
                ],
            );

            $summary['inventory_categories']++;

            $items = $this->parseSeedCsvItems($catalog['path']);

            foreach ($items as $item) {
                InventoryItem::query()->updateOrCreate(
                    [
                        'team_id' => $team->id,
                        'inventory_category_id' => $category->id,
                        'name' => $item['name'],
                    ],
                    [
                        'unit_price' => $item['rate'],
                        'unit' => 'piece',
                        'is_active' => true,
                    ],
                );

                $summary['inventory_items']++;
            }
        }

        return $summary;
    }

    /**
     * @return array<int, array{name: string, code: string, category: string, number: int, rate: int}>
     */
    private function fallbackRooms(): array
    {
        return [
            ['number' => 101, 'name' => 'James Lodge', 'code' => 'D1', 'category' => 'DELUXE', 'rate' => 26875],
            ['number' => 102, 'name' => 'Jolly Haven', 'code' => 'D2', 'category' => 'DELUXE', 'rate' => 26875],
            ['number' => 104, 'name' => 'Cleopatra', 'code' => 'S1', 'category' => 'STANDARD', 'rate' => 22575],
            ['number' => 205, 'name' => 'Happy Daze', 'code' => 'R1', 'category' => 'ROYAL', 'rate' => 32250],
            ['number' => 206, 'name' => 'Celebrity', 'code' => 'R2', 'category' => 'ROYAL', 'rate' => 32250],
            ['number' => 207, 'name' => 'Josh Lodge', 'code' => 'R3', 'category' => 'ROYAL', 'rate' => 32250],
            ['number' => 208, 'name' => "Ann's Haven", 'code' => 'ES1', 'category' => 'EXECUTIVE_SUITE', 'rate' => 43000],
            ['number' => 209, 'name' => 'Emmy Villa', 'code' => 'D3', 'category' => 'DELUXE', 'rate' => 26875],
            ['number' => 210, 'name' => 'Sam Haven', 'code' => 'R4', 'category' => 'ROYAL', 'rate' => 32250],
            ['number' => 211, 'name' => 'Pacific Breeze', 'code' => 'D4', 'category' => 'DELUXE', 'rate' => 26875],
            ['number' => 212, 'name' => 'Blossom Villa', 'code' => 'S2', 'category' => 'STANDARD', 'rate' => 22575],
            ['number' => 213, 'name' => 'Helen of Troy', 'code' => 'R5', 'category' => 'ROYAL', 'rate' => 32250],
            ['number' => 214, 'name' => 'Wilberforce', 'code' => 'D5', 'category' => 'DELUXE', 'rate' => 26875],
            ['number' => 215, 'name' => 'Elegance Suite', 'code' => 'D6', 'category' => 'DELUXE', 'rate' => 26875],
            ['number' => 216, 'name' => 'Sunrise Villa', 'code' => 'D7', 'category' => 'DELUXE', 'rate' => 26875],
            ['number' => 217, 'name' => 'Ocean Green', 'code' => 'S3', 'category' => 'STANDARD', 'rate' => 22575],
            ['number' => 218, 'name' => 'Florida', 'code' => 'S4', 'category' => 'STANDARD', 'rate' => 22575],
            ['number' => 219, 'name' => 'Mandela', 'code' => 'S5', 'category' => 'STANDARD', 'rate' => 22575],
            ['number' => 220, 'name' => 'Clinton', 'code' => 'S6', 'category' => 'STANDARD', 'rate' => 22575],
            ['number' => 222, 'name' => 'Rose', 'code' => 'D8', 'category' => 'DELUXE', 'rate' => 26875],
            ['number' => 223, 'name' => 'Oasis', 'code' => 'D9', 'category' => 'DELUXE', 'rate' => 26875],
            ['number' => 224, 'name' => 'Macbeth', 'code' => 'D10', 'category' => 'DELUXE', 'rate' => 26875],
            ['number' => 225, 'name' => 'Ibom', 'code' => 'D11', 'category' => 'DELUXE', 'rate' => 26875],
            ['number' => 226, 'name' => 'Obama', 'code' => 'D12', 'category' => 'DELUXE', 'rate' => 26875],
            ['number' => 227, 'name' => 'Azikiwe', 'code' => 'D13', 'category' => 'DELUXE', 'rate' => 26875],
            ['number' => 228, 'name' => 'Nelson', 'code' => 'D14', 'category' => 'DELUXE', 'rate' => 26875],
            ['number' => 229, 'name' => 'Thomas Edison', 'code' => 'D15', 'category' => 'DELUXE', 'rate' => 26875],
            ['number' => 230, 'name' => 'Anderson', 'code' => 'D16', 'category' => 'DELUXE', 'rate' => 26875],
            ['number' => 231, 'name' => 'Newton', 'code' => 'D17', 'category' => 'DELUXE', 'rate' => 26875],
            ['number' => 232, 'name' => 'Queen Elizabeth', 'code' => 'D18', 'category' => 'DELUXE', 'rate' => 26875],
            ['number' => 233, 'name' => 'Aristotle', 'code' => 'D19', 'category' => 'DELUXE', 'rate' => 26875],
            ['number' => 234, 'name' => 'Plato', 'code' => 'D20', 'category' => 'DELUXE', 'rate' => 26875],
            ['number' => 300, 'name' => 'Conference Hall', 'code' => 'CH1', 'category' => 'CONFERENCE_HALL', 'rate' => 161250],
        ];
    }

    /**
     * @return array<int, array{name: string, rate: float}>
     */
    private function parseSeedCsvItems(string $path): array
    {
        if (! file_exists($path)) {
            return [];
        }

        $handle = fopen($path, 'r');

        if ($handle === false) {
            return [];
        }

        $header = fgetcsv($handle) ?: [];
        $headerMap = collect($header)
            ->map(fn ($value) => Str::upper(trim((string) $value)))
            ->values();

        $itemIndex = max((int) $headerMap->search('ITEMS'), 1);
        $rateIndex = (int) $headerMap->search('RATES');
        $rateIndex = $rateIndex > 0 ? $rateIndex : null;

        $items = [];

        while (($row = fgetcsv($handle)) !== false) {
            $name = trim((string) ($row[$itemIndex] ?? ''));

            if ($name === '' || Str::upper($name) === 'ITEMS') {
                continue;
            }

            $rateRaw = $rateIndex !== null ? trim((string) ($row[$rateIndex] ?? '')) : '';
            $rate = is_numeric(str_replace(',', '', $rateRaw)) ? (float) str_replace(',', '', $rateRaw) : 0.0;

            $items[$name] = [
                'name' => $name,
                'rate' => round($rate, 2),
            ];
        }

        fclose($handle);

        return array_values($items);
    }

    /**
     * @return array<string, string>
     */
    private function sourceTables(): array
    {
        $tables = DB::connection('ann_ledger')
            ->table('information_schema.tables')
            ->where('table_schema', 'public')
            ->pluck('table_name')
            ->all();

        $lookup = array_flip($tables);

        return array_filter([
            'User' => $this->firstExistingTable($lookup, ['User', 'user']),
            'Room' => $this->firstExistingTable($lookup, ['Room', 'room']),
            'Booking' => $this->firstExistingTable($lookup, ['Booking', 'booking']),
            'BookingPayment' => $this->firstExistingTable($lookup, ['BookingPayment', 'bookingpayment', 'booking_payment']),
            'InventoryCategory' => $this->firstExistingTable($lookup, ['InventoryCategory', 'inventorycategory', 'inventory_category']),
            'InventoryItem' => $this->firstExistingTable($lookup, ['InventoryItem', 'inventoryitem', 'inventory_item']),
            'StockRecord' => $this->firstExistingTable($lookup, ['StockRecord', 'stockrecord', 'stock_record']),
            'InventorySale' => $this->firstExistingTable($lookup, ['InventorySale', 'inventorysale', 'inventory_sale']),
        ]);
    }

    /**
     * @param  array<string, int>  $lookup
     */
    private function firstExistingTable(array $lookup, array $options): ?string
    {
        foreach ($options as $option) {
            if (array_key_exists($option, $lookup)) {
                return $option;
            }
        }

        return null;
    }

    private function mapRoomType(string $category): string
    {
        return match (Str::upper($category)) {
            'STANDARD' => 'single',
            'DELUXE' => 'deluxe',
            'ROYAL', 'EXECUTIVE_SUITE' => 'suite',
            'CONFERENCE_HALL' => 'penthouse',
            default => 'double',
        };
    }

    private function inferCapacity(string $category): int
    {
        return match (Str::upper($category)) {
            'STANDARD' => 1,
            'DELUXE' => 2,
            'ROYAL' => 3,
            'EXECUTIVE_SUITE' => 4,
            'CONFERENCE_HALL' => 20,
            default => 2,
        };
    }

    private function mapRoomStatus(string $status): string
    {
        return match (Str::upper($status)) {
            'AVAILABLE' => 'available',
            'MAINTENANCE' => 'maintenance',
            'OCCUPIED', 'RESERVED' => 'occupied',
            default => 'available',
        };
    }

    private function mapBookingStatus(mixed $checkInDate, mixed $actualCheckOut): string
    {
        if ($actualCheckOut) {
            return 'checked_out';
        }

        $checkIn = Carbon::parse($checkInDate);

        if ($checkIn->isFuture()) {
            return 'confirmed';
        }

        return 'checked_in';
    }

    private function mapInvoiceStatus(string $paymentStatus): string
    {
        return match (Str::upper($paymentStatus)) {
            'PAID' => 'paid',
            'PARTIAL' => 'partially_paid',
            default => 'issued',
        };
    }

    private function mapPaymentMethod(string $paymentMode): string
    {
        return match (Str::upper($paymentMode)) {
            'CASH' => 'cash',
            'POS' => 'card',
            'TRANSFER' => 'bank_transfer',
            default => 'other',
        };
    }

    private function safeAmount(mixed $value): float
    {
        return round((float) $value, 2);
    }

    private function safeDate(mixed $value): string
    {
        return Carbon::parse($value)->toDateString();
    }

    private function safeDateTime(mixed $value): string
    {
        return Carbon::parse($value)->toDateTimeString();
    }

    private function deriveFloorFromRoomNumber(string $roomNumber): int
    {
        $digits = preg_replace('/\D+/', '', $roomNumber) ?: '1';

        return max((int) substr($digits, 0, 1), 1);
    }

    private function importGuestEmail(string $guestName, string $sourceBookingId): string
    {
        $slug = Str::slug($guestName);

        return trim($slug !== '' ? $slug : 'guest').'-'.Str::lower(Str::substr($sourceBookingId, 0, 8)).'@import.local';
    }

    private function firstName(string $fullName): string
    {
        $parts = preg_split('/\s+/', trim($fullName)) ?: [];

        return $parts[0] ?? 'Guest';
    }

    private function lastName(string $fullName): string
    {
        $parts = preg_split('/\s+/', trim($fullName)) ?: [];

        if (count($parts) <= 1) {
            return 'Imported';
        }

        array_shift($parts);

        return implode(' ', $parts);
    }

    private function nullOrString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }
}
