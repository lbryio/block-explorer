<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * @property integer $id
 * @property string $transaction_hash_id
 * @property int $vout
 * @property string $name
 * @property string $claim_id
 * @property boolean $claim_type
 * @property string $publisher_id
 * @property string $publisher_sig
 * @property string $certificate
 * @property string $sd_hash
 * @property integer $transaction_time
 * @property string $version
 * @property string $value_as_hex
 * @property string $value_as_json
 * @property int $valid_at_height
 * @property int $height
 * @property integer $effective_amount
 * @property string $author
 * @property string $description
 * @property string $content_type
 * @property boolean $is_nsfw
 * @property string $language
 * @property string $thumbnail_url
 * @property string $title
 * @property float $fee
 * @property string $fee_currency
 * @property string $fee_address
 * @property boolean $is_filtered
 * @property string $bid_state
 * @property string $created_at
 * @property string $modified_at
 * @property string $claim_address
 * @property boolean $is_cert_valid
 * @property boolean $is_cert_processed
 * @property string $license
 * @property string $license_url
 * @property string $preview
 * @property string $type
 * @property integer $release_time
 * @property string $source_hash
 * @property string $source_name
 * @property integer $source_size
 * @property string $source_media_type
 * @property string $source_url
 * @property integer $frame_width
 * @property integer $frame_height
 * @property integer $duration
 * @property integer $audio_duration
 * @property string $os
 * @property string $email
 * @property string $website_url
 * @property boolean $has_claim_list
 * @property string $claim_reference
 * @property integer $list_type
 * @property mixed $claim_id_list
 * @property string $country
 * @property string $state
 * @property string $city
 * @property string $code
 * @property integer $latitude
 * @property integer $longitude
 * @property Transaction $transaction
 * @property ClaimInList[] $claimInLists
 * @property ClaimTag[] $claimTags
 */
class Claim extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'claim';

    /**
     * The "type" of the auto-incrementing ID.
     *
     * @var string
     */
    protected $keyType = 'integer';

    /**
     * @var array
     */
    protected $fillable = ['transaction_hash_id', 'vout', 'name', 'claim_id', 'claim_type', 'publisher_id', 'publisher_sig', 'certificate', 'sd_hash', 'transaction_time', 'version', 'value_as_hex', 'value_as_json', 'valid_at_height', 'height', 'effective_amount', 'author', 'description', 'content_type', 'is_nsfw', 'language', 'thumbnail_url', 'title', 'fee', 'fee_currency', 'fee_address', 'is_filtered', 'bid_state', 'created_at', 'modified_at', 'claim_address', 'is_cert_valid', 'is_cert_processed', 'license', 'license_url', 'preview', 'type', 'release_time', 'source_hash', 'source_name', 'source_size', 'source_media_type', 'source_url', 'frame_width', 'frame_height', 'duration', 'audio_duration', 'os', 'email', 'website_url', 'has_claim_list', 'claim_reference', 'list_type', 'claim_id_list', 'country', 'state', 'city', 'code', 'latitude', 'longitude'];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function transaction()
    {
        return $this->belongsTo('App\Transaction', 'transaction_hash_id', 'hash');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function claimInLists()
    {
        return $this->hasMany('App\ClaimInList', 'list_claim_id', 'claim_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function claimTags()
    {
        return $this->hasMany('App\ClaimTag', null, 'claim_id');
    }

    /**
     * Get claim content tag from content type
     * @return string
     */
    public function getContentTag() {
        $contentTag = null;
        if ($this->type == "channel") {
            return 'channel';
        } elseif ($this->type == "claimreference") {
            return 'support';
        } elseif ($this->type == "claimlist") {
            return 'list';
        } else {
            if (substr($this->content_type, 0, 5) === 'audio') {
                return 'audio';
            } else if (substr($this->content_type, 0, 5) === 'video') {
                return 'video';
            } else if (substr($this->content_type, 0, 5) === 'image') {
                return 'image';
            } else if ($this->content_type === 'application/pdf') {
                return 'pdf';
            } else {
                return 'document';
            }
        }
    }
}
