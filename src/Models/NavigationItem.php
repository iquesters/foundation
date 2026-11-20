<?php

namespace Iquesters\Foundation\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Route;

/**
 * One navigation entry: a module's sidebar item, or (with a parent) a tab of
 * that page. See Services\Navigation\NavigationSync and docs/developer/navigation.md.
 */
class NavigationItem extends Model
{
    public const SOURCE_MODULE = 'module';

    public const SOURCE_ADMIN = 'admin';

    /** Fixed items the platform adds: a module's dashboard and an item's own-page tab. Movable, not editable. */
    public const SOURCE_SYSTEM = 'system';

    public const DASHBOARD_ROUTE = 'module.dashboard';

    protected $table = 'navigation_items';

    protected $fillable = [
        'uid', 'ref_module', 'ref_parent', 'key', 'label', 'icon', 'route', 'params',
        'table_schema_uid', 'url', 'permission', 'sort', 'status', 'source', 'customized',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'params' => 'array',
        'customized' => 'boolean',
        'sort' => 'integer',
    ];

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class, 'ref_module');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'ref_parent');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'ref_parent')->orderBy('sort')->orderBy('id');
    }

    public function metas(): HasMany
    {
        return $this->hasMany(NavigationItemMeta::class, 'ref_parent');
    }

    public function getMeta(string $key): ?string
    {
        $value = $this->metas()->where('meta_key', $key)->value('meta_value');

        return $value === null ? null : (string) $value;
    }

    /** Set (or with null, remove) an extra setting of the item. */
    public function setMeta(string $key, mixed $value): void
    {
        if ($value === null) {
            $this->metas()->where('meta_key', $key)->delete();

            return;
        }

        $this->metas()->updateOrCreate(
            ['meta_key' => $key],
            ['meta_value' => is_scalar($value) ? (string) $value : json_encode($value), 'status' => 'active', 'updated_by' => auth()->id()]
        );
    }

    /** A system item: it can be moved, but not edited, hidden or deleted. */
    public function isLocked(): bool
    {
        return $this->source === self::SOURCE_SYSTEM;
    }

    /** The module's dashboard (a fixed sidebar item). */
    public function isDashboard(): bool
    {
        return $this->isLocked() && $this->ref_parent === null;
    }

    /** The tab that stands for its sidebar item's own page (wherever it is in the tab order). */
    public function isSelfTab(): bool
    {
        return $this->isLocked() && $this->ref_parent !== null;
    }

    /** What the item shows: an own-page tab shows its sidebar item. */
    public function displayLabel(): string
    {
        return $this->isSelfTab() ? (string) $this->parent?->label : (string) $this->label;
    }

    public function displayIcon(): ?string
    {
        return $this->isSelfTab() ? $this->parent?->icon : $this->icon;
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    /**
     * Where the item leads: its URL, its route (a null param takes the
     * current page's value, so tabs of a record keep its uid), or the list
     * page of its table schema. "#" when the route doesn't exist (yet).
     */
    public function href(): string
    {
        // The own-page tab opens what its sidebar item opens.
        if ($this->isSelfTab()) {
            return $this->parent?->href() ?? '#';
        }

        if ($this->url) {
            return $this->url;
        }

        $params = is_array($this->params) ? $this->params : [];

        foreach ($params as $name => $value) {
            if ($value === null) {
                $params[$name] = request()?->route($name);
            }
        }

        if ($this->table_schema_uid) {
            $params['default_table_schema_uid'] = $this->table_schema_uid;
        }

        $route = $this->route ?: ($this->table_schema_uid ? 'ui.list' : null);

        if (! $route || ! Route::has($route)) {
            return '#';
        }

        try {
            return route($route, $params);
        } catch (\Throwable) {
            return '#'; // a required parameter isn't available on this page
        }
    }

    /** Whether the user may see it (no permission set: everyone who sees the module). */
    public function visibleTo($user): bool
    {
        return ! $this->permission || ($user && $user->can($this->permission));
    }
}
