<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * @property integer $transaction_id
 * @property integer $address_id
 * @property float $debit_amount
 * @property float $credit_amount
 * @property Transaction $transaction
 * @property Address $address
 */
class TransactionAddress extends Model
{
    /**
     * The table associated with the model.
     * 
     * @var string
     */
    protected $table = 'transaction_address';

    /**
     * @var array
     */
    protected $fillable = ['debit_amount', 'credit_amount'];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function transaction()
    {
        return $this->belongsTo('App\Transaction');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function address()
    {
        return $this->belongsTo('App\Address');
    }
}
