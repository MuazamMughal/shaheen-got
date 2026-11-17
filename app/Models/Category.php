<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use App\Models\Concerns\HasActiveAndOrder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Category extends Model
{
    use HasActiveAndOrder, HasFactory, RecordsActivity;

    protected $fillable = ['name', 'slug', 'country', 'flag_icon', 'image', 'description', 'sort_order', 'is_active'];

    protected $casts = ['is_active' => 'boolean', 'sort_order' => 'integer'];

    protected $appends = ['image_url', 'flag_url'];

    protected static function booted(): void
    {
        static::saving(function (Category $category) {
            if (blank($category->slug) || $category->isDirty('name')) {
                $category->slug = static::uniqueSlug($category->name, $category->id);
            }
        });
    }

    public function sectors(): HasMany
    {
        return $this->hasMany(Sector::class);
    }

    public function getImageUrlAttribute(): ?string
    {
        return $this->image ? Storage::disk('public')->url($this->image) : null;
    }

    public function getFlagUrlAttribute(): ?string
    {
        return $this->flag_icon ? Storage::disk('public')->url($this->flag_icon) : null;
    }

    public function flights(): HasMany
    {
        return $this->hasMany(Flight::class);
    }

    /** Reason this record can't be deleted, or null if it can. */
    public function deleteBlocker(): ?string
    {
        if ($this->flights()->withTrashed()->exists()) {
            return 'This category is used by flights and cannot be deleted. Hide it instead.';
        }

        return $this->sectors()->exists() ? 'This category still has sectors. Delete or move them first.' : null;
    }

    private static function uniqueSlug(string $name, ?int $ignoreId): string
    {
        $base = Str::slug($name) ?: 'category';
        $slug = $base;
        $i = 2;
        while (static::where('slug', $slug)->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }
}
