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

<?php $this->assign('title', 'Search Results') ?>

<div class="claims-head">
    <h3>Search results</h3>
</div>

<div class="claims-grid">
    <?php if (isset($claims) && count($claims) > 0): ?>
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
                <img src="<?php echo htmlspecialchars($claim->ThumbnailUrl) ?>" alt="" />
            <?php else: ?>
                <div class="autothumb"><?php echo $autoThumbText ?></div>
            <?php endif; ?>
        </div>

        <div class="metadata">
            <div class="title" title="<?php echo $claim->ClaimType == 1 ? $claim->Name : ((strlen(trim($claim->Title)) > 0) ? $claim->Title : '') ?>"><?php echo $claim->ClaimType == 1 ? $claim->Name : ((strlen(trim($claim->Title)) > 0) ? $claim->Title : '<em>No Title</em>') ?></div>
            <div class="link" title="<?php echo $claim->getLbryLink() ?>"><a href="<?php echo $claim->getLbryLink() ?>" rel="nofollow"><?php echo $claim->getLbryLink() ?></a></div>

            <div class="desc"><?php echo strlen(trim($claim->Description)) > 0 ? $claim->Description : '<em>No description available</em>' ?></div>

            <div class="label half-width">Transaction</div>
            <div class="label half-width">Created</div>

            <div class="value half-width"><a href="/tx/<?php echo $claim->TransactionHash ?>#output-<?php echo $claim->Vout ?>" title="<?php echo $claim->TransactionHash ?>"><?php echo $claim->TransactionHash ?></a></div>
            

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

    <?php else: ?>
        <div class="no-results">No results were found.</div>
    <?php endif; ?>
    <div class="clear"></div>
</div>

<?php echo $this->element('pagination') ?>
