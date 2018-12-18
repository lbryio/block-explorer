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
        echo $this->element('claimbox', array('claim' => $claim));
        $idx++; 
        endforeach; ?>
    <?php else: ?>
        <div class="no-results">No results were found.</div>
    <?php endif; ?>
    <div class="clear"></div>
</div>

<?php echo $this->element('pagination') ?>
