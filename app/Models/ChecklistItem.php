<?php

namespace App\Models;

use App\Enums\Importance;
use Database\Factories\ChecklistItemFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $category_id
 * @property int|null $parent_id
 * @property string $title
 * @property string|null $content
 * @property Importance $importance
 * @property bool $requires_rating
 * @property int $order
 * @property-read Category $category
 * @property-read ChecklistItem|null $parent
 * @property-read Collection<int, ChecklistItem> $children
 * @property-read Collection<int, MediaItem> $media
 * @property-read Collection<int, Evaluation> $evaluations
 */
class ChecklistItem extends Model
{
    /** @use HasFactory<ChecklistItemFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = ['category_id', 'parent_id', 'title', 'content', 'importance', 'requires_rating', 'order'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'importance' => Importance::class,
            'requires_rating' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return BelongsTo<ChecklistItem, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(ChecklistItem::class, 'parent_id');
    }

    /**
     * @return HasMany<ChecklistItem, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(ChecklistItem::class, 'parent_id')->orderBy('order');
    }

    /**
     * Children all the way down, with their media — eager-load this (instead of
     * `children`) anywhere the full sub-item tree is walked, so depth doesn't
     * turn into one query per node.
     *
     * @return HasMany<ChecklistItem, $this>
     */
    public function childrenRecursive(): HasMany
    {
        return $this->children()->with(['media', 'childrenRecursive']);
    }

    /**
     * @return HasMany<MediaItem, $this>
     */
    public function media(): HasMany
    {
        return $this->hasMany(MediaItem::class)->orderBy('order');
    }

    /**
     * @return HasMany<Evaluation, $this>
     */
    public function evaluations(): HasMany
    {
        return $this->hasMany(Evaluation::class);
    }

    /**
     * A leaf item has no sub-items; only leaves count toward completion.
     */
    public function isLeaf(): bool
    {
        return $this->children()->doesntExist();
    }
}
