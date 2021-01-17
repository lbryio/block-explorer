<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * @property integer $id
 * @property string $block_hash_id
 * @property int $input_count
 * @property int $output_count
 * @property float $fee
 * @property integer $transaction_time
 * @property integer $transaction_size
 * @property string $hash
 * @property int $version
 * @property int $lock_time
 * @property string $raw
 * @property string $created_at
 * @property string $modified_at
 * @property string $created_time
 * @property float $value
 * @property Block $block
 * @property Claim[] $claims
 * @property Input[] $inputs
 * @property Output[] $outputs
 * @property Support[] $supports
 * @property TransactionAddress[] $transactionAddresses
 */
class Transaction extends Model
{
    /**
     * The table associated with the model.
     * 
     * @var string
     */
    protected $table = 'transaction';

    /**
     * The "type" of the auto-incrementing ID.
     * 
     * @var string
     */
    protected $keyType = 'integer';

    /**
     * @var array
     */
    protected $fillable = ['block_hash_id', 'input_count', 'output_count', 'fee', 'transaction_time', 'transaction_size', 'hash', 'version', 'lock_time', 'raw', 'created_at', 'modified_at', 'created_time', 'value'];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function block()
    {
        return $this->belongsTo('App\Block', 'block_hash_id', 'hash');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function claims()
    {
        return $this->hasMany('App\Claim', 'transaction_hash_id', 'hash');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function inputs()
    {
        return $this->hasMany('App\Input');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function outputs()
    {
        return $this->hasMany('App\Output');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function supports()
    {
        return $this->hasMany('App\Support', 'transaction_hash_id', 'hash');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function transactionAddresses()
    {
        return $this->hasMany('App\TransactionAddress');
    }
}
