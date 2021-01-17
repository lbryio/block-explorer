<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * @property integer $id
 * @property string $transaction_hash_id
 * @property string $supported_claim_id
 * @property float $support_amount
 * @property string $bid_state
 * @property int $vout
 * @property string $created_at
 * @property string $modified_at
 * @property Transaction $transaction
 */
class Support extends Model
{
    /**
     * The table associated with the model.
     * 
     * @var string
     */
    protected $table = 'support';

    /**
     * The "type" of the auto-incrementing ID.
     * 
     * @var string
     */
    protected $keyType = 'integer';

    /**
     * @var array
     */
    protected $fillable = ['transaction_hash_id', 'supported_claim_id', 'support_amount', 'bid_state', 'vout', 'created_at', 'modified_at'];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function transaction()
    {
        return $this->belongsTo('App\Transaction', 'transaction_hash_id', 'hash');
    }
}
