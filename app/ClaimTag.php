<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * @property integer $id
 * @property integer $tag_id
 * @property string $claim_id
 * @property string $created_at
 * @property string $modified_at
 * @property Claim $claim
 * @property Tag $tag
 */
class ClaimTag extends Model
{
    /**
     * The table associated with the model.
     * 
     * @var string
     */
    protected $table = 'claim_tag';

    /**
     * The "type" of the auto-incrementing ID.
     * 
     * @var string
     */
    protected $keyType = 'integer';

    /**
     * @var array
     */
    protected $fillable = ['tag_id', 'claim_id', 'created_at', 'modified_at'];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function claim()
    {
        return $this->belongsTo('App\Claim', null, 'claim_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function tag()
    {
        return $this->belongsTo('App\Tag');
    }
}
