<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MultiAccountMember extends Model
{
    protected $fillable = [
        'group_id',
        'no_cust',
        'va_display',
        'nama',
        'kelas',
        'jenjang',
        'last_academic_year',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(MultiAccountGroup::class, 'group_id');
    }
}
