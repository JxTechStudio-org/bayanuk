<?php

namespace App\Models;

use App\Enums\DeletionReason;
use Illuminate\Database\Eloquent\Model;

class DeletionLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'document_id',
        'reason',
        'file_deleted',
        'deleted_at',
    ];

    protected $casts = [
        'reason' => DeletionReason::class,
        'file_deleted' => 'boolean',
        'deleted_at' => 'datetime',
    ];
}
