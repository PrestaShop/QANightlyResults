<?php
    if ($GA_key !== false && $GA_key != '') {
        ?>
        <script async src="https://www.googletagmanager.com/gtag/js?id=<?php echo $GA_key; ?>"></script>
        <script>
          window.dataLayer = window.dataLayer || [];

          function gtag() {
            dataLayer.push(arguments);
          }

          gtag('js', new Date());

          gtag('config', <?php echo $GA_key; ?>);
        </script>

        <?php
    }
 ?>
</body>
</html>