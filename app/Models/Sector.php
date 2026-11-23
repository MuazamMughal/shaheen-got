<?php

namespace App\Models;

use App\Enums\TripType;
use App\Models\Concerns\HasActiveAndOrder;
use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sector extends Model
{
    use HasActiveAndOrder, HasFactory, RecordsActivity;

    protected $fillable = ['category_id', 'name', 'origin', 'destination', 'trip_type', 'sort_order', 'is_active'];

    protected $casts = ['is_active' => 'boolean', 'sort_order' => 'integer', 'trip_type' => TripType::class];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
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
