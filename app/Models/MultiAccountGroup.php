<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MultiAccountGroup extends Model
{
    protected $fillable = [];

    public function members(): HasMany
    {
        return $this->hasMany(MultiAccountMember::class, 'group_id');
    }
}
