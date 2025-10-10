<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class WhatsAppMessagePackage extends Model
{
    protected $table = 'whatsapp_message_packages';

    protected $fillable = [
        'name',
        'message_count',
        'price',
        'currency',
        'description',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'price' => 'decimal:2',
            'message_count' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    #[Scope]
    protected function active(Builder $query)
    {
        return $query->where('is_active', true);
    }

    #[Scope]
    protected function ordered(Builder $query)
    {
        return $query->orderBy('sort_order')->orderBy('price');
    }
}
