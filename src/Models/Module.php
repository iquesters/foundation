<?php

namespace Iquesters\Foundation\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Permission\Models\Role;

class Module extends Model
{
    use HasFactory;

    protected $table = 'modules';

    protected $fillable = [
        'uid',
        'name',
        'description',
        'status',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'uid' => 'string',
    ];

    public function metas(): HasMany
    {
        return $this->hasMany(ModuleMeta::class, 'ref_parent');
    }

    public function getMeta(string $key)
    {
        $meta = $this->metas()->where('meta_key', $key)->first();
        return $meta ? $meta->meta_value : null;
    }

    public function setMeta(string $key, $value)
    {
        return $this->metas()->updateOrCreate(
            ['meta_key' => $key],
            ['meta_value' => $value]
        );
    }

    /**
     * Get roles assigned to this module
     */
    public function getAssignedRoles()
    {
        $roleIds = json_decode($this->getMeta('assigned_roles') ?? '[]', true);
        return Role::whereIn('id', $roleIds)->get();
    }

    /**
     * Get assigned role IDs
     */
    public function getAssignedRoleIds(): array
    {
        return json_decode($this->getMeta('assigned_roles') ?? '[]', true);
    }

    /**
     * Assign roles to this module
     */
    public function assignRoles(array $roleIds): void
    {
        $this->setMeta('assigned_roles', json_encode($roleIds));
    }

    /**
     * Check if module is assigned to a specific role
     */
    public function isAssignedToRole($role): bool
    {
        $assignedRoleIds = $this->getAssignedRoleIds();
        
        if (is_numeric($role)) {
            return in_array($role, $assignedRoleIds);
        }
        
        if ($role instanceof Role) {
            return in_array($role->id, $assignedRoleIds);
        }
        
        return false;
    }

    /**
     * Check if module is accessible by user
     */
    public function isAccessibleByUser($user): bool
    {
        if (!$user) return false;
        
        $assignedRoleIds = $this->getAssignedRoleIds();
        $userRoleIds = $user->roles->pluck('id')->toArray();
        
        return count(array_intersect($assignedRoleIds, $userRoleIds)) > 0;
    }

    /**
     * Get modules accessible by a user
     */
    public static function getForUser($user)
    {
        if (!$user) {
            return collect();
        }

        $userRoleIds = $user->roles->pluck('id')->toArray();
        
        return static::where('status', 'active')->get()->filter(function ($module) use ($userRoleIds) {
            $assignedRoleIds = $module->getAssignedRoleIds();
            return count(array_intersect($assignedRoleIds, $userRoleIds)) > 0;
        });
    }

    /**
     * Scope for active modules
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}