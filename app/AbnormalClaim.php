<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * @property integer $id
 * @property integer $output_id
 * @property string $name
 * @property string $claim_id
 * @property boolean $is_update
 * @property string $block_hash
 * @property string $transaction_hash
 * @property int $vout
 * @property string $value_as_hex
 * @property string $value_as_json
 * @property string $created_at
 * @property string $modified_at
 * @property Output $output
 */
class AbnormalClaim extends Model
{
    /**
     * The table associated with the model.
     * 
     * @var string
     */
    protected $table = 'abnormal_claim';

    /**
     * The "type" of the auto-incrementing ID.
     * 
     * @var string
     */
    protected $keyType = 'integer';

    /**
     * @var array
     */
    protected $fillable = ['output_id', 'name', 'claim_id', 'is_update', 'block_hash', 'transaction_hash', 'vout', 'value_as_hex', 'value_as_json', 'created_at', 'modified_at'];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function output()
    {
        return $this->belongsTo('App\Output');
    }
}
