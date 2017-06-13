<!DOCTYPE html>
<html>
<head>
    <?= $this->Html->charset(); ?>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>
        LBRY Block Explorer &bull; <?= $this->fetch('title') ?>
    </title>
    <?php echo ''; /* $this->Html->meta('icon') */?>

    <?php echo $this->Html->script('jquery.js') ?>
    <?php echo $this->Html->script('moment.js') ?>

    <?php echo $this->Html->css('main.css') ?>

    <script src="https://use.typekit.net/yst3vhs.js"></script>
    <script>try{Typekit.load({ async: true });}catch(e){}</script>

    <?php if ($_SERVER['HTTP_HOST'] !== 'local.lbry.block.ng'): ?>
    <!-- Analytics -->
    <script type="text/javascript">
        var _paq = _paq || [];
        /* tracker methods like "setCustomDimension" should be called before "trackPageView" */
        _paq.push(['trackPageView']);
        _paq.push(['enableLinkTracking']);
        (function() {
            var u="//analytics.aureolin.co/";
            _paq.push(['setTrackerUrl', u+'piwik.php']);
            _paq.push(['setSiteId', '1']);
            var d=document, g=d.createElement('script'), s=d.getElementsByTagName('script')[0];
            g.type='text/javascript'; g.async=true; g.defer=true; g.src=u+'piwik.js'; s.parentNode.insertBefore(g,s);
        })();
    </script>
    <!-- End Analytics Code -->
    <?php endif; ?>

    <?php echo $this->fetch('meta') ?>
    <?php echo $this->fetch('css') ?>
    <?php echo $this->fetch('script') ?>
</head>
<body>
    <?php echo $this->fetch('content') ?>
    <footer>
        <div class="content">
            <a href="/">LBRY Block Explorer</a> is an alternative blockchain explorer for the <a href="https://lbry.io/get?r=RzJNi">LBRY</a> blockchain. Please report issues or send feedback to <a href="mailto:hello@aureolin.co?Subject=LBRY%20Block%20Explorer">hello@aureolin.co</a>.<br />
            If you like this explorer, LBC donations to <a href="/address/bacon25yAnhH2YZxKDt4G7DcygXD8xpM1a">bacon25yAnhH2YZxKDt4G7DcygXD8xpM1a</a> are appreciated.<br />
            &copy; 2017 <a href="https://www.aureolin.co">Aureolin</a>.

            <div class="page-time">Page took <?php echo round((microtime(true) - TIME_START) * 1000, 0) ?>ms</div>
        </div>
    </footer>
</body>
</html>

