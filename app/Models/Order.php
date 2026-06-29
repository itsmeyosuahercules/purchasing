<?php

namespace App\Models;

use App\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'order_number',
        'reference_rfq_no',
        'user_id',
        'supplier_id',
        'status',
        'approved_by',
        'approved_at',
        'valid_until',
        'delivery_date',
        'rejected_at',
        'rejection_reason',
        'notes',
        'whatsapp_link',
        'supplier_emailed_at',
        'supplier_whatsapp_sent_at',
        'supplier_whatsapp_error',
    ];

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'approved_at' => 'datetime',
            'valid_until' => 'date',
            'delivery_date' => 'date',
            'rejected_at' => 'datetime',
            'supplier_emailed_at' => 'datetime',
            'supplier_whatsapp_sent_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function isPending(): bool
    {
        return $this->status === OrderStatus::Pending;
    }

    public function total(): float
    {
        return (float) $this->items->sum(fn (OrderItem $item) => $item->quantity * $item->price);
    }

    public function poDate(): \Illuminate\Support\Carbon
    {
        return $this->approved_at ?? $this->created_at;
    }
}
