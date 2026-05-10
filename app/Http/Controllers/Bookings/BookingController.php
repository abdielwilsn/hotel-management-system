<?php

namespace App\Http\Controllers\Bookings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Bookings\ProcessBookingPaymentRequest;
use App\Http\Requests\Bookings\SaveBookingRequest;
use App\Models\Booking;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Room;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class BookingController extends Controller
{
    public function index(Request $request, Team $current_team): Response
    {
        Gate::authorize('viewAny', [Booking::class, $current_team]);

        $bookings = $current_team->bookings()
            ->with([
                'room',
                'invoice' => function ($query): void {
                    $query->select([
                        'invoices.id',
                        'invoices.booking_id',
                        'invoices.invoice_number',
                        'invoices.total_amount',
                        'invoices.paid_amount',
                        'invoices.status',
                    ]);
                },
            ])
            ->orderByDesc('check_in_date')
            ->get();

        $rooms = $current_team->rooms()->where('status', '!=', 'maintenance')->get(['id', 'room_number', 'room_type', 'capacity', 'price_per_night']);
        $statuses = ['pending', 'confirmed', 'checked_in', 'checked_out', 'cancelled'];

        return Inertia::render('bookings/Index', [
            'bookings' => $bookings,
            'rooms' => $rooms,
            'statuses' => $statuses,
            'team' => $current_team->only('id', 'slug', 'name'),
        ]);
    }

    public function store(SaveBookingRequest $request, Team $current_team): RedirectResponse
    {
        Gate::authorize('create', [Booking::class, $current_team]);

        $booking = DB::transaction(function () use ($request, $current_team): Booking {
            $data = $request->validated();
            $processPayment = (bool) ($data['process_payment'] ?? false);

            $room = Room::query()->findOrFail($data['room_id']);
            $pricePerNight = (float) $room->price_per_night;
            $nights = max(1, Carbon::parse($data['check_in_date'])->diffInDays($data['check_out_date']));
            $totalAmount = round($pricePerNight * $nights, 2);

            $bookingPayload = Arr::only($data, [
                'room_id',
                'guest_name',
                'guest_email',
                'guest_phone',
                'number_of_guests',
                'check_in_date',
                'check_out_date',
                'status',
                'notes',
            ]);

            $bookingPayload['price_per_night'] = $pricePerNight;
            $bookingPayload['total_amount'] = $totalAmount;

            $booking = $current_team->bookings()->create($bookingPayload);

            // Update room status if booking is checked in
            if ($booking->status === 'checked_in') {
                $room->update(['status' => 'occupied']);
            }

            $invoice = $this->syncBookingInvoice($current_team, $booking);

            if ($processPayment) {
                $this->recordPayment(
                    $current_team,
                    $invoice,
                    [
                        'amount' => (float) $data['payment_amount'],
                        'method' => (string) $data['payment_method'],
                        'payment_date' => (string) $data['payment_date'],
                        'status' => 'completed',
                        'reference' => $data['payment_reference'] ?? null,
                        'notes' => $data['payment_notes'] ?? null,
                    ],
                );

                if ($booking->status === 'pending') {
                    $booking->update(['status' => 'confirmed']);
                }
            }

            return $booking;
        });

        return redirect()->route('bookings.index', $current_team->slug)
            ->with('message', "Booking for {$booking->guest_name} has been created.");
    }

    public function processPayment(ProcessBookingPaymentRequest $request, Team $current_team, Booking $booking): RedirectResponse
    {
        $this->bookingForTeam($current_team, $booking);

        Gate::authorize('create', [Payment::class, $current_team]);

        DB::transaction(function () use ($booking, $current_team, $request): void {
            $invoice = $this->syncBookingInvoice($current_team, $booking);

            $balance = round((float) $invoice->total_amount - (float) $invoice->paid_amount, 2);
            $amount = (float) $request->validated('amount');

            if ($amount > $balance) {
                $amount = $balance;
            }

            if ($amount <= 0) {
                return;
            }

            $this->recordPayment($current_team, $invoice, [
                'amount' => $amount,
                'method' => (string) $request->validated('method'),
                'payment_date' => (string) $request->validated('payment_date'),
                'status' => (string) $request->validated('status'),
                'reference' => $request->validated('reference'),
                'notes' => $request->validated('notes'),
            ]);

            if ($booking->status === 'pending') {
                $booking->update(['status' => 'confirmed']);
            }
        });

        return redirect()->route('bookings.index', $current_team->slug)
            ->with('message', "Payment recorded for {$booking->guest_name}.");
    }

    public function edit(Request $request, Team $current_team, Booking $booking): Response
    {
        $this->bookingForTeam($current_team, $booking);

        Gate::authorize('update', [$booking, $current_team]);

        $rooms = $current_team->rooms()->get(['id', 'room_number', 'room_type', 'capacity', 'price_per_night']);
        $statuses = ['pending', 'confirmed', 'checked_in', 'checked_out', 'cancelled'];

        return Inertia::render('bookings/Edit', [
            'booking' => $booking->load('room'),
            'rooms' => $rooms,
            'statuses' => $statuses,
            'team' => $current_team->only('id', 'slug', 'name'),
        ]);
    }

    public function update(SaveBookingRequest $request, Team $current_team, Booking $booking): RedirectResponse
    {
        $this->bookingForTeam($current_team, $booking);

        Gate::authorize('update', [$booking, $current_team]);

        $data = $request->validated();
        $room = Room::query()->findOrFail($data['room_id']);
        $nights = max(1, Carbon::parse($data['check_in_date'])->diffInDays($data['check_out_date']));
        $data['price_per_night'] = $room->price_per_night;
        $data['total_amount'] = $data['price_per_night'] * $nights;

        $oldStatus = $booking->status;
        $oldRoomId = $booking->room_id;
        $newStatus = $data['status'];

        $booking->update($data);

        // Handle room status changes
        // If room changed, release old room if it was occupied by this booking
        if ($oldRoomId !== (int) $data['room_id']) {
            $oldRoom = Room::query()->find($oldRoomId);
            if ($oldRoom && $oldRoom->status === 'occupied') {
                // Check if there are other checked-in bookings for this room
                $hasOtherCheckedIn = Booking::query()
                    ->where('room_id', $oldRoom->id)
                    ->where('id', '!=', $booking->id)
                    ->whereIn('status', ['checked_in', 'pending', 'confirmed'])
                    ->exists();

                if (! $hasOtherCheckedIn) {
                    $oldRoom->update(['status' => 'available']);
                }
            }
        }

        // Update new room status based on booking status
        if ($newStatus === 'checked_in') {
            $room->update(['status' => 'occupied']);
        } elseif (in_array($newStatus, ['checked_out', 'cancelled'], true)) {
            // Check if there are other active bookings for this room
            $hasOtherActive = Booking::query()
                ->where('room_id', $room->id)
                ->where('id', '!=', $booking->id)
                ->whereIn('status', ['checked_in', 'pending', 'confirmed'])
                ->exists();

            if (! $hasOtherActive) {
                $room->update(['status' => 'available']);
            }
        }

        return redirect()->route('bookings.index', $current_team->slug)
            ->with('message', "Booking for {$booking->guest_name} has been updated.");
    }

    public function destroy(Request $request, Team $current_team, Booking $booking): RedirectResponse
    {
        $this->bookingForTeam($current_team, $booking);

        Gate::authorize('delete', [$booking, $current_team]);

        $name = $booking->guest_name;
        $roomId = $booking->room_id;
        Booking::query()->whereKey($booking->id)->delete();

        // Release room if booking was occupying it
        $room = Room::query()->find($roomId);
        if ($room && $room->status === 'occupied') {
            // Check if there are other active bookings for this room
            $hasOtherActive = Booking::query()
                ->where('room_id', $room->id)
                ->whereIn('status', ['checked_in', 'pending', 'confirmed'])
                ->exists();

            if (! $hasOtherActive) {
                $room->update(['status' => 'available']);
            }
        }

        return redirect()->route('bookings.index', $current_team->slug)
            ->with('message', "Booking for {$name} has been removed.");
    }

    private function bookingForTeam(Team $team, Booking $booking): void
    {
        if ($booking->team_id !== $team->id) {
            abort(403);
        }
    }

    private function syncBookingInvoice(Team $team, Booking $booking): Invoice
    {
        /** @var Invoice $invoice */
        $invoice = $team->invoices()->firstOrCreate(
            ['booking_id' => $booking->id],
            [
                'invoice_number' => $this->generateInvoiceNumber($team),
                'guest_name' => $booking->guest_name,
                'guest_email' => $booking->guest_email,
                'issue_date' => Carbon::today()->toDateString(),
                'due_date' => Carbon::parse($booking->check_in_date)->toDateString(),
                'subtotal' => $booking->total_amount,
                'tax_amount' => 0,
                'discount_amount' => 0,
                'total_amount' => $booking->total_amount,
                'paid_amount' => 0,
                'status' => 'issued',
                'notes' => 'Auto-generated from booking.',
            ],
        );

        $invoice->update([
            'guest_name' => $booking->guest_name,
            'guest_email' => $booking->guest_email,
            'subtotal' => $booking->total_amount,
            'total_amount' => $booking->total_amount,
        ]);

        return $invoice;
    }

    /**
     * @param  array{amount: float, method: string, payment_date: string, status: string, reference: mixed, notes: mixed}  $payload
     */
    private function recordPayment(Team $team, Invoice $invoice, array $payload): void
    {
        $team->payments()->create([
            'invoice_id' => $invoice->id,
            'payment_number' => $this->generatePaymentNumber($team),
            'payment_date' => $payload['payment_date'],
            'amount' => $payload['amount'],
            'method' => $payload['method'],
            'status' => $payload['status'],
            'reference' => is_string($payload['reference']) ? $payload['reference'] : null,
            'notes' => is_string($payload['notes']) ? $payload['notes'] : null,
        ]);

        $this->refreshInvoicePaidAmount($invoice->fresh());
    }

    private function refreshInvoicePaidAmount(Invoice $invoice): void
    {
        $paidAmount = (float) $invoice->payments()
            ->where('status', 'completed')
            ->sum('amount');

        $totalAmount = (float) $invoice->total_amount;
        $status = $invoice->status;

        if ($paidAmount <= 0 && $status !== 'void') {
            $status = 'issued';
        }

        if ($paidAmount >= $totalAmount && $totalAmount > 0) {
            $status = 'paid';
        }

        $invoice->update([
            'paid_amount' => round($paidAmount, 2),
            'status' => $status,
        ]);
    }

    private function generateInvoiceNumber(Team $team): string
    {
        $teamCode = strtoupper(substr(preg_replace('/[^a-z0-9]/i', '', $team->slug), 0, 4));
        $teamCode = $teamCode !== '' ? $teamCode : 'TEAM';

        do {
            $candidate = sprintf(
                '%s-INV-%s-%04d',
                $teamCode,
                Carbon::now()->format('ymd'),
                random_int(1, 9999),
            );
        } while ($team->invoices()->where('invoice_number', $candidate)->exists());

        return $candidate;
    }

    private function generatePaymentNumber(Team $team): string
    {
        $teamCode = strtoupper(substr(preg_replace('/[^a-z0-9]/i', '', $team->slug), 0, 4));
        $teamCode = $teamCode !== '' ? $teamCode : 'TEAM';

        do {
            $candidate = sprintf(
                '%s-PAY-%s-%04d',
                $teamCode,
                Carbon::now()->format('ymd'),
                random_int(1, 9999),
            );
        } while ($team->payments()->where('payment_number', $candidate)->exists());

        return $candidate;
    }
}
