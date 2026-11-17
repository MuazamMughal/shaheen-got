<?php

namespace App\Models;

use App\Enums\PaxType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingPassenger extends Model
{
    protected $fillable = [
        'pax_type', 'title', 'given_name', 'surname', 'passport_no', 'dob',
        'passport_issue_date', 'passport_expiry_date', 'is_lead', 'sort',
    ];

    protected $casts = [
        'pax_type' => PaxType::class,
        'dob' => 'date',
        'passport_issue_date' => 'date',
        'passport_expiry_date' => 'date',
        'is_lead' => 'boolean',
    ];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function fullName(): string
    {
        return trim("{$this->title} {$this->given_name} {$this->surname}");
    }
}
