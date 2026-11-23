<?php

namespace App\Models;

use App\Models\Concerns\HasActiveAndOrder;
use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BaggageType extends Model
{
    use HasActiveAndOrder, HasFactory, RecordsActivity;

    protected $fillable = ['label', 'description', 'sort_order', 'is_active'];

    protected $casts = ['is_active' => 'boolean', 'sort_order' => 'integer'];

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
