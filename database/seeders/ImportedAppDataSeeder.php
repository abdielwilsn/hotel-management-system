<?php

namespace Database\Seeders;

use App\Models\Booking;
use App\Models\Department;
use App\Models\Expense;
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
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

class ImportedAppDataSeeder extends Seeder
{
    /**
     * Seed imported hospitality data across operational modules.
     */
    public function run(): void
    {
        $team = Team::query()->where('slug', 'anns-haven')->first();

        if ($team) {
            $this->seedTeamData($team);
        }
    }

    private function seedTeamData(Team $team): void
    {
        $departments = $this->seedDepartments($team);
        $this->seedStaff($team, $departments);

        $rooms = $this->seedRooms($team);
        $guests = $this->seedGuests($team);
        $bookings = $this->seedBookings($team, $rooms, $guests);
        $invoices = $this->seedInvoices($team, $bookings);

        $this->seedPayments($team, $invoices);
        $this->seedExpenses($team);

        $items = $this->seedInventory($team);
        $this->seedInventorySales($team, $items, $bookings);
    }

    /**
     * @return array<string, Department>
     */
    private function seedDepartments(Team $team): array
    {
        $rows = [
            ['name' => 'Front Desk', 'description' => 'Guest reception and concierge operations.'],
            ['name' => 'Housekeeping', 'description' => 'Room turnover and public-area sanitation.'],
            ['name' => 'Finance', 'description' => 'Billing, reconciliation, and procurement approvals.'],
            ['name' => 'Operations', 'description' => 'Property maintenance and cross-team coordination.'],
        ];

        $departments = [];

        foreach ($rows as $row) {
            $department = Department::query()->updateOrCreate(
                ['team_id' => $team->id, 'name' => $row['name']],
                [
                    'description' => $row['description'],
                    'status' => 'active',
                ],
            );

            $departments[$row['name']] = $department;
        }

        return $departments;
    }

    /**
     * @param  array<string, Department>  $departments
     */
    private function seedStaff(Team $team, array $departments): void
    {
        $rows = [
            [
                'full_name' => 'Ayakang',
                'email' => 'ayakang@annshaven.com',
                'department' => 'Front Desk',
                'role' => 'receptionist',
                'gender' => 'female',
                'salary' => 185000,
            ],
            [
                'full_name' => 'Edidiong',
                'email' => 'edidiong@annshaven.com',
                'department' => 'Front Desk',
                'role' => 'receptionist',
                'gender' => 'female',
                'salary' => 185000,
            ],
            [
                'full_name' => 'Otobong',
                'email' => 'otobong@annshaven.com',
                'department' => 'Front Desk',
                'role' => 'receptionist',
                'gender' => 'male',
                'salary' => 185000,
            ],
            [
                'full_name' => 'Ima-Obong',
                'email' => 'imaobong@annshaven.com',
                'department' => 'Front Desk',
                'role' => 'receptionist',
                'gender' => 'female',
                'salary' => 185000,
            ],
            [
                'full_name' => 'Mr. Ndiana',
                'email' => 'admin@annshaven.com',
                'department' => 'Operations',
                'role' => 'manager',
                'gender' => 'male',
                'salary' => 350000,
            ],
        ];

        foreach ($rows as $row) {
            $staff = Staff::query()->updateOrCreate(
                ['email' => $row['email']],
                [
                    'team_id' => $team->id,
                    'department_id' => $departments[$row['department']]->id,
                    'full_name' => $row['full_name'],
                    'phone' => '+234-800-000-'.random_int(1000, 9999),
                    'address' => 'Victoria Island, Lagos',
                    'gender' => $row['gender'],
                    'role' => $row['role'],
                    'employment_date' => Carbon::now()->subMonths(8)->toDateString(),
                    'salary' => $row['salary'],
                    'emergency_contact_name' => 'Next of Kin',
                    'emergency_contact_phone' => '+234-801-555-0101',
                    'status' => 'active',
                ],
            );

            // Create user account and attach to team
            $roleMap = ['manager' => 'admin', 'admin' => 'admin'];
            $teamRole = $roleMap[$row['role']] ?? 'member';

            $user = User::firstOrCreate(
                ['email' => $staff->email],
                [
                    'name' => $staff->full_name,
                    'password' => Hash::make('password'),
                ],
            );

            if (! $user->teams()->where('team_id', $team->id)->exists()) {
                $user->teams()->attach($team, ['role' => $teamRole]);
            }
        }
    }

    /**
     * @return array<string, Room>
     */
    private function seedRooms(Team $team): array
    {
        $rows = [
            // Floor 1
            ['room_number' => '101', 'name' => 'James Lodge',    'floor' => 1, 'room_type' => 'deluxe',    'capacity' => 2, 'price_per_night' => 26875, 'status' => 'available'],
            ['room_number' => '102', 'name' => 'Jolly Haven',    'floor' => 1, 'room_type' => 'deluxe',    'capacity' => 2, 'price_per_night' => 26875, 'status' => 'available'],
            ['room_number' => '104', 'name' => 'Cleopatra',      'floor' => 1, 'room_type' => 'double',    'capacity' => 2, 'price_per_night' => 22575, 'status' => 'available'],
            // Floor 2
            ['room_number' => '205', 'name' => 'Happy Daze',     'floor' => 2, 'room_type' => 'suite',     'capacity' => 2, 'price_per_night' => 32250, 'status' => 'available'],
            ['room_number' => '206', 'name' => 'Celebrity',      'floor' => 2, 'room_type' => 'suite',     'capacity' => 2, 'price_per_night' => 32250, 'status' => 'available'],
            ['room_number' => '207', 'name' => 'Josh Lodge',     'floor' => 2, 'room_type' => 'suite',     'capacity' => 2, 'price_per_night' => 32250, 'status' => 'available'],
            ['room_number' => '208', 'name' => "Ann's Haven",    'floor' => 2, 'room_type' => 'penthouse', 'capacity' => 3, 'price_per_night' => 43000, 'status' => 'available'],
            ['room_number' => '209', 'name' => 'Emmy Villa',     'floor' => 2, 'room_type' => 'deluxe',    'capacity' => 2, 'price_per_night' => 26875, 'status' => 'available'],
            ['room_number' => '210', 'name' => 'Sam Haven',      'floor' => 2, 'room_type' => 'suite',     'capacity' => 2, 'price_per_night' => 32250, 'status' => 'available'],
            ['room_number' => '211', 'name' => 'Pacific Breeze', 'floor' => 2, 'room_type' => 'deluxe',    'capacity' => 2, 'price_per_night' => 26875, 'status' => 'available'],
            ['room_number' => '212', 'name' => 'Blossom Villa',  'floor' => 2, 'room_type' => 'double',    'capacity' => 2, 'price_per_night' => 22575, 'status' => 'available'],
            ['room_number' => '213', 'name' => 'Helen of Troy',  'floor' => 2, 'room_type' => 'suite',     'capacity' => 2, 'price_per_night' => 32250, 'status' => 'available'],
            ['room_number' => '214', 'name' => 'Wilberforce',    'floor' => 2, 'room_type' => 'deluxe',    'capacity' => 2, 'price_per_night' => 26875, 'status' => 'available'],
            ['room_number' => '215', 'name' => 'Elegance Suite', 'floor' => 2, 'room_type' => 'deluxe',    'capacity' => 2, 'price_per_night' => 26875, 'status' => 'available'],
            ['room_number' => '216', 'name' => 'Sunrise Villa',  'floor' => 2, 'room_type' => 'deluxe',    'capacity' => 2, 'price_per_night' => 26875, 'status' => 'available'],
            ['room_number' => '217', 'name' => 'Ocean Green',    'floor' => 2, 'room_type' => 'double',    'capacity' => 2, 'price_per_night' => 22575, 'status' => 'available'],
            ['room_number' => '218', 'name' => 'Florida',        'floor' => 2, 'room_type' => 'double',    'capacity' => 2, 'price_per_night' => 22575, 'status' => 'available'],
            ['room_number' => '219', 'name' => 'Mandela',        'floor' => 2, 'room_type' => 'double',    'capacity' => 2, 'price_per_night' => 22575, 'status' => 'available'],
            ['room_number' => '220', 'name' => 'Clinton',        'floor' => 2, 'room_type' => 'double',    'capacity' => 2, 'price_per_night' => 22575, 'status' => 'available'],
            ['room_number' => '222', 'name' => 'Rose',           'floor' => 2, 'room_type' => 'deluxe',    'capacity' => 2, 'price_per_night' => 26875, 'status' => 'available'],
            ['room_number' => '223', 'name' => 'Oasis',          'floor' => 2, 'room_type' => 'deluxe',    'capacity' => 2, 'price_per_night' => 26875, 'status' => 'available'],
            ['room_number' => '224', 'name' => 'Macbeth',        'floor' => 2, 'room_type' => 'deluxe',    'capacity' => 2, 'price_per_night' => 26875, 'status' => 'available'],
            ['room_number' => '225', 'name' => 'Ibom',           'floor' => 2, 'room_type' => 'deluxe',    'capacity' => 2, 'price_per_night' => 26875, 'status' => 'available'],
            ['room_number' => '226', 'name' => 'Obama',          'floor' => 2, 'room_type' => 'deluxe',    'capacity' => 2, 'price_per_night' => 26875, 'status' => 'available'],
            ['room_number' => '227', 'name' => 'Azikiwe',        'floor' => 2, 'room_type' => 'deluxe',    'capacity' => 2, 'price_per_night' => 26875, 'status' => 'available'],
            ['room_number' => '228', 'name' => 'Nelson',         'floor' => 2, 'room_type' => 'deluxe',    'capacity' => 2, 'price_per_night' => 26875, 'status' => 'available'],
            ['room_number' => '229', 'name' => 'Thomas Edison',  'floor' => 2, 'room_type' => 'deluxe',    'capacity' => 2, 'price_per_night' => 26875, 'status' => 'available'],
            ['room_number' => '230', 'name' => 'Anderson',       'floor' => 2, 'room_type' => 'deluxe',    'capacity' => 2, 'price_per_night' => 26875, 'status' => 'available'],
            ['room_number' => '231', 'name' => 'Newton',         'floor' => 2, 'room_type' => 'deluxe',    'capacity' => 2, 'price_per_night' => 26875, 'status' => 'available'],
            ['room_number' => '232', 'name' => 'Queen Elizabeth', 'floor' => 2, 'room_type' => 'deluxe',   'capacity' => 2, 'price_per_night' => 26875, 'status' => 'available'],
            ['room_number' => '233', 'name' => 'Aristotle',      'floor' => 2, 'room_type' => 'deluxe',    'capacity' => 2, 'price_per_night' => 26875, 'status' => 'available'],
            ['room_number' => '234', 'name' => 'Plato',          'floor' => 2, 'room_type' => 'deluxe',    'capacity' => 2, 'price_per_night' => 26875, 'status' => 'available'],
        ];

        $rooms = [];

        foreach ($rows as $row) {
            $room = Room::query()->updateOrCreate(
                ['team_id' => $team->id, 'room_number' => $row['room_number']],
                [
                    'floor' => $row['floor'],
                    'room_type' => $row['room_type'],
                    'capacity' => $row['capacity'],
                    'price_per_night' => $row['price_per_night'],
                    'status' => $row['status'],
                    'description' => $row['name'],
                    'features' => ['wifi' => true, 'ac' => true, 'tv' => true],
                ],
            );

            $rooms[$row['room_number']] = $room;
        }

        return $rooms;
    }

    /**
     * @return array<string, Guest>
     */
    private function seedGuests(Team $team): array
    {
        $prefix = str_replace('-', '.', $team->slug);

        $rows = [
            ['first_name' => 'Cristian', 'last_name' => 'Okoro', 'email' => "cristian.{$prefix}@guest.test", 'tier' => 'gold', 'points' => 1420],
            ['first_name' => 'Ronesecure', 'last_name' => 'Adeyemi', 'email' => "ronesecure.{$prefix}@guest.test", 'tier' => 'silver', 'points' => 860],
            ['first_name' => 'Sobir', 'last_name' => 'Bamidele', 'email' => "sobir.{$prefix}@guest.test", 'tier' => 'standard', 'points' => 120],
        ];

        $guests = [];

        foreach ($rows as $row) {
            $guest = Guest::query()->updateOrCreate(
                ['team_id' => $team->id, 'email' => $row['email']],
                [
                    'first_name' => $row['first_name'],
                    'last_name' => $row['last_name'],
                    'phone' => '+234-809-000-1234',
                    'loyalty_tier' => $row['tier'],
                    'loyalty_points' => $row['points'],
                    'last_stay_date' => Carbon::now()->subDays(14)->toDateString(),
                    'preferences' => 'Late checkout when available',
                    'notes' => 'Imported from prior hotel app export.',
                ],
            );

            $guests[$guest->full_name] = $guest;
        }

        return $guests;
    }

    /**
     * @param  array<string, Room>  $rooms
     * @param  array<string, Guest>  $guests
     * @return array<string, Booking>
     */
    private function seedBookings(Team $team, array $rooms, array $guests): array
    {
        $today = Carbon::today();

        $rows = [
            [
                'ref' => 'BK-1001',
                'guest' => 'Cristian Okoro',
                'room' => '205',
                'check_in' => $today->copy()->subDay(),
                'check_out' => $today->copy()->addDay(),
                'status' => 'checked_in',
            ],
            [
                'ref' => 'BK-1002',
                'guest' => 'Ronesecure Adeyemi',
                'room' => '208',
                'check_in' => $today->copy(),
                'check_out' => $today->copy()->addDays(4),
                'status' => 'confirmed',
            ],
            [
                'ref' => 'BK-1003',
                'guest' => 'Sobir Bamidele',
                'room' => '102',
                'check_in' => $today->copy()->addDay(),
                'check_out' => $today->copy()->addDays(3),
                'status' => 'pending',
            ],
        ];

        $bookings = [];

        foreach ($rows as $row) {
            $room = $rooms[$row['room']];
            $guest = $guests[$row['guest']];
            $nights = max(1, $row['check_in']->diffInDays($row['check_out']));
            $total = $nights * (float) $room->price_per_night;

            $booking = Booking::query()->updateOrCreate(
                [
                    'team_id' => $team->id,
                    'room_id' => $room->id,
                    'guest_email' => (string) $guest->email,
                    'check_in_date' => $row['check_in']->toDateString(),
                ],
                [
                    'guest_name' => $guest->full_name,
                    'guest_phone' => (string) ($guest->phone ?? '+234-000-000-0000'),
                    'number_of_guests' => min($room->capacity, 2),
                    'check_out_date' => $row['check_out']->toDateString(),
                    'price_per_night' => $room->price_per_night,
                    'total_amount' => $total,
                    'status' => $row['status'],
                    'notes' => 'Imported booking '.$row['ref'],
                ],
            );

            $bookings[$row['ref']] = $booking;
        }

        return $bookings;
    }

    /**
     * @param  array<string, Booking>  $bookings
     * @return array<string, Invoice>
     */
    private function seedInvoices(Team $team, array $bookings): array
    {
        $prefix = 'AH';

        $rows = [
            ['invoice_number' => "{$prefix}-INV-2001", 'booking_ref' => 'BK-1001', 'status' => 'partially_paid', 'paid' => 120000],
            ['invoice_number' => "{$prefix}-INV-2002", 'booking_ref' => 'BK-1002', 'status' => 'issued', 'paid' => 0],
            ['invoice_number' => "{$prefix}-INV-2003", 'booking_ref' => 'BK-1003', 'status' => 'draft', 'paid' => 0],
        ];

        $invoices = [];

        foreach ($rows as $row) {
            $booking = $bookings[$row['booking_ref']];
            $subtotal = (float) $booking->total_amount;
            $taxAmount = round($subtotal * 0.075, 2);
            $total = $subtotal + $taxAmount;

            $invoice = Invoice::query()->updateOrCreate(
                [
                    'team_id' => $team->id,
                    'invoice_number' => $row['invoice_number'],
                ],
                [
                    'booking_id' => $booking->id,
                    'guest_name' => $booking->guest_name,
                    'guest_email' => $booking->guest_email,
                    'issue_date' => Carbon::today()->subDays(1)->toDateString(),
                    'due_date' => Carbon::today()->addDays(5)->toDateString(),
                    'subtotal' => $subtotal,
                    'tax_amount' => $taxAmount,
                    'discount_amount' => 0,
                    'total_amount' => $total,
                    'paid_amount' => min($row['paid'], $total),
                    'status' => $row['status'],
                    'notes' => 'Imported from legacy invoice register.',
                ],
            );

            $invoices[$row['invoice_number']] = $invoice;
        }

        return $invoices;
    }

    /**
     * @param  array<string, Invoice>  $invoices
     */
    private function seedPayments(Team $team, array $invoices): void
    {
        $rows = [
            ['payment_number' => 'PAY-5001', 'invoice_pick' => 0, 'amount' => 120000, 'method' => 'bank_transfer', 'status' => 'completed'],
            ['payment_number' => 'PAY-5002', 'invoice_pick' => 1, 'amount' => 50000, 'method' => 'card', 'status' => 'pending'],
        ];

        $invoiceValues = array_values($invoices);

        foreach ($rows as $row) {
            $invoice = $invoiceValues[$row['invoice_pick'] % count($invoiceValues)];

            Payment::query()->updateOrCreate(
                [
                    'team_id' => $team->id,
                    'payment_number' => strtoupper($team->slug).'-'.$row['payment_number'],
                ],
                [
                    'invoice_id' => $invoice->id,
                    'payment_date' => Carbon::today()->toDateString(),
                    'amount' => min($row['amount'], (float) $invoice->total_amount),
                    'method' => $row['method'],
                    'status' => $row['status'],
                    'reference' => 'LEGACY-'.strtoupper($team->slug),
                    'notes' => 'Imported payment transaction.',
                ],
            );
        }
    }

    private function seedExpenses(Team $team): void
    {
        $rows = [
            ['title' => 'Diesel Generator Refill', 'category' => 'utilities', 'amount' => 185000],
            ['title' => 'Laundry Detergents', 'category' => 'supplies', 'amount' => 64000],
            ['title' => 'Air Conditioning Maintenance', 'category' => 'maintenance', 'amount' => 220000],
            ['title' => 'Digital Marketing Campaign', 'category' => 'marketing', 'amount' => 95000],
        ];

        foreach ($rows as $index => $row) {
            Expense::query()->updateOrCreate(
                [
                    'team_id' => $team->id,
                    'title' => $row['title'],
                    'incurred_date' => Carbon::today()->subDays($index + 2)->toDateString(),
                ],
                [
                    'category' => $row['category'],
                    'amount' => $row['amount'],
                    'vendor' => 'Legacy Vendor '.($index + 1),
                    'status' => 'paid',
                    'description' => 'Imported expense record from prior system.',
                ],
            );
        }
    }

    /**
     * @return array<string, InventoryItem>
     */
    private function seedInventory(Team $team): array
    {
        $categoryRows = [
            ['external_id' => 'legacy-cat-bev', 'type' => 'bar', 'name' => 'Beverages'],
            ['external_id' => 'legacy-cat-food', 'type' => 'kitchen', 'name' => 'Kitchen Supplies'],
        ];

        $categories = [];

        foreach ($categoryRows as $row) {
            $category = InventoryCategory::query()->updateOrCreate(
                [
                    'team_id' => $team->id,
                    'source_external_id' => strtoupper($team->slug).'-'.$row['external_id'],
                ],
                [
                    'type' => $row['type'],
                    'name' => $row['name'],
                    'description' => 'Imported category from external inventory app.',
                ],
            );

            $categories[$row['name']] = $category;
        }

        $itemRows = [
            ['external_id' => 'legacy-item-water', 'category' => 'Beverages', 'name' => 'Bottled Water', 'price' => 1500, 'unit' => 'bottle'],
            ['external_id' => 'legacy-item-soda', 'category' => 'Beverages', 'name' => 'Soft Drink', 'price' => 2500, 'unit' => 'can'],
            ['external_id' => 'legacy-item-rice', 'category' => 'Kitchen Supplies', 'name' => 'Rice (5kg)', 'price' => 22000, 'unit' => 'bag'],
        ];

        $items = [];

        foreach ($itemRows as $row) {
            $item = InventoryItem::query()->updateOrCreate(
                [
                    'team_id' => $team->id,
                    'source_external_id' => strtoupper($team->slug).'-'.$row['external_id'],
                ],
                [
                    'inventory_category_id' => $categories[$row['category']]->id,
                    'name' => $row['name'],
                    'unit_price' => $row['price'],
                    'unit' => $row['unit'],
                    'is_active' => true,
                ],
            );

            $businessDate = Carbon::today()->subDay()->toDateString();
            $opening = 120;
            $newStock = 30;
            $salesQty = 18;
            $closing = $opening + $newStock - $salesQty;

            InventoryStockRecord::query()->updateOrCreate(
                [
                    'inventory_item_id' => $item->id,
                    'business_date' => $businessDate,
                ],
                [
                    'team_id' => $team->id,
                    'source_external_id' => strtoupper($team->slug).'-stock-'.$row['external_id'],
                    'opening_stock' => $opening,
                    'new_stock' => $newStock,
                    'total_stock' => $opening + $newStock,
                    'sales_qty' => $salesQty,
                    'closing_stock' => $closing,
                    'damaged' => 1,
                    'shortage' => 0,
                    'excess' => 0,
                    'sales_value' => $salesQty * $row['price'],
                    'closing_value' => $closing * $row['price'],
                    'recorded_by' => 'System Import',
                    'notes' => 'Imported opening balance and day movement.',
                    'is_closed' => true,
                ],
            );

            $items[$row['name']] = $item;
        }

        return $items;
    }

    /**
     * @param  array<string, InventoryItem>  $items
     * @param  array<string, Booking>  $bookings
     */
    private function seedInventorySales(Team $team, array $items, array $bookings): void
    {
        $rows = [
            ['item' => 'Bottled Water', 'booking_ref' => 'BK-1001', 'qty' => 4, 'payment_mode' => 'room_post'],
            ['item' => 'Soft Drink', 'booking_ref' => 'BK-1002', 'qty' => 2, 'payment_mode' => 'cash'],
        ];

        foreach ($rows as $row) {
            $item = $items[$row['item']];
            $booking = $bookings[$row['booking_ref']];
            $unitPrice = (float) $item->unit_price;

            InventorySale::query()->updateOrCreate(
                [
                    'team_id' => $team->id,
                    'source_external_id' => strtoupper($team->slug).'-sale-'.$row['booking_ref'].'-'.str_replace(' ', '-', strtolower($row['item'])),
                ],
                [
                    'inventory_item_id' => $item->id,
                    'booking_id' => $booking->id,
                    'room_number' => (int) optional($booking->room)->room_number,
                    'guest_name' => $booking->guest_name,
                    'quantity' => $row['qty'],
                    'unit_price' => $unitPrice,
                    'total_amount' => $unitPrice * $row['qty'],
                    'payment_mode' => $row['payment_mode'],
                    'business_date' => Carbon::today()->toDateString(),
                    'officer_name' => 'Front Desk Agent',
                    'sold_at' => Carbon::now(),
                ],
            );
        }
    }
}
