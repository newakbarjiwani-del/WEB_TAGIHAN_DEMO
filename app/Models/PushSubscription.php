<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PushSubscription extends Model
{
    /** Subscription disimpan di DB tagihan (sama dengan mst_qris). */
    protected $connection = 'tagihan';

    protected $fillable = [
        'endpoint_hash',
        'endpoint',
        'public_key',
        'auth_token',
        'content_encoding',
        'nocust',
        'vano',
        'user_agent',
        'last_used_at',
    ];

    protected $casts = [
        'last_used_at' => 'datetime',
    ];
}
