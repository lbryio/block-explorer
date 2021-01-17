<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * @property integer $id
 * @property string $bits
 * @property string $chainwork
 * @property int $confirmations
 * @property float $difficulty
 * @property string $hash
 * @property integer $height
 * @property string $merkle_root
 * @property string $name_claim_root
 * @property integer $nonce
 * @property string $previous_block_hash
 * @property string $next_block_hash
 * @property integer $block_size
 * @property integer $block_time
 * @property integer $version
 * @property string $version_hex
 * @property string $transaction_hashes
 * @property boolean $transactions_processed
 * @property string $created_at
 * @property string $modified_at
 * @property Transaction[] $transactions
 */
class Block extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'block';

    /**
     * The "type" of the auto-incrementing ID.
     *
     * @var string
     */
    protected $keyType = 'integer';

    /**
     * @var array
     */
    protected $fillable = ['bits', 'chainwork', 'confirmations', 'difficulty', 'hash', 'height', 'merkle_root', 'name_claim_root', 'nonce', 'previous_block_hash', 'next_block_hash', 'block_size', 'block_time', 'version', 'version_hex', 'transaction_hashes', 'transactions_processed', 'created_at', 'modified_at'];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function transactions()
    {
        return $this->hasMany('App\Transaction', 'block_hash_id', 'hash');
    }

}
