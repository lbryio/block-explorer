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

    <!-- Analytics -->
    <?php if ($_SERVER['HTTP_HOST'] === 'explorer.lbry.io'): ?>
    <!-- Global site tag (gtag.js) - Google Analytics -->
    <script async src="//www.googletagmanager.com/gtag/js?id=UA-60403362-1"></script>
    <script>
      window.dataLayer = window.dataLayer || [];
      function gtag(){dataLayer.push(arguments);}
      gtag('js', new Date());
      gtag('config', 'UA-60403362-4');
    </script>
    <?php endif; ?>

    <script type="text/javascript">
        // handle coinomi and lbry app urls
        var hashpart = window.location.hash;
        if (hashpart.length > 3) {
            hashpart = hashpart.substring(3);
            var txhash = null;
            if (hashpart.indexOf('?id=') > -1) {
                txhash = hashpart.substring(hashpart.indexOf('?id=') + 4);
            } else {
                var parts = hashpart.split('/');
                if (parts.length > 1) {
                    txhash = parts[1];
                }
            }

            if (txhash && $.trim(txhash.trim).length > 0) {
                window.location.href = '/tx/' + txhash;
            }
        }
    </script>

    <?php echo $this->fetch('meta') ?>
    <?php echo $this->fetch('css') ?>
    <?php echo $this->fetch('script') ?>
</head>
<body>
    <?php echo $this->fetch('content') ?>
    <footer>
        <div class="content">
            <a href="https://lbry.com">LBRY</a>

            <div class="page-time">Page took <?php echo round((microtime(true) - TIME_START) * 1000, 0) ?>ms</div>
        </div>
    </footer>
</body>
</html>
