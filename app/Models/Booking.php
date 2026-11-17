<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use App\Enums\BookingStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Booking extends Model
{
    use HasFactory, SoftDeletes, RecordsActivity;

    public const PRICE_FIELDS = ['adult_price', 'child_price', 'infant_price', 'total_price'];

    protected $fillable = [
        'booker_name', 'booker_phone', 'booker_email', 'adults', 'children', 'infants', 'agent_remarks',
    ];

    protected function casts(): array
    {
        return [
            'status' => BookingStatus::class,
            'adults' => 'integer',
            'children' => 'integer',
            'infants' => 'integer',
            'adult_price' => 'integer',
            'child_price' => 'integer',
            'infant_price' => 'integer',
            'total_price' => 'integer',
            'payment_approved_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'rejected_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Human booking number from the id: BK-000123.
        static::created(function (Booking $booking) {
            $booking->forceFill(['booking_no' => 'BK-'.str_pad((string) $booking->id, 6, '0', STR_PAD_LEFT)])->saveQuietly();
        });
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    public function flight(): BelongsTo
    {
        return $this->belongsTo(Flight::class)->withTrashed();
    }

    public function passengers(): HasMany
    {
        return $this->hasMany(BookingPassenger::class)->orderBy('sort');
    }

    public function leadPassenger(): HasOne
    {
        return $this->hasOne(BookingPassenger::class)->where('is_lead', true);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(BookingStatusLog::class)->latest('id');
    }

    public function totalPax(): int
    {
        return $this->adults + $this->children + $this->infants;
    }

    /**
     * Seats this booking occupies. Infants travel on an adult's lap and
     * don't take a seat (standard airline rule).
     */
    public function seatsNeeded(): int
    {
        return $this->adults + $this->children;
    }

    public static function calculateTotal(int $adults, int $children, int $infants, int $adultPrice, int $childPrice, int $infantPrice): int
    {
        return $adults * $adultPrice + $children * $childPrice + $infants * $infantPrice;
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', BookingStatus::open());
    }

    public function scopeStatus(Builder $query, BookingStatus|string $status): Builder
    {
        return $query->where('status', $status);
    }

    protected function activityAttributes(): array
    {
        return ['status', 'payment_status', 'payment_note', 'booker_name', 'booker_phone', 'booker_email', 'adults', 'children', 'infants', 'total_price', 'reject_reason', 'cancel_reason', 'admin_remarks'];
    }
}
