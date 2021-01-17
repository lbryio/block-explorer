<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * @property integer $id
 * @property string $address
 * @property string $first_seen
 * @property string $created_at
 * @property string $modified_at
 * @property float $balance
 * @property TransactionAddress[] $transactionAddresses
 */
class Address extends Model
{
    /**
     * The table associated with the model.
     * 
     * @var string
     */
    protected $table = 'address';

    /**
     * The "type" of the auto-incrementing ID.
     * 
     * @var string
     */
    protected $keyType = 'integer';

    /**
     * @var array
     */
    protected $fillable = ['address', 'first_seen', 'created_at', 'modified_at', 'balance'];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function transactionAddresses()
    {
        return $this->hasMany('App\TransactionAddress');
    }
}
