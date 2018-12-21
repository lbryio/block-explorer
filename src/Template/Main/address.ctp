<?php $this->assign('title', 'Address ' . $address->address) ?>

<?php $this->start('script') ?>
<script type="text/javascript">
    var buildTagRequest = function() {
        return {
            tag: $.trim($('input[name="tag_value"]').val()),
            url: $.trim($('input[name="tag_url"]').val()),
            vamount: parseFloat($('input[name="tag_verify_amount"]').val())
        };
    };

    $(document).ready(function() {
        $('.tag-link').on('click', function(evt) {
            evt.preventDefault();
            var container = $('.tag-address-container');
            if (!container.is(':visible')) {
                container.slideDown(200);
            }
        });

        $('.btn-tag').on('click', function(evt) {
            evt.preventDefault();
            var btn = $(this);
            var req = buildTagRequest();

            var err = $('.tag-address-container .error-message');
            err.css({ color: '#ff0000' }).text('');
            if (req.tag.length === 0 || req.tag.length > 30) {
                return err.text('Oops! Please specify a valid tag. It should be no more than 30 characters long.');
            }

            if (req.url.length > 200) {
                return err.text('Oops! The link should be no more than 200 characters long.');
            }

            if (isNaN(req.vamount)) {
                return err.text('Oops! Invalid verification amount. Please refresh the page and try again.');
            }

            var btnClose = $('.btn-close');
            $.ajax({
                url: '/api/v1/address/<?php echo $address->address ?>/tag',
                type: 'post',
                dataType: 'json',
                data: req,
                beforeSend: function() {
                    btn.prop('disabled', true);
                    btnClose.prop('disabled', true);
                    btn.text('Loading...');
                },
                success: function(response) {
                    if (response.success) {
                        err.css({ color: '#00aa00'}).html('Your request for the tag, <strong>' + response.tag + '</strong> was successfully submitted. The tag will become active upon automatic transaction verification.');
                    }
                },
                error: function(xhr) {
                    var error = 'An error occurred with the request. If this problem persists, please send an email to hello@aureolin.co.';
                    try {
                        var json = JSON.parse(xhr.responseText);
                        if (json.error) {
                            error = json.message ? json.message : error;
                        }
                    } catch (e) {
                        // return default error
                    }
                    err.css({ color: '#ff0000' }).text(error);
                },
                complete: function() {
                    btn.text('Tag address');
                    btn.prop('disabled', false);
                    btnClose.prop('disabled', false);
                }
            });
        });

        $('.btn-close').on('click', function() {
            $('input[name="tag_value"]').val('');
            $('input[name="tag_url"]').val('');
            $('.tag-address-container').slideUp(200);
        });
    });
</script>
<?php $this->end() ?>

<?php echo $this->element('header') ?>

<div class="address-head">
    <h3>LBRY Address</h3>
    <h4><?php echo $address->address ?></h4>
    <?php if (isset($address->Tag) && strlen(trim($address->Tag)) > 0): ?>
        <?php if (strlen(trim($address->TagUrl)) > 0): ?><a href="<?php echo $address->TagUrl ?>" target="_blank" rel="nofollow"><?php echo $address->Tag ?></a><?php else: echo $address->Tag; endif; ?>
    <?php endif; ?>
</div>

<div class="address-subhead">
    <div class="address-qr">
        <img src="/qr/lbry%3A<?php echo $address->address ?>" alt="lbry:<?php echo $address->address ?>" />
    </div>

    <div class="address-summary">
        <div class="box">
            <div class="title">Balance (LBC)</div>
            <div class="value"><?php echo $this->Amount->format($balanceAmount) ?></div>
        </div>

        <div class="box">
            <div class="title">Received (LBC)</div>
            <div class="value"><?php echo $this->Amount->format($totalReceived) ?></div>
        </div>

        <div class="box last">
            <div class="title">Sent (LBC)</div>
            <div class="value"><?php echo $this->Amount->format($totalSent) ?></div>
        </div>

        <div class="clear"></div>
    </div>

    <div class="clear"></div>
</div>

<div class="recent-transactions">
    <h3>Transactions</h3>
    <div class="results-meta">
        <?php if ($numRecords > 0):
        $begin = ($currentPage - 1) * $pageLimit + 1;
        ?>
        Showing <?php echo number_format($begin, 0, '', ',') ?> - <?php echo number_format(min($numRecords, ($begin + $pageLimit) - 1), 0, '', ','); ?> of <?php echo number_format($numRecords, 0, '', ','); ?> transaction<?php echo $numRecords == 1 ? '' : 's' ?>
        <?php endif; ?>
    </div>

    <table class="table tx-table">
        <thead>
            <tr>
                <th class="w125 left">Height</th>
                <th class="w250 left">Transaction Hash</th>
                <th class="left">Timestamp</th>
                <th class="w125 right">Confirmations</th>
                <th class="w80 right">Inputs</th>
                <th class="w80 right">Outputs</th>
                <th class="w225 right">Amount</th>
            </tr>
        </thead>

        <tbody>
            <?php if (count($recentTxs) == 0): ?>
            <tr>
                <td class="nodata" colspan="7">There are no recent transactions to display for this wallet.</td>
            </tr>
            <?php endif; ?>

            <?php foreach ($recentTxs as $tx): ?>
            <tr>
                <td class="w125"><?php if ($tx->height === null): ?><em>Unconfirmed</em><?php else: ?><a href="/blocks/<?php echo $tx->height ?>"><?php echo $tx->height ?></a><?php endif; ?></td>
                <td class="w250"><div><a href="/tx/<?php echo $tx->hash ?>?address=<?php echo $address->address ?>#<?php echo $address->address ?>"><?php echo $tx->hash ?></a></div></td>
                <td><?php echo \DateTime::createFromFormat('U', $tx->transaction_time)->format('d M Y H:i:s') . ' UTC'; ?></td>
                <td class="right"><?php echo number_format($tx->confirmations, 0, '', ',') ?></td>
                <td class="right"><?php echo $tx->input_count ?></td>
                <td class="right"><?php echo $tx->output_count ?></td>
                <td class="right<?php echo ' ' . ($tx->debit_amount > 0 && $tx->credit_amount > 0 ? 'diff' : ($tx->debit_amount > 0 ? 'debit' : 'credit')) ?>">
                    <?php echo number_format($tx->credit_amount - $tx->debit_amount, 8, '.', ',') ?> LBC
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php echo $this->element('pagination') ?>