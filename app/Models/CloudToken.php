<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CloudToken extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'api_token',
        'organization_name',
    ];

    protected function casts(): array
    {
        return [
            'api_token' => 'encrypted',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
