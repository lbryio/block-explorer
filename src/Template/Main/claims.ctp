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
$autoThumbText = '';
$link = $claim->Name;
if (isset($claim->Publisher->Name)) {
    $link = urlencode($claim->Publisher->Name) . '/' . $link;
}
$link = 'lbry://' . $link;
if ($claim->ClaimType == 1) { $autoThumbText = strtoupper(substr($claim->Name, 1, min( strlen($claim->Name), 10 ))); } else {
    $str = str_replace(' ', '', (strlen(trim($claim->Title)) > 0) ? $claim->Title : $claim->Name);
    $autoThumbText = strtoupper(mb_substr($str, 0, min( strlen($str), 10 )));
}

$cost = 'Free';
if (isset($claim->Price) && $claim->Price > 0) {
    $cost = $this->Amount->formatCurrency($claim->Price) . ' LBC';
} else if (isset($claim->Fee) && strtolower($claim->FeeCurrency) === 'lbc') {
    $cost = $this->Amount->formatCurrency($claim->Fee) . ' LBC';
}

$desc = $claim->Description;
if (strlen(trim($desc)) == 0) {
    $desc = '<em>No description available.</em>';
} else {
    $desc = preg_replace('#((https?|ftp|lbry)://([A-Za-z0-9\-\/]+|\S*?\.\S*?))([\s)\[\]{},;"\':<]|\.\s|$)#i','<a href="$1" target="_blank" rel="nofollow">$1</a>$4', $desc);
    $desc = preg_replace('/(([^<>()\[\]\\.,;:\s@"]+(\.[^<>()\[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/is', '<a href="mailto:$0" rel="nofollow">$0</a>', $desc);
}

?>

<?php $this->assign('title', 'Claim &bull; ' . $claim->Name) ?>

<div class="claims-head">
    <h3><a href="/claims">LBRY Claims</a> &bull; <?php echo $claim->Name ?></h3>
    <h4><?php echo $claim->ClaimId ?></h4>
</div>

<div class="claims-body">
    <div class="claim-info">
        <div data-autothumb="<?php echo $autoThumbText ?>" class="thumbnail <?php echo $a[mt_rand(0, count($a) - 1)] ?>">
            <?php if (!$claim->IsNSFW && strlen(trim($claim->ThumbnailUrl)) > 0): ?>
                <img src="<?php echo $claim->ThumbnailUrl ?>" alt="" />
            <?php else: ?>
                <div class="autothumb"><?php echo $autoThumbText ?></div>
            <?php endif; ?>
        </div>

        <div class="content">
            <?php if ($claim->ClaimType == 2): ?>
            <div class="label">Published by</div>
            <div class="value">
                <?php if (isset($claim->Publisher)): ?>
                    <a href="lbry://<?php echo $claim->Publisher->Name ?>"><?php echo $claim->Publisher->Name ?></a>
                <?php else: ?>
                    <em>Anonymous</em>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <div class="label">Created</div>
            <div class="value"><?php echo \DateTime::createFromFormat('U', $claim->TransactionTime > 0 ? $claim->TransactionTime : $claim->Created->format('U'))->format('j M Y H:i:s') ?> UTC</div>

            <div class="label">Transaction</div>
            <div class="value"><a href="/tx/<?php echo $claim->TransactionHash ?>#output-<?php echo $claim->Vout ?>"><?php echo $claim->TransactionHash ?></a></div>

            <?php if ($claim->ClaimType == 2): ?>
            <div class="label half-width">Cost</div>
            <div class="label half-width">Safe for work</div>

            <div class="value half-width"><?php echo $cost ?></div>
            <div class="value half-width"><?php echo $claim->IsNSFW ? 'No' : 'Yes' ?></div>

            <div class="clear"></div>
            <?php endif; ?>
        </div>
    </div>

    <div class="claim-metadata">
        <?php if ($claim->ClaimType == 1): ?>
            <div class="title">Identity Claim</div>
            <div class="desc">This is an identity claim.</div>
        <?php else: ?>
            <div class="title"><?php echo $claim->Title ?></div>
            <div class="desc"><?php echo str_replace("\n", '<br />', $desc) ?></div>

            <div class="details">
                <div class="label half-width">Author</div>
                <div class="label half-width">Content Type</div>


                <div class="value half-width"><?php echo strlen(trim($claim->Author)) > 0 ? $claim->Author : '<em>Unspecified</em>' ?></div>
                <div class="value half-width"><?php echo strlen(trim($claim->ContentType)) > 0 ? $claim->ContentType : '<em>Unspecified</em>' ?></div>

                <div class="label half-width">License</div>
                <div class="label half-width">Language</div>

                <div class="value half-width"<?php if(strlen(trim($claim->License)) > 0): ?> title="<?php echo $claim->License ?>"<?php endif; ?>>
                    <?php if (strlen(trim($claim->LicenseUrl)) > 0): ?><a href="<?php echo $claim->LicenseUrl ?>" rel="nofollow" target="_blank"><?php endif; ?>
                    <?php echo strlen(trim($claim->License)) > 0 ? $claim->License : '<em>Unspecified</em>' ?>
                    <?php if (strlen(trim($claim->LicenseUrl))): ?></a><?php endif; ?>
                </div>
                <div class="value half-width"><?php echo strlen(trim($claim->Language)) > 0 ? ($claim->Language == 'en' ? 'English' : '') : '<em>Unspecified</em>' ?></div>
            </div>
        <?php endif; ?>
        <a href="<?php echo $link ?>" class="open-lbry-link">Open in LBRY</a>
    </div>

    <div class="clear"></div>


    <?php if (count($moreClaims) > 0): ?>

    <div class="more-claims">
        <h4><?php echo isset($claim->Publisher) ? 'More from the publisher' : 'Published by this identity' ?></h4>

        <div class="claims-grid">
        <?php $idx = 1; $row = 1; $rowCount = ceil(count($moreClaims) / 3);

        foreach ($moreClaims as $claim):
            $last_row = ($row == $rowCount);
            if ($idx % 3 == 0) {
                $row++;
            }

            $autoThumbText = '';
            $link = $claim->Name;
            if (isset($claim->Publisher->Name)) {
                $link = urlencode($claim->Publisher->Name) . '/' . $link;
            }
            $link = 'lbry://' . $link;
            $cost = '';
            if (isset($claim->Price) && $claim->Price > 0) {
                $cost = $this->Amount->formatCurrency($claim->Price) . ' LBC';
            } else if (isset($claim->Fee) && strtolower($claim->FeeCurrency) === 'lbc') {
                $cost = $this->Amount->formatCurrency($claim->Fee) . ' LBC';
            }

            // content type
            $ctTag = null;
            if (substr($claim->ContentType, 0, 5) === 'audio') {
                $ctTag = 'audio';
            } else if (substr($claim->ContentType, 0, 5) === 'video') {
                $ctTag = 'video';
            } else if (substr($claim->ContentType, 0, 5) === 'image') {
                $ctTag = 'image';
            }

            if (!$ctTag && $claim->ClaimType == 1) {
                $ctTag = 'identity';
            }

            if ($claim->ClaimType == 1) { $autoThumbText = strtoupper(substr($claim->Name, 1, min( strlen($claim->Name), 10 ))); } else {
                $str = str_replace(' ', '', (strlen(trim($claim->Title)) > 0) ? $claim->Title : $claim->Name);
                $autoThumbText = strtoupper(mb_substr($str, 0, min( strlen($str), 10 )));
            }

        ?>
        <div data-id="<?php echo $claim->ClaimId ?>" class="claim-grid-item<?php if ($idx % 3 == 0): ?> last-item<?php endif; ?><?php if ($last_row): ?> last-row<?php endif; ?>">
            <?php if (strlen(trim($cost)) > 0): ?>
                <div class="price-tag"><?php echo $cost ?></div>
            <?php endif; ?>

            <div class="tags">
                <?php if ($ctTag): ?>
                <div class="content-type"><?php echo strtoupper($ctTag) ?></div>
                <?php endif; ?>
                <?php if ($claim->IsNSFW): ?>
                <div class="nsfw">NSFW</div>
                <?php endif; ?>
            </div>

            <div data-autothumb="<?php echo $autoThumbText ?>" class="thumbnail <?php echo $a[mt_rand(0, count($a) - 1)] ?>">
                <?php if (!$claim->IsNSFW && strlen(trim($claim->ThumbnailUrl)) > 0): ?>
                    <img src="<?php echo $claim->ThumbnailUrl ?>" alt="" />
                <?php else: ?>
                    <div class="autothumb"><?php echo $autoThumbText ?></div>
                <?php endif; ?>
            </div>

            <div class="metadata">
                <div class="title" title="<?php echo $claim->ClaimType == 1 ? $claim->Name : ((strlen(trim($claim->Title)) > 0) ? $claim->Title : '') ?>"><?php echo $claim->ClaimType == 1 ? $claim->Name : ((strlen(trim($claim->Title)) > 0) ? $claim->Title : '<em>No Title</em>') ?></div>
                <div class="link" title="<?php echo $link ?>"><a href="<?php echo $link ?>" rel="nofollow"><?php echo $link ?></a></div>

                <div class="desc"><?php echo strlen(trim($claim->Description)) > 0 ? $claim->Description : '<em>No description available</em>' ?></div>

                <div class="label half-width">Transaction</div>
                <div class="label half-width">Created</div>

                <div class="value half-width"><a href="/tx/<?php echo $claim->TransactionHash ?>#output-<?php echo $claim->Vout ?>" title="<?php echo $claim->TransactionHash ?>"><?php echo $claim->TransactionHash ?></a></div>
                <div class="value half-width" title="<?php echo $claim->Created->format('j M Y H:i:s') ?> UTC">
                    <?php echo \Carbon\Carbon::createFromTimestamp($claim->Created->format('U'))->diffForHumans(); ?>
                </div>

                <div class="clear spacer"></div>

                <?php if ($claim->ClaimType == 2): ?>
                <div class="label half-width">Content Type</div>
                <div class="label half-width">Language</div>

                <div class="value half-width" title="<?php echo $claim->ContentType ?>"><?php echo $claim->ContentType ?></div>
                <div class="value half-width" title="<?php echo $claim->Language == 'en' ? 'English' : $claim->Language ?>"><?php echo $claim->Language == 'en' ? 'English' : $claim->Language ?></div>

                <div class="clear spacer"></div>

                <div class="label half-width">Author</div>
                <div class="label half-width">License</div>

                <div class="value half-width" title="<?php echo strlen(trim($claim->Author)) > 0 ? $claim->Author : '<em>Unspecified</em>' ?>"><?php echo strlen(trim($claim->Author)) > 0 ? $claim->Author : '<em>Unspecified</em>' ?></div>
                <div class="value half-width" title="<?php echo strlen(trim($claim->License)) > 0 ? $claim->License : '' ?>">
                    <?php if (strlen(trim($claim->LicenseUrl)) > 0): ?><a href="<?php echo $claim->LicenseUrl ?>" rel="nofollow" target="_blank"><?php endif; ?>
                    <?php echo strlen(trim($claim->License)) > 0 ? $claim->License : '<em>Unspecified</em>' ?>
                    <?php if (strlen(trim($claim->LicenseUrl))): ?></a><?php endif; ?>
                </div>
                <?php endif; ?>

            </div>
        </div>
        <?php $idx++; endforeach; ?>

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
        $autoThumbText = '';
        $link = $claim->Name;
        if (isset($claim->Publisher->Name)) {
            $link = urlencode($claim->Publisher->Name) . '/' . $link;
        }
        $link = 'lbry://' . $link;

        $cost = '';
        if (isset($claim->Price) && $claim->Price > 0) {
            $cost = $this->Amount->formatCurrency($claim->Price) . ' LBC';
        } else if (isset($claim->Fee) && strtolower($claim->FeeCurrency) === 'lbc') {
            $cost = $this->Amount->formatCurrency($claim->Fee) . ' LBC';
        }

        // content type
        $ctTag = null;
        if (substr($claim->ContentType, 0, 5) === 'audio') {
            $ctTag = 'audio';
        } else if (substr($claim->ContentType, 0, 5) === 'video') {
            $ctTag = 'video';
        } else if (substr($claim->ContentType, 0, 5) === 'image') {
            $ctTag = 'image';
        }

        if (!$ctTag && $claim->ClaimType == 1) {
            $ctTag = 'identity';
        }

        if ($claim->ClaimType == 1) { $autoThumbText = strtoupper(substr($claim->Name, 1, min( strlen($claim->Name), 10 ))); } else {
            $str = str_replace(' ', '', (strlen(trim($claim->Title)) > 0) ? $claim->Title : $claim->Name);
            $autoThumbText = strtoupper(mb_substr($str, 0, min( strlen($str), 10 )));
        }

    ?>
    <div data-id="<?php echo $claim->ClaimId ?>" class="claim-grid-item<?php if ($idx % 3 == 0): ?> last-item<?php endif; ?><?php if ($last_row): ?> last-row<?php endif; ?>">
        <?php if (strlen(trim($cost)) > 0): ?>
        <div class="price-tag"><?php echo $cost ?></div>
        <?php endif; ?>

        <div class="tags">
            <?php if ($ctTag): ?>
            <div class="content-type"><?php echo strtoupper($ctTag) ?></div>
            <?php endif; ?>
            <?php if ($claim->IsNSFW): ?>
            <div class="nsfw">NSFW</div>
            <?php endif; ?>
        </div>

        <div data-autothumb="<?php echo $autoThumbText ?>" class="thumbnail <?php echo $a[mt_rand(0, count($a) - 1)] ?>">
            <?php if (!$claim->IsNSFW && strlen(trim($claim->ThumbnailUrl)) > 0): ?>
                <img src="<?php echo $claim->ThumbnailUrl ?>" alt="" />
            <?php else: ?>
                <div class="autothumb"><?php echo $autoThumbText ?></div>
            <?php endif; ?>
        </div>

        <div class="metadata">
            <div class="title" title="<?php echo $claim->ClaimType == 1 ? $claim->Name : ((strlen(trim($claim->Title)) > 0) ? $claim->Title : '') ?>"><?php echo $claim->ClaimType == 1 ? $claim->Name : ((strlen(trim($claim->Title)) > 0) ? $claim->Title : '<em>No Title</em>') ?></div>
            <div class="link" title="<?php echo $link ?>"><a href="<?php echo $link ?>" rel="nofollow"><?php echo $link ?></a></div>

            <div class="desc"><?php echo strlen(trim($claim->Description)) > 0 ? $claim->Description : '<em>No description available</em>' ?></div>

            <div class="label half-width">Transaction</div>
            <div class="label half-width">Created</div>

            <div class="value half-width"><a href="/tx/<?php echo $claim->TransactionHash ?>#output-<?php echo $claim->Vout ?>" title="<?php echo $claim->TransactionHash ?>"><?php echo $claim->TransactionHash ?></a></div>
            <div class="value half-width" title="<?php echo $claim->Created->format('j M Y H:i:s') ?> UTC">
                <?php echo \Carbon\Carbon::createFromTimestamp($claim->TransactionTime > 0 ? $claim->TransactionTime : $claim->Created->format('U'))->diffForHumans(); ?>
            </div>

            <div class="clear spacer"></div>

            <?php if ($claim->ClaimType == 2): ?>
            <div class="label half-width">Content Type</div>
            <div class="label half-width">Language</div>

            <div class="value half-width" title="<?php echo $claim->ContentType ?>"><?php echo $claim->ContentType ?></div>
            <div class="value half-width" title="<?php echo $claim->Language == 'en' ? 'English' : $claim->Language ?>"><?php echo $claim->Language == 'en' ? 'English' : $claim->Language ?></div>

            <div class="clear spacer"></div>

            <div class="label half-width">Author</div>
            <div class="label half-width">License</div>

            <div class="value half-width" title="<?php echo strlen(trim($claim->Author)) > 0 ? $claim->Author : '<em>Unspecified</em>' ?>"><?php echo strlen(trim($claim->Author)) > 0 ? $claim->Author : '<em>Unspecified</em>' ?></div>
            <div class="value half-width" title="<?php echo strlen(trim($claim->License)) > 0 ? $claim->License : '' ?>">
                <?php if (strlen(trim($claim->LicenseUrl)) > 0): ?><a href="<?php echo $claim->LicenseUrl ?>" rel="nofollow" target="_blank"><?php endif; ?>
                <?php echo strlen(trim($claim->License)) > 0 ? $claim->License : '<em>Unspecified</em>' ?>
                <?php if (strlen(trim($claim->LicenseUrl))): ?></a><?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php $idx++; endforeach; ?>

    <div class="clear"></div>
</div>

<?php echo $this->element('pagination') ?>

<?php endif ?>