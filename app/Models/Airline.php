<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use App\Models\Concerns\HasActiveAndOrder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Airline extends Model
{
    use HasActiveAndOrder, HasFactory, RecordsActivity;

    protected $fillable = ['name', 'iata_code', 'logo', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    protected $appends = ['logo_url'];

    public function getLogoUrlAttribute(): ?string
    {
        return $this->logo ? Storage::disk('public')->url($this->logo) : null;
    }

    public function flights(): HasMany
    {
        return $this->hasMany(Flight::class);
    }

    /** Records used by any flight (including deleted ones, for history) are kept. */
    public function deleteBlocker(): ?string
    {
        return $this->flights()->withTrashed()->exists()
            ? 'This is used by flights and cannot be deleted. Deactivate it instead.'
            : null;
    }
}
