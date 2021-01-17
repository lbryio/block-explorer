<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * @property integer $id
 * @property integer $transaction_id
 * @property string $transaction_hash
 * @property integer $input_address_id
 * @property boolean $is_coinbase
 * @property string $coinbase
 * @property string $prevout_hash
 * @property int $prevout_n
 * @property boolean $prevout_spend_updated
 * @property int $sequence
 * @property float $value
 * @property string $script_sig_asm
 * @property string $script_sig_hex
 * @property string $created
 * @property string $modified
 * @property int $vin
 * @property Transaction $transaction
 */
class Input extends Model
{
    /**
     * The table associated with the model.
     * 
     * @var string
     */
    protected $table = 'input';

    /**
     * The "type" of the auto-incrementing ID.
     * 
     * @var string
     */
    protected $keyType = 'integer';

    /**
     * @var array
     */
    protected $fillable = ['transaction_id', 'transaction_hash', 'input_address_id', 'is_coinbase', 'coinbase', 'prevout_hash', 'prevout_n', 'prevout_spend_updated', 'sequence', 'value', 'script_sig_asm', 'script_sig_hex', 'created', 'modified', 'vin'];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function transaction()
    {
        return $this->belongsTo('App\Transaction');
    }
}
