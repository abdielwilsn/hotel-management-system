<?php

namespace App\Http\Controllers\Bookings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Bookings\CheckoutBookingRequest;
use App\Http\Requests\Bookings\ExtendBookingStayRequest;
use App\Http\Requests\Bookings\ProcessBookingPaymentRequest;
use App\Http\Requests\Bookings\SaveBookingRequest;
use App\Models\Booking;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Room;
use App\Models\Team;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class BookingController extends Controller
{
    public function index(Request $request, Team $current_team): Response
    {
        Gate::authorize('viewAny', [Booking::class, $current_team]);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'in:pending,confirmed,checked_in,checked_out,cancelled'],
            'payment_status' => ['nullable', 'string', 'in:unpaid,partial,paid'],
            'room_id' => ['nullable', 'integer'],
            'check_in_from' => ['nullable', 'date'],
            'check_in_to' => ['nullable', 'date'],
            'check_out_from' => ['nullable', 'date'],
            'check_out_to' => ['nullable', 'date'],
        ]);

        $bookings = $current_team->bookings()
            ->with([
                'room',
                'createdBy:id,name',
                'updatedBy:id,name',
                'invoice' => function ($query): void {
                    $query->select([
                        'invoices.id',
                        'invoices.booking_id',
                        'invoices.invoice_number',
                        'invoices.issue_date',
                        'invoices.due_date',
                        'invoices.total_amount',
                        'invoices.paid_amount',
                        'invoices.status',
                    ])->with([
                        'payments' => function ($paymentQuery): void {
                            $paymentQuery
                                ->select([
                                    'payments.id',
                                    'payments.invoice_id',
                                    'payments.created_by_user_id',
                                    'payments.updated_by_user_id',
                                    'payments.payment_number',
                                    'payments.payment_date',
                                    'payments.amount',
                                    'payments.method',
                                    'payments.status',
                                    'payments.reference',
                                ])
                                ->with([
                                    'createdBy:id,name',
                                    'updatedBy:id,name',
                                ])
                                ->orderByDesc('payment_date')
                                ->orderByDesc('id');
                        },
                    ]);
                },
            ])
            ->when($filters['search'] ?? null, function (Builder $query, string $search): void {
                $query->where(function (Builder $subQuery) use ($search): void {
                    $subQuery
                        ->where('guest_name', 'like', "%{$search}%")
                        ->orWhere('guest_email', 'like', "%{$search}%")
                        ->orWhere('guest_phone', 'like', "%{$search}%")
                        ->orWhereHas('room', function (Builder $roomQuery) use ($search): void {
                            $roomQuery->where('room_number', 'like', "%{$search}%");
                        });
                });
            })
            ->when($filters['status'] ?? null, function (Builder $query, string $status): void {
                $query->where('status', $status);
            })
            ->when($filters['room_id'] ?? null, function (Builder $query, int $roomId): void {
                $query->where('room_id', $roomId);
            })
            ->when($filters['check_in_from'] ?? null, function (Builder $query, string $checkInFrom): void {
                $query->whereDate('check_in_date', '>=', $checkInFrom);
            })
            ->when($filters['check_in_to'] ?? null, function (Builder $query, string $checkInTo): void {
                $query->whereDate('check_in_date', '<=', $checkInTo);
            })
            ->when($filters['check_out_from'] ?? null, function (Builder $query, string $checkOutFrom): void {
                $query->whereDate('check_out_date', '>=', $checkOutFrom);
            })
            ->when($filters['check_out_to'] ?? null, function (Builder $query, string $checkOutTo): void {
                $query->whereDate('check_out_date', '<=', $checkOutTo);
            })
            ->when($filters['payment_status'] ?? null, function (Builder $query, string $paymentStatus): void {
                if ($paymentStatus === 'unpaid') {
                    $query->where(function (Builder $subQuery): void {
                        $subQuery
                            ->whereDoesntHave('invoice')
                            ->orWhereHas('invoice', function (Builder $invoiceQuery): void {
                                $invoiceQuery->where('paid_amount', '<=', 0);
                            });
                    });

                    return;
                }

                if ($paymentStatus === 'partial') {
                    $query->whereHas('invoice', function (Builder $invoiceQuery): void {
                        $invoiceQuery
                            ->where('paid_amount', '>', 0)
                            ->whereColumn('paid_amount', '<', 'total_amount');
                    });

                    return;
                }

                $query->whereHas('invoice', function (Builder $invoiceQuery): void {
                    $invoiceQuery
                        ->whereColumn('paid_amount', '>=', 'total_amount')
                        ->where('total_amount', '>', 0);
                });
            })
            ->orderByDesc('check_in_date')
            ->get()
            ->map(function (Booking $booking): Booking {
                $booking->setAttribute('extension_history', $this->extensionHistoryFromNotes($booking->notes));

                return $booking;
            });

        $today = Carbon::today()->toDateString();

        $rooms = $current_team->rooms()
            ->where('status', 'available')
            ->whereDoesntHave('bookings', function (Builder $query) use ($today): void {
                $query
                    ->where('status', 'checked_in')
                    ->orWhere(function (Builder $reservationQuery) use ($today): void {
                        $reservationQuery
                            ->where(function (Builder $statusQuery): void {
                                $statusQuery
                                    ->where('status', 'pending')
                                    ->orWhere('status', 'confirmed');
                            })
                            ->whereDate('check_in_date', '<=', $today)
                            ->whereDate('check_out_date', '>=', $today);
                    });
            })
            ->get(['id', 'room_number', 'room_type', 'capacity', 'price_per_night']);
        $statuses = ['pending', 'confirmed', 'checked_in', 'checked_out', 'cancelled'];

        // Dashboard data
        $now = Carbon::now();
        $upcomingCheckIns = $current_team->bookings()
            ->with('room:id,room_number')
            ->where('status', '!=', 'cancelled')
            ->whereIn('status', ['pending', 'confirmed'])
            ->whereDate('check_in_date', '=', $today)
            ->orderBy('check_in_date')
            ->limit(5)
            ->get(['id', 'room_id', 'guest_name', 'check_in_date']);

        $pendingCheckOuts = $current_team->bookings()
            ->with('room:id,room_number')
            ->where('status', 'checked_in')
            ->whereDate('check_out_date', '=', $today)
            ->orderBy('check_out_date')
            ->limit(5)
            ->get(['id', 'room_id', 'guest_name', 'check_out_date']);

        $pendingPayments = $current_team->bookings()
            ->with(['invoice' => function ($query): void {
                $query->select('invoices.id', 'invoices.booking_id', 'invoices.total_amount', 'invoices.paid_amount', 'invoices.status');
            }])
            ->where('status', '!=', 'cancelled')
            ->whereHas('invoice', function (Builder $query): void {
                $query->where(function (Builder $subQuery): void {
                    $subQuery
                        ->where('paid_amount', '<=', 0)
                        ->orWhereColumn('paid_amount', '<', 'total_amount');
                });
            })
            ->limit(5)
            ->get(['id', 'guest_name', 'check_in_date']);

        // Calendar data for next 14 days
        $calendarStart = Carbon::today();
        $calendarEnd = $calendarStart->clone()->addDays(13);

        $calendarBookings = $current_team->bookings()
            ->with('room:id,room_number,room_type')
            ->where('status', '!=', 'cancelled')
            ->where(function (Builder $query) use ($calendarStart, $calendarEnd): void {
                $query
                    ->whereDate('check_in_date', '<=', $calendarEnd)
                    ->whereDate('check_out_date', '>=', $calendarStart);
            })
            ->orderBy('check_in_date')
            ->get(['id', 'room_id', 'guest_name', 'check_in_date', 'check_out_date', 'status']);

        return Inertia::render('bookings/Index', [
            'bookings' => $bookings,
            'rooms' => $rooms,
            'statuses' => $statuses,
            'paymentStatuses' => ['unpaid', 'partial', 'paid'],
            'filters' => Arr::only($filters, [
                'search',
                'status',
                'payment_status',
                'room_id',
                'check_in_from',
                'check_in_to',
                'check_out_from',
                'check_out_to',
            ]),
            'dashboard' => [
                'upcomingCheckIns' => $upcomingCheckIns,
                'pendingCheckOuts' => $pendingCheckOuts,
                'pendingPayments' => $pendingPayments,
                'calendarBookings' => $calendarBookings,
                'today' => $today,
                'calendarStart' => $calendarStart->toDateString(),
                'calendarEnd' => $calendarEnd->toDateString(),
            ],
            'team' => $current_team->only('id', 'slug', 'name'),
        ]);
    }

    public function store(SaveBookingRequest $request, Team $current_team): RedirectResponse
    {
        Gate::authorize('create', [Booking::class, $current_team]);

        $booking = DB::transaction(function () use ($request, $current_team): Booking {
            $data = $request->validated();
            $processPayment = (bool) ($data['process_payment'] ?? false);
            $actorId = $request->user()?->id;

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
            $bookingPayload['created_by_user_id'] = $actorId;
            $bookingPayload['updated_by_user_id'] = $actorId;

            $booking = $current_team->bookings()->create($bookingPayload);

            if ($booking->status === 'checked_in') {
                $this->markRoomAsOccupied($room);
            }

            $invoice = $this->syncBookingInvoice($current_team, $booking);

            if ($processPayment) {
                $this->recordPayment(
                    $current_team,
                    $invoice,
                    [
                        'actor_id' => $actorId,
                        'amount' => (float) $data['payment_amount'],
                        'method' => (string) $data['payment_method'],
                        'payment_date' => (string) $data['payment_date'],
                        'status' => 'completed',
                        'reference' => $data['payment_reference'] ?? null,
                        'notes' => $data['payment_notes'] ?? null,
                    ],
                );

                if ($booking->status === 'pending') {
                    $booking->update([
                        'status' => 'confirmed',
                        'updated_by_user_id' => $actorId,
                    ]);
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

        $payment = DB::transaction(function () use ($booking, $current_team, $request): ?Payment {
            $actorId = $request->user()?->id;
            $invoice = $this->syncBookingInvoice($current_team, $booking);

            $balance = round((float) $invoice->total_amount - (float) $invoice->paid_amount, 2);
            $amount = (float) $request->validated('amount');

            if ($amount > $balance) {
                $amount = $balance;
            }

            if ($amount <= 0) {
                return null;
            }

            $payment = $this->recordPayment($current_team, $invoice, [
                'actor_id' => $actorId,
                'amount' => $amount,
                'method' => (string) $request->validated('method'),
                'payment_date' => (string) $request->validated('payment_date'),
                'status' => (string) $request->validated('status'),
                'reference' => $request->validated('reference'),
                'notes' => $request->validated('notes'),
            ]);

            if ($booking->status === 'pending') {
                $booking->update([
                    'status' => 'confirmed',
                    'updated_by_user_id' => $actorId,
                ]);
            }

            return $payment;
        });

        if ($payment) {
            return redirect()->route('payments.receipt', [$current_team->slug, $payment->id])
                ->with('message', "Payment recorded for {$booking->guest_name}.");
        }

        return redirect()->route('bookings.index', $current_team->slug)
            ->with('message', "Payment recorded for {$booking->guest_name}.");
    }

    public function checkout(CheckoutBookingRequest $request, Team $current_team, Booking $booking): RedirectResponse
    {
        $this->bookingForTeam($current_team, $booking);

        Gate::authorize('update', [$booking, $current_team]);

        DB::transaction(function () use ($booking, $current_team, $request): void {
            $actorId = $request->user()?->id;
            $invoice = $this->syncBookingInvoice($current_team, $booking);
            $balance = round((float) $invoice->total_amount - (float) $invoice->paid_amount, 2);
            $settlementAmount = round((float) ($request->validated('settlement_amount') ?? 0), 2);

            if ($balance > 0 && $settlementAmount > 0) {
                $this->recordPayment($current_team, $invoice, [
                    'actor_id' => $actorId,
                    'amount' => $settlementAmount,
                    'method' => (string) $request->validated('settlement_method'),
                    'payment_date' => (string) $request->validated('settlement_payment_date'),
                    'status' => 'completed',
                    'reference' => $request->validated('settlement_reference'),
                    'notes' => $request->validated('settlement_notes'),
                ]);
            }

            $booking->update([
                'status' => 'checked_out',
                'updated_by_user_id' => $actorId,
            ]);

            $this->releaseRoomIfNoActiveBookings($booking->room()->first(), $booking->id);
        });

        return redirect()->route('bookings.index', $current_team->slug)
            ->with('message', "{$booking->guest_name} has been checked out.");
    }

    public function extendStay(ExtendBookingStayRequest $request, Team $current_team, Booking $booking): RedirectResponse
    {
        $this->bookingForTeam($current_team, $booking);

        Gate::authorize('update', [$booking, $current_team]);

        DB::transaction(function () use ($booking, $current_team, $request): void {
            $actorId = $request->user()?->id;
            $newCheckOutDate = (string) $request->validated('check_out_date');
            $updatedNotes = $booking->notes;

            if ($request->filled('notes')) {
                $note = trim((string) $request->validated('notes'));
                $updatedNotes = $updatedNotes
                    ? trim($updatedNotes."\nExtension: {$note}")
                    : "Extension: {$note}";
            }

            $nights = max(1, Carbon::parse($booking->check_in_date)->diffInDays($newCheckOutDate));
            $totalAmount = round((float) $booking->price_per_night * $nights, 2);

            $booking->update([
                'check_out_date' => $newCheckOutDate,
                'total_amount' => $totalAmount,
                'notes' => $updatedNotes,
                'updated_by_user_id' => $actorId,
            ]);

            $invoice = $this->syncBookingInvoice($current_team, $booking->fresh());
            $this->refreshInvoicePaidAmount($invoice->fresh());
        });

        return redirect()->route('bookings.index', $current_team->slug)
            ->with('message', "Stay extended for {$booking->guest_name}.");
    }

    public function edit(Request $request, Team $current_team, Booking $booking): Response
    {
        $this->bookingForTeam($current_team, $booking);

        Gate::authorize('update', [$booking, $current_team]);

        $rooms = $current_team->rooms()->get(['id', 'room_number', 'room_type', 'capacity', 'price_per_night']);
        $statuses = ['pending', 'confirmed', 'checked_in', 'checked_out', 'cancelled'];

        return Inertia::render('bookings/Edit', [
            'booking' => $booking->load(['room', 'createdBy:id,name', 'updatedBy:id,name']),
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
        $actorId = $request->user()?->id;
        $room = Room::query()->findOrFail($data['room_id']);
        $nights = max(1, Carbon::parse($data['check_in_date'])->diffInDays($data['check_out_date']));
        $data['price_per_night'] = $room->price_per_night;
        $data['total_amount'] = $data['price_per_night'] * $nights;
        $data['updated_by_user_id'] = $actorId;

        $oldStatus = $booking->status;
        $oldRoomId = $booking->room_id;
        $newStatus = $data['status'];

        $booking->update($data);

        if ($oldRoomId !== (int) $data['room_id']) {
            $oldRoom = Room::query()->find($oldRoomId);
            $this->releaseRoomIfNoActiveBookings($oldRoom, $booking->id);
        }

        if ($newStatus === 'checked_in') {
            $this->markRoomAsOccupied($room);
        } elseif (in_array($newStatus, ['checked_out', 'cancelled'], true)) {
            $this->releaseRoomIfNoActiveBookings($room, $booking->id);
        }

        $invoice = $this->syncBookingInvoice($current_team, $booking->fresh());
        $this->refreshInvoicePaidAmount($invoice->fresh());

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

        $room = Room::query()->find($roomId);
        $this->releaseRoomIfNoActiveBookings($room);

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
            'due_date' => Carbon::parse($booking->check_out_date)->toDateString(),
            'subtotal' => $booking->total_amount,
            'total_amount' => $booking->total_amount,
        ]);

        return $invoice;
    }

    /**
     * @param  array{actor_id?: int|null, amount: float, method: string, payment_date: string, status: string, reference: mixed, notes: mixed}  $payload
     */
    private function recordPayment(Team $team, Invoice $invoice, array $payload): Payment
    {
        $payment = $team->payments()->create([
            'invoice_id' => $invoice->id,
            'created_by_user_id' => $payload['actor_id'] ?? null,
            'updated_by_user_id' => $payload['actor_id'] ?? null,
            'payment_number' => $this->generatePaymentNumber($team),
            'payment_date' => $payload['payment_date'],
            'amount' => $payload['amount'],
            'method' => $payload['method'],
            'status' => $payload['status'],
            'reference' => is_string($payload['reference']) ? $payload['reference'] : null,
            'notes' => is_string($payload['notes']) ? $payload['notes'] : null,
        ]);

        $this->refreshInvoicePaidAmount($invoice->fresh());

        return $payment;
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
        } elseif ($paidAmount > 0 && $paidAmount < $totalAmount) {
            $status = 'partially_paid';
        }

        if ($paidAmount >= $totalAmount && $totalAmount > 0) {
            $status = 'paid';
        }

        $invoice->update([
            'paid_amount' => round($paidAmount, 2),
            'status' => $status,
        ]);
    }

    private function markRoomAsOccupied(?Room $room): void
    {
        if (! $room) {
            return;
        }

        $room->update(['status' => 'occupied']);
    }

    private function releaseRoomIfNoActiveBookings(?Room $room, ?int $excludedBookingId = null): void
    {
        if (! $room) {
            return;
        }

        $hasOtherActive = Booking::query()
            ->where('room_id', $room->id)
            ->when($excludedBookingId, fn (Builder $query, int $bookingId) => $query->where('id', '!=', $bookingId))
            ->whereIn('status', ['checked_in', 'pending', 'confirmed'])
            ->exists();

        if (! $hasOtherActive) {
            $room->update(['status' => 'available']);
        }
    }

    /**
     * @return array<int, array{label: string}>
     */
    private function extensionHistoryFromNotes(?string $notes): array
    {
        if (! is_string($notes) || trim($notes) === '') {
            return [];
        }

        return Collection::make(preg_split('/\r\n|\r|\n/', $notes) ?: [])
            ->map(static fn (?string $line): string => trim((string) $line))
            ->filter(static fn (string $line): bool => str_starts_with($line, 'Extension: '))
            ->map(static fn (string $line): array => ['label' => trim(substr($line, 11))])
            ->values()
            ->all();
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
