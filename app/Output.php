<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * @property integer $id
 * @property integer $transaction_id
 * @property string $transaction_hash
 * @property float $value
 * @property int $vout
 * @property string $type
 * @property string $script_pub_key_asm
 * @property string $script_pub_key_hex
 * @property int $required_signatures
 * @property string $address_list
 * @property boolean $is_spent
 * @property integer $spent_by_input_id
 * @property string $created_at
 * @property string $modified_at
 * @property string $claim_id
 * @property Transaction $transaction
 * @property AbnormalClaim[] $abnormalClaims
 */
class Output extends Model
{
    /**
     * The table associated with the model.
     * 
     * @var string
     */
    protected $table = 'output';

    /**
     * The "type" of the auto-incrementing ID.
     * 
     * @var string
     */
    protected $keyType = 'integer';

    /**
     * @var array
     */
    protected $fillable = ['transaction_id', 'transaction_hash', 'value', 'vout', 'type', 'script_pub_key_asm', 'script_pub_key_hex', 'required_signatures', 'address_list', 'is_spent', 'spent_by_input_id', 'created_at', 'modified_at', 'claim_id'];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function transaction()
    {
        return $this->belongsTo('App\Transaction');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function abnormalClaims()
    {
        return $this->hasMany('App\AbnormalClaim');
    }
}
