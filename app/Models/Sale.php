<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Sale extends Model
{
    protected $fillable = ['product_id', 'customer_id', 'sold_by', 'quantity', 'total_price', 'submission_id', 'product_name', 'customer_name', 'staff_name', 'unit_price', 'currency', 'details_captured_at_sale', 'payment_method', 'amount_tendered', 'change_given', 'request_fingerprint'];

    protected function casts(): array
    {
        return ['quantity' => 'integer', 'total_price' => 'decimal:2', 'unit_price' => 'decimal:2', 'details_captured_at_sale' => 'boolean', 'amount_tendered' => 'decimal:2', 'change_given' => 'decimal:2'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sold_by')->withTrashed();
    }
}
