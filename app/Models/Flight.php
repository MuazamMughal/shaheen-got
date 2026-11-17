<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use App\Enums\FlightStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Flight extends Model
{
    use HasFactory, SoftDeletes, RecordsActivity;

    /** Price columns — always pass through HidesPrices before reaching the browser. */
    public const PRICE_FIELDS = ['fare_price', 'adult_price', 'child_price', 'infant_price'];

    protected $fillable = [
        'category_id', 'sector_id', 'airline_id', 'baggage_type_id',
        'departure_date', 'arrival_date', 'flight_code', 'departure_time', 'arrival_time',
        'meal', 'fare_price', 'adult_price', 'child_price', 'infant_price',
        'total_seats', 'pnr',
    ];

    protected function casts(): array
    {
        return [
            'departure_date' => 'date',
            'arrival_date' => 'date',
            'meal' => 'boolean',
            'fare_price' => 'integer',
            'adult_price' => 'integer',
            'child_price' => 'integer',
            'infant_price' => 'integer',
            'total_seats' => 'integer',
            'seats_confirmed' => 'integer',
            'status' => FlightStatus::class,
        ];
    }

    protected static function booted(): void
    {
        // Keep live <-> sold_out in step with the seat counters.
        static::saving(function (Flight $flight) {
            if ($flight->status === FlightStatus::Live && $flight->seats_available <= 0) {
                $flight->status = FlightStatus::SoldOut;
            } elseif ($flight->status === FlightStatus::SoldOut && $flight->seats_available > 0) {
                $flight->status = FlightStatus::Live;
            }
        });
    }

    /** Store times as HH:MM:SS on every database (MySQL and SQLite tests alike). */
    public function setDepartureTimeAttribute(?string $value): void
    {
        $this->attributes['departure_time'] = self::normalizeTime($value);
    }

    public function setArrivalTimeAttribute(?string $value): void
    {
        $this->attributes['arrival_time'] = self::normalizeTime($value);
    }

    private static function normalizeTime(?string $value): ?string
    {
        return $value === null ? null : (strlen($value) === 5 ? $value.':00' : $value);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function sector(): BelongsTo
    {
        return $this->belongsTo(Sector::class);
    }

    public function airline(): BelongsTo
    {
        return $this->belongsTo(Airline::class);
    }

    public function baggageType(): BelongsTo
    {
        return $this->belongsTo(BaggageType::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    /** Eager-load the seats held by open (pending / payment approved) requests as `seats_pending`. */
    public function scopeWithPendingSeats(Builder $query): Builder
    {
        return $query->withSum(['bookings as seats_pending_adults' => fn ($q) => $q->open()], 'adults')
            ->withSum(['bookings as seats_pending_children' => fn ($q) => $q->open()], 'children');
    }

    public function getSeatsPendingAttribute(): int
    {
        if (array_key_exists('seats_pending_adults', $this->attributes)) {
            return (int) $this->seats_pending_adults + (int) $this->seats_pending_children;
        }

        return (int) $this->bookings()->open()->sum(\DB::raw('adults + children'));
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getSeatsAvailableAttribute(): int
    {
        return max(0, (int) $this->total_seats - (int) $this->seats_confirmed);
    }

    /** Flights agents can see and book. */
    public function scopeBookable(Builder $query): Builder
    {
        return $query->where('status', FlightStatus::Live)
            ->whereColumn('seats_confirmed', '<', 'total_seats')
            ->whereDate('departure_date', '>=', today());
    }

    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->whereDate('departure_date', '>=', today());
    }

    /** Reason this flight can't be deleted, or null. */
    public function deleteBlocker(): ?string
    {
        if ($this->seats_confirmed > 0) {
            return 'This flight has confirmed seats and cannot be deleted. Close it instead.';
        }

        return $this->bookings()->exists()
            ? 'This flight has bookings and cannot be deleted. Close it instead.'
            : null;
    }

    protected function activityAttributes(): array
    {
        return [...$this->getFillable(), 'status', 'seats_confirmed'];
    }
}
