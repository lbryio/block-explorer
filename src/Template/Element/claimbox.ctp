<?php
$autoThumbText = $claim->getAutoThumbText();
$cost = '';
if (isset($claim->price) && $claim->price > 0) {
    $cost = $this->Amount->formatCurrency($claim->price) . ' LBC';
} else if (isset($claim->fee) && strtolower($claim->fee_currency) === 'lbc') {
    $cost = $this->Amount->formatCurrency($claim->fee) . ' LBC';
}
$a = ['purple', 'orange', 'blue', 'teal', 'green', 'yellow'];
// content type
$ctTag = $claim->getContentTag();
?>
<div data-id="<?php echo $claim->claim_id ?>" class="claim-grid-item<?php if ($idx % 3 == 0): ?> last-item<?php endif; ?><?php if ($last_row): ?> last-row<?php endif; ?>">
    <?php if (strlen(trim($cost)) > 0): ?>
    <div class="price-tag"><?php echo $cost ?></div>
    <?php endif; ?>

    <div class="tags">
        <?php if ($claim->bid_state == 'Controlling'): ?>
        <div class="bid-state">Controlling</div>
        <?php endif; ?>
        <?php if ($ctTag): ?>
        <div class="content-type"><?php echo strtoupper($ctTag) ?></div>
        <?php endif; ?>
        <?php if ($claim->is_nsfw): ?>
        <div class="nsfw">NSFW</div>
        <?php endif; ?>
    </div>

    <div data-autothumb="<?php echo $autoThumbText ?>" class="thumbnail <?php echo $a[mt_rand(0, count($a) - 1)] ?>">
        <?php if (!$claim->is_nsfw && strlen(trim($claim->thumbnail_url)) > 0): ?>
            <img src="<?php echo htmlspecialchars('https://thumbnails.odycdn.com/optimize/s:0:104/quality:85/plain/'.$claim->thumbnail_url) ?>" alt="" />
        <?php else: ?>
            <div class="autothumb"><?php echo $autoThumbText ?></div>
        <?php endif; ?>
    </div>

    <?php if ($claim->isBlocked): ?>

    <div class="blocked-info">
        In response to a complaint we received under the US Digital Millennium Copyright Act, we have blocked access to this content from our applications. For more information, please refer to <a href="https://lbry.com/faq/dmca" target="_blank">DMCA takedown requests</a>
    </div>

    <?php else: ?>

    <div class="metadata">
        <div class="title" title="<?php echo $claim->claim_type == 1 ? $claim->name : ((strlen(trim($claim->title)) > 0) ? $claim->title : '') ?>"><?php echo $claim->claim_type == 1 ? $claim->name : ((strlen(trim($claim->title)) > 0) ? $claim->title : '<em>No Title</em>') ?></div>
        <div class="link" title="<?php echo $claim->getLbryLink() ?>"><a href="<?php echo $claim->getLbryLink() ?>" rel="nofollow"><?php echo $claim->getLbryLink() ?></a></div>

        <div class="desc"><?php echo strlen(trim($claim->description)) > 0 ? $claim->description : '<em>No description available</em>' ?></div>

        <div class="label half-width">Transaction</div>
        <div class="label half-width">Created</div>

        <div class="value half-width"><a href="/tx/<?php echo $claim->transaction_hash_id ?>#output-<?php echo $claim->vout ?>" title="<?php echo $claim->transaction_hash_id ?>"><?php echo $claim->transaction_hash_id ?></a></div>
        <div class="value half-width" title="<?php echo $claim->created_at->format('j M Y H:i:s') ?> UTC">
                <?php echo \Carbon\Carbon::createFromTimestamp($claim->created_at->format('U'))->diffForHumans(); ?>
            </div>
        <div class="clear spacer"></div>

        <?php if ($claim->claim_type == 1): ?>
        <div class="label half-width">Content Type</div>
        <div class="label half-width">Language</div>

        <div class="value half-width" title="<?php echo $claim->content_type ?>"><?php echo $claim->content_type ?></div>
        <div class="value half-width" title="<?php echo $claim->language == 'en' ? 'English' : $claim->language ?>"><?php echo $claim->language == 'en' ? 'English' : $claim->language ?></div>

        <div class="clear spacer"></div>

        <!--<div class="label half-width">Author</div>
        <div class="label half-width">License</div>-->


        <!--<div class="value half-width" title="<?php echo strlen(trim($claim->author)) > 0 ? $claim->author : '<em>Unspecified</em>' ?>"><?php echo strlen(trim($claim->author)) > 0 ? $claim->author : '<em>Unspecified</em>' ?></div>

        <div class="value half-width" title="<?php echo strlen(trim($claim->license)) > 0 ? $claim->license : '' ?>">
            <?php if (strlen(trim($claim->LicenseUrl)) > 0): ?><a href="<?php echo $claim->LicenseUrl ?>" rel="nofollow" target="_blank"><?php endif; ?>
            <?php echo strlen(trim($claim->License)) > 0 ? $claim->License : '<em>Unspecified</em>' ?>
            <?php if (strlen(trim($claim->LicenseUrl))): ?></a><?php endif; ?>
        </div>
        -->
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>
