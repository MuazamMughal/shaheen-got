<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Agency extends Model
{
    use HasFactory, RecordsActivity;

    protected $fillable = [
        'agency_name', 'owner_name', 'city', 'address', 'phone', 'whatsapp', 'notification_emails',
    ];

    protected $casts = ['notification_emails' => 'array'];

    protected $appends = ['logo_url'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getLogoUrlAttribute(): ?string
    {
        return $this->logo ? Storage::disk('public')->url($this->logo) : null;
    }

    /** Agent login email + extra notification emails, de-duplicated. */
    public function allNotificationEmails(): array
    {
        return array_values(array_unique(array_filter([
            $this->user?->email,
            ...($this->notification_emails ?? []),
        ])));
    }
}
