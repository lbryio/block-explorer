<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * @property integer $id
 * @property string $tag
 * @property string $created_at
 * @property string $modified_at
 * @property ClaimTag[] $claimTags
 */
class Tag extends Model
{
    /**
     * The table associated with the model.
     * 
     * @var string
     */
    protected $table = 'tag';

    /**
     * The "type" of the auto-incrementing ID.
     * 
     * @var string
     */
    protected $keyType = 'integer';

    /**
     * @var array
     */
    protected $fillable = ['tag', 'created_at', 'modified_at'];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function claimTags()
    {
        return $this->hasMany('App\ClaimTag');
    }
}
