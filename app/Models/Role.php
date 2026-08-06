<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;


class Role extends Model
{
    use HasUuid, SoftDeletes;

    protected $fillable = [

        'name',

        'slug',

        'description',

        'status',

        'is_system',

    ];

    protected $casts = [

        'status' => 'boolean',

        'is_system' => 'boolean',

    ];


    /**
     * Users assigned to this role.
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
public function role(): BelongsTo
{
    return $this->belongsTo(Role::class);
}

   
}