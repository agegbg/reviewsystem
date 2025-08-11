<?php
/**
 * footer.php
 * Common closing tags and JS includes.
 *
 * Usage:
 *   $EXTRA_FOOT = '<script>console.log("page ready")</script>';
 *   require_once __DIR__ . '/footer.php';
 */
if (!isset($EXTRA_FOOT)) { $EXTRA_FOOT = ''; }
?>

  <!-- Optional per-page scripts -->
  <?= $EXTRA_FOOT ?>

  <!-- Bootstrap JS (with Popper & minimal jQuery for BS4 tooltips/dropdowns) -->
  <script src="https://code.jquery.com/jquery-3.3.1.slim.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.7/umd/popper.min.js"></script>
  <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/js/bootstrap.min.js"></script>

  <!-- Matomo Analytics -->
  <script>
    var _paq = window._paq = window._paq || [];
    _paq.push(['trackPageView']);
    _paq.push(['enableLinkTracking']);
    (function() {
      var u="//www.zebras.se/matomo/";
      _paq.push(['setTrackerUrl', u+'matomo.php']);
      _paq.push(['setSiteId', '1']);
      var d=document, g=d.createElement('script'), s=d.getElementsByTagName('script')[0];
      g.async=true; g.src=u+'matomo.js'; s.parentNode.insertBefore(g,s);
    })();
  </script>
  <!-- End Matomo Code -->

</body>
</html>
