<?php

namespace App\Modules\Notifications\Models;

use App\Modules\Users\Models\User;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppNotification extends Model
{
    protected $fillable = ['user_id', 'type', 'data', 'read_at'];

    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
        ];
    }

    /**
     * Store data as JSON string; expose as JSON string for GraphQL (String! field).
     * Use getDataArray() for internal array access.
     */
    protected function data(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => is_string($value) ? $value : json_encode($value),
            set: fn ($value) => is_array($value) ? json_encode($value) : $value,
        );
    }

    public function getDataArray(): array
    {
        $raw = $this->getRawOriginal('data');
        return is_string($raw) ? json_decode($raw, true) ?? [] : (array) $raw;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isRead(): bool
    {
        return $this->read_at !== null;
    }
}
