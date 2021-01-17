<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * @property integer $id
 * @property string $list_claim_id
 * @property string $claim_id
 * @property string $created_at
 * @property string $modified_at
 * @property Claim $claim
 */
class ClaimInList extends Model
{
    /**
     * The table associated with the model.
     * 
     * @var string
     */
    protected $table = 'claim_in_list';

    /**
     * The "type" of the auto-incrementing ID.
     * 
     * @var string
     */
    protected $keyType = 'integer';

    /**
     * @var array
     */
    protected $fillable = ['list_claim_id', 'claim_id', 'created_at', 'modified_at'];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function claim()
    {
        return $this->belongsTo('App\Claim', 'list_claim_id', 'claim_id');
    }
}
