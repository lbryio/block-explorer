<?php $this->start('script'); ?>
<script type="text/javascript">
    var resizeCards = function() {
        var claimInfo = $('.claim-info');
        var claimMtdt = $('.claim-metadata');
        if (claimMtdt.outerHeight() < claimInfo.outerHeight()) {
            claimMtdt.outerHeight(claimInfo.outerHeight());
        } else if (claimInfo.outerHeight() < claimMtdt.outerHeight()) {
            claimInfo.outerHeight(claimMtdt.outerHeight());
        }
    };

    window.onload = function() {
        resizeCards();
    };

    $(document).ready(function() {
        resizeCards();

        $('.claim-grid-item img,.claim-info img').on('error', function() {
            var img = $(this);
            var parent = img.parent();
            var text = parent.attr('data-autothumb');
            img.remove();
            parent.append(
                $('<div></div>').attr({'class': 'autothumb' }).text(text)
            );
        });

        $(document).on('click', '.claim-grid-item', function() {
            var id = $(this).attr('data-id');
            location.href = '/claims/' + id;
        });
    });
</script>
<?php $this->end(); ?>
<?php echo $this->element('header') ?>

<?php if (isset($claim)):

$a = ['purple', 'orange', 'blue', 'teal', 'green', 'yellow'];
$autoThumbText = $claim->getAutoThumbText();
$cost = 'Free';
if (isset($claim->price) && $claim->price > 0) {
    $cost = $this->Amount->formatCurrency($claim->price) . ' LBC';
} else if (isset($claim->fee) && strtolower($claim->fee_currency) === 'lbc') {
    $cost = $this->Amount->formatCurrency($claim->fee) . ' LBC';
}

$desc = $claim->description;
if (strlen(trim($desc)) == 0) {
    $desc = '<em>No description available.</em>';
} else {
    $desc = preg_replace('#((https?|ftp|lbry)://([A-Za-z0-9\-\/]+|\S*?\.\S*?))([\s)\[\]{},;"\':<]|\.\s|$)#i','<a href="$1" target="_blank" rel="nofollow">$1</a>$4', $desc);
    $desc = preg_replace('/(([^<>()\[\]\\.,;:\s@"]+(\.[^<>()\[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/is', '<a href="mailto:$0" rel="nofollow">$0</a>', $desc);
}

?>

<?php $this->assign('title', 'Claim &bull; ' . $claim->name) ?>

<div class="claims-head">
    <h3><a href="/claims">LBRY Claims</a> &bull; <?php echo $claim->name ?></h3>
    <h4><?php echo $claim->claim_id ?></h4>
</div>

<div class="claims-body">
    <?php if ($claimIsBlocked): ?>

    <div class="blocked-claim-info">
        <div class="content">
            In response to a complaint we received under the US Digital Millennium Copyright Act, we have blocked access to this content from our applications. For more information, please refer to <a href="https://lbry.com/faq/dmca" target="_blank">DMCA takedown requests</a>.
        </div>
    </div>

    <?php else: ?>
    <div class="claim-info">
        <div data-autothumb="<?php echo $autoThumbText ?>" class="thumbnail <?php echo $a[mt_rand(0, count($a) - 1)] ?>">
            <?php if (!$claim->is_nsfw && strlen(trim($claim->thumbnail_url)) > 0): ?>
                <img src="<?php echo htmlspecialchars('https://thumbnails.odycdn.com/optimize/s:0:104/quality:85/plain/'.$claim->thumbnail_url) ?>" alt="" />
            <?php else: ?>
                <div class="autothumb"><?php echo $autoThumbText ?></div>
            <?php endif; ?>
        </div>

        <div class="content">
            <?php if ($claim->claim_type == 1): ?>
            <div class="label">Published By</div>
            <div class="value">
                <?php if (isset($claim->publisher)): ?>
                    <a href="lbry://<?php echo $claim->publisher ?>"><?php echo $claim->publisher ?></a>
                <?php else: ?>
                    <em>Anonymous</em>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <div class="label">Created On</div>
            <div class="value"><?php echo \DateTime::createFromFormat('U', $claim->transaction_time > 0 ? $claim->transaction_time : $claim->created_at->format('U'))->format('j M Y H:i:s') ?> UTC</div>

            <div class="label">Transaction ID</div>
            <div class="value"><a href="/tx/<?php echo $claim->transaction_hash_id ?>#output-<?php echo $claim->vout ?>"><?php echo $claim->transaction_hash_id ?></a></div>

            <?php if ($claim->claim_type == 1): ?>
            <div class="label half-width">Cost</div>
            <div class="label half-width">Safe for Work</div>

            <div class="value half-width"><?php echo $cost ?></div>
            <div class="value half-width"><?php echo $claim->is_nsfw ? 'No' : 'Yes' ?></div>

            <div class="clear"></div>
            <?php endif; ?>
        </div>
    </div>

    <div class="claim-metadata">
        <?php if ($claim->claim_type == 2): ?>
            <div class="title">Identity Claim</div>
            <div class="desc">This is an identity claim.</div>
        <?php else: ?>
            <div class="title"><?php echo $claim->title ?></div>
            <div class="desc"><?php echo str_replace("\n", '<br />', $desc) ?></div>

            <div class="details">
                <div class="label half-width">Author</div>
                <div class="label half-width">Content Type</div>


                <div class="value half-width"><?php echo strlen(trim($claim->author)) > 0 ? $claim->author : '<em>Unspecified</em>' ?></div>
                <div class="value half-width"><?php echo strlen(trim($claim->content_type)) > 0 ? $claim->content_type : '<em>Unspecified</em>' ?></div>

                <!--
                <div class="label half-width">License</div>
                -->
                <div class="label">Language</div>

                <!--
                <div class="value half-width"<?php if(strlen(trim($claim->license)) > 0): ?> title="<?php echo $claim->license ?>"<?php endif; ?>>
                    <?php if (strlen(trim($claim->license_url)) > 0): ?><a href="<?php echo $claim->LicenseUrl ?>" rel="nofollow" target="_blank"><?php endif; ?>
                    <?php echo strlen(trim($claim->License)) > 0 ? $claim->License : '<em>Unspecified</em>' ?>
                    <?php if (strlen(trim($claim->LicenseUrl))): ?></a><?php endif; ?>
                </div>
                -->
                <div class="value half-width"><?php echo strlen(trim($claim->language)) > 0 ? ($claim->language == 'en' ? 'English' : '') : '<em>Unspecified</em>' ?></div>
            </div>
        <?php endif; ?>
        <a href="<?php echo $claim->getLbryLink() ?>" class="open-lbry-link">Open in LBRY</a>
    </div>

    <div class="clear"></div>

    <?php endif; ?>

    <?php if (count($moreClaims) > 0): ?>

    <div class="more-claims">
        <h4><?php echo isset($claim->publisher) ? 'More from the publisher' : 'Published by this identity' ?></h4>

        <div class="claims-grid">
        <?php $idx = 1; $row = 1; $rowCount = ceil(count($moreClaims) / 3);

        foreach ($moreClaims as $claim):
            $last_row = ($row == $rowCount);
            if ($idx % 3 == 0) {
                $row++;
            }
            echo $this->element('claimbox', array('claim' => $claim, 'idx' => $idx, 'last_row' => $last_row));
            $idx++;
            endforeach; ?>
        <div class="clear"></div>
    </div>
    </div>

    <?php endif; ?>

</div>

<?php else: ?>

<?php $this->assign('title', 'Claims Explorer') ?>

<div class="claims-head">
    <h2>Claims Explorer</h2>
</div>

<div class="claims-grid">
    <?php

    $idx = 1;
    $row = 1;
    $rowCount = ceil(count($claims) / 3);
    $a = ['purple', 'orange', 'blue', 'teal', 'green', 'yellow'];
    foreach ($claims as $claim):
        $last_row = ($row == $rowCount);
        if ($idx % 3 == 0) {
            $row++;
        }
        echo $this->element('claimbox', array('claim' => $claim, 'idx' => $idx, 'last_row' => $last_row));
        $idx++;
        endforeach; ?>
    <div class="clear"></div>
</div>

<?php echo $this->element('pagination') ?>

<?php endif ?>
