<?php $this->assign('title', 'Home') ?>

<?php $this->start('script'); ?>
<script type="text/javascript">
    var updateInterval = 120000;

    var updateStatus = function() {
        $.ajax({
            url: '/api/v1/status',
            type: 'get',
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    var status = response.status;
                    var stats = $('.stats');
                    stats.find('.box:eq(0) > .value').text(status.height);
                    stats.find('.box:eq(1) > .value').text(status.difficulty);
                    stats.find('.box:eq(2) > .value').text(status.hashrate);
                    stats.find('.box:eq(3) > .value').text(status.price);
                }
            }
        });
    };

    var updateRecentBlocks = function() {
        var tbody = $('.recent-blocks .table tbody');

        $.ajax({
            url: '/api/v1/recentblocks',
            type: 'get',
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    var blocks = response.blocks;
                    for (var i = blocks.length - 1; i >= 0; i--) {
                        var block = blocks[i];
                        var prevRow = tbody.find('tr[data-height="' + block.Height + '"]');
                        if (prevRow.length > 0) {
                            continue;
                        }

                        var blockTime = moment(block.BlockTime * 1000);
                        tbody.prepend(
                            $('<tr></tr>').attr({'data-height': block.Height, 'data-time': block.BlockTime}).append(
                                $('<td></td>').append(
                                    $('<a></a>').attr({'href': '/blocks/' + block.Height}).text(block.Height)
                                )
                            ).append(
                                $('<td></td>').text(blockTime.fromNow())
                            ).append(
                                $('<td></td>').attr({'class': 'right'}).text((block.BlockSize / 1024).toFixed(2) + 'KB')
                            ).append(
                                $('<td></td>').attr({'class': 'right'}).text(block.TransactionCount)
                            ).append(
                                $('<td></td>').attr({'class': 'right'}).text(block.Difficulty)
                            ).append(
                                $('<td></td>').attr({'class': 'last-cell'}).text(blockTime.utc().format('D MMM YYYY HH:mm:ss') + ' UTC')
                            )
                        );

                        // Remove the last row
                        tbody.find('tr:last').remove();
                    }
                }
            },
            complete: function() {
                tbody.find('tr').each(function() {
                    var row = $(this);
                    var blockTime = moment(row.attr('data-time') * 1000);
                    row.find('td:eq(1)').text(blockTime.fromNow());
                });
            }
        });
    };

    $(document).ready(function() {
        setInterval(updateStatus, updateInterval);
        setInterval(updateRecentBlocks, updateInterval);

        $(document).on('click', '.recent-claims .claim-box .tx-link', function(evt) {
            evt.stopImmediatePropagation();
        });

        $(document).on('click', '.recent-claims .claim-box', function() {
            var id = $(this).attr('data-id');
            window.location.href = '/claims/' + id;

            // center the popup
            /*var dualScreenLeft = window.screenLeft != undefined ? window.screenLeft : screen.left;
            var dualScreenTop = window.screenTop != undefined ? window.screenTop : screen.top;
            var width = window.innerWidth ? window.innerWidth : document.documentElement.clientWidth ? document.documentElement.clientWidth : screen.width;
            var height = window.innerHeight ? window.innerHeight : document.documentElement.clientHeight ? document.documentElement.clientHeight : screen.height;

            var left = ((width / 2) - (1366 / 2)) + dualScreenLeft;
            var top = ((height / 2) - (768 / 2)) + dualScreenTop;

            window.open('/claims/' + id, 'claim_details', 'width=1366,height=768,left=' + left + ',top=' + top);*/
        });

        $('.claim-box img').on('error', function() {
            var img = $(this);
            var parent = img.parent();
            var text = parent.attr('data-autothumb');
            img.remove();
            parent.append(
                $('<div></div>').attr({'class': 'autothumb' }).text(text)
            );
        });
    });
</script>
<?php $this->end(); ?>

<div class="home-container">
    <div class="home-container-cell">
        <div class="main">
            <div class="title">LBRY Block Explorer</div><br>
            <form method="get" action="/find">
                <input class="search-input" name="q" type="text" placeholder="Enter a block height or hash, claim id or name, transaction hash or address" />
                <div class="ctls">
                    <div class="left-links"><a href="https://lbry.io/get">Download the LBRY App</a></div>
                    <button class="btn btn-search">Search</button>
                    <div class="right-links">
                        <a href="/realtime">Realtime</a> &bull; <a href="/stats" class="last">Stats</a>
                    </div>
                </div>
            </form>
        </div>

        <div class="stats">
            <div class="box">
                <div class="title">Block Height</div>
                <div class="value"><?php echo $recentBlocks[0]->Height ?></div>
            </div>

            <div class="box">
                <div class="title">Difficulty</div>
                <div class="value" title="<?php echo $recentBlocks[0]->Difficulty ?>"><?php echo number_format($recentBlocks[0]->Difficulty, 2, '.', '') ?></div>
            </div>

            <div class="box">
                <div class="title">Network</div>
                <div class="value"><?php echo $hashRate ?></div>
            </div>

            <div class="box last">
                <div class="title">Price</div>
                <div class="value"><?php echo $lbcUsdPrice ?></div>
            </div>

            <div class="clear"></div>
        </div>

        <div class="recent-blocks">
            <h3>Recent Blocks</h3>
            <a class="all-blocks-link" href="/blocks">LBRY Blocks</a>
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th class="left w125">Height</th>
                        <th class="left w125">Age</th>
                        <th class="right w150">Block Size</th>
                        <th class="right w150">Transactions</th>
                        <th class="right w150">Difficulty</th>
                        <th class="left w250 last-cell">Timestamp</th>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach ($recentBlocks as $block): ?>
                    <tr data-height="<?php echo $block->Height ?>" data-time="<?php echo $block->BlockTime ?>">
                        <td><a href="/blocks/<?php echo $block->Height ?>"><?php echo $block->Height ?></a></td>
                        <td><?php echo \Carbon\Carbon::createFromTimestamp($block->BlockTime)->diffForHumans(); ?></td>
                        <td class="right"><?php echo round($block->BlockSize / 1024, 2) . 'KB' ?></td>
                        <td class="right"><?php echo $block->TransactionCount ?></td>
                        <td class="right"><?php echo number_format($block->Difficulty, 2, '.', '') ?></td>
                        <td class="last-cell"><?php echo DateTime::createFromFormat('U', $block->BlockTime)->format('d M Y H:i:s') . ' UTC' ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="recent-claims">
            <h3>Recent Claims</h3>
            <a class="claim-explorer-link" href="/claims">Claims Explorer</a>
            <?php $idx = 0; $a = ['purple', 'orange', 'blue', 'teal', 'green', 'yellow']; foreach ($recentClaims as $claim):
                $idx++;
                $autoThumbText = $claim->getAutoThumbText();
                $link = $claim->Name;
                $rawLink = $claim->Name;
                if (isset($claim->Publisher->Name)) {
                    $link = urlencode($claim->Publisher->Name) . '/' . $link;
                    $rawLink = $claim->Publisher->Name . '/' . $link;
                }
                $link = 'lbry://' . $link;
                $rawLink = 'lbry://' . $rawLink;

                // content type
                $ctTag = $claim->getContentTag();
            ?>
            <div data-id="<?php echo $claim->ClaimId ?>" class="claim-box<?php if ($idx == 5): ?> last<?php endif; ?>">
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
                        <img src="<?php echo strip_tags($claim->ThumbnailUrl) ?>" alt="" />
                    <?php else: ?>
                        <div class="autothumb"><?php echo $autoThumbText ?></div>
                    <?php endif; ?>
                </div>

                <div class="metadata">
                    <div class="title" title="<?php echo $claim->ClaimType == 1 ? $claim->Name : ((strlen(trim($claim->Title)) > 0) ? $claim->Title : ''); ?>"><?php echo $claim->ClaimType == 1 ? $claim->Name : ((strlen(trim($claim->Title)) > 0) ? $claim->Title : '<em>No Title</em>') ?></div>
                    <div class="link" title="<?php echo $rawLink ?>"><a href="<?php echo $link ?>"><?php echo $rawLink ?></a></div>

                    <div class="clear"></div>
                    <?php if ($claim->ClaimType == 2 && strlen(trim($claim->Description)) > 0): ?>
                    <div class="desc"><?php echo $claim->Description ?></div>
                    <?php endif; ?>
                </div>

                <a class="tx-link" href="/tx/<?php echo $claim->TransactionHash ?>#output-<?php echo $claim->Vout ?>" target="_blank">Transaction</a>
            </div>
            <?php endforeach; ?>

            <div class="clear"></div>
        </div>
    </div>

</div>
