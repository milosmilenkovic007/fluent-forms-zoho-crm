<?php
/**
 * Google Ads tracking functionality
 */

if (!defined('ABSPATH')) {
    exit;
}

class HJ_Zoho_Tracking {

    public function __construct() {
        $this->hooks();
    }

    private function hooks() {
        add_action('wp_head', [$this, 'inject_gtag_head']);
        add_action('wp_footer', [$this, 'frontend_tracking_script']);
        add_action('wp_footer', [$this, 'maybe_fire_conversion_footer']);
    }

    public function inject_gtag_head() {
        $o = HJ_Zoho_Ads_Integration::get_opts();
        if (empty($o['ads_conversion_id'])) return;
        ?>
<script async src="https://www.googletagmanager.com/gtag/js?id=AW-<?php echo esc_js($o['ads_conversion_id']); ?>"></script>
<script>
window.dataLayer = window.dataLayer || [];
function gtag(){dataLayer.push(arguments);}
gtag('js', new Date());
gtag('config', 'AW-<?php echo esc_js($o['ads_conversion_id']); ?>');
</script>
        <?php
    }

    public function frontend_tracking_script() {
        $o = HJ_Zoho_Ads_Integration::get_opts();
        ?>
<script>
(function(){
  function getParam(name){
    const m = new URLSearchParams(window.location.search).get(name);
    return m ? m : '';
  }
  function setCookie(n,v,days){
    var d=new Date(); d.setTime(d.getTime()+(days*24*60*60*1000));
    document.cookie = n+"="+encodeURIComponent(v)+"; path=/; expires="+d.toUTCString();
  }
  function getCookie(n){
    var row=(document.cookie.split('; ').find(r=>r.startsWith(n+'='))||'');
    return row ? decodeURIComponent(row.split('=')[1]) : '';
  }

  var gclid  = getParam('gclid')  || getCookie('_gclid')  || '';
  var gbraid = getParam('gbraid') || getCookie('_gbraid') || '';
  var wbraid = getParam('wbraid') || getCookie('_wbraid') || '';
  var utm_source   = getParam('utm_source')   || getCookie('_utm_source')   || '';
  var utm_medium   = getParam('utm_medium')   || getCookie('_utm_medium')   || '';
  var utm_campaign = getParam('utm_campaign') || getCookie('_utm_campaign') || '';
  var utm_term     = getParam('utm_term')     || getCookie('_utm_term')     || '';
  var utm_content  = getParam('utm_content')  || getCookie('_utm_content')  || '';

  if (gclid)  setCookie('_gclid', gclid, 90);
  if (gbraid) setCookie('_gbraid', gbraid, 90);
  if (wbraid) setCookie('_wbraid', wbraid, 90);
  if (utm_source)   setCookie('_utm_source', utm_source, 90);
  if (utm_medium)   setCookie('_utm_medium', utm_medium, 90);
  if (utm_campaign) setCookie('_utm_campaign', utm_campaign, 90);
  if (utm_term)     setCookie('_utm_term', utm_term, 90);
  if (utm_content)  setCookie('_utm_content', utm_content, 90);

  document.addEventListener('DOMContentLoaded', function(){
    function addHidden(form,name,val){
      var i=document.createElement('input'); i.type='hidden'; i.name=name; i.value=val||''; form.appendChild(i);
    }
    document.querySelectorAll('form.fluent_form').forEach(function(f){
      addHidden(f,'gclid', gclid);
      addHidden(f,'gbraid', gbraid);
      addHidden(f,'wbraid', wbraid);
      addHidden(f,'utm_source', utm_source);
      addHidden(f,'utm_medium', utm_medium);
      addHidden(f,'utm_campaign', utm_campaign);
      addHidden(f,'utm_term', utm_term);
      addHidden(f,'utm_content', utm_content);
      addHidden(f,'lead_source_url', window.location.href);
    });

    <?php if (!empty($o['ads_conversion_id']) && !empty($o['ads_conversion_label']) && !empty($o['fire_on_submit'])): ?>
    document.addEventListener('fluentform_submission_success', function(){
      if (typeof gtag === 'function') {
        gtag('event', 'conversion', {'send_to': 'AW-<?php echo esc_js($o['ads_conversion_id']); ?>/<?php echo esc_js($o['ads_conversion_label']); ?>'});
      }
    });
    <?php endif; ?>
  });
})();
</script>
        <?php
    }

    public function maybe_fire_conversion_footer() {
        $o = HJ_Zoho_Ads_Integration::get_opts();
        if (empty($o['ads_conversion_id']) || empty($o['ads_conversion_label'])) return;

        $slug    = trim($o['thankyou_slug'], "/");
        $current = trim(parse_url(add_query_arg([]), PHP_URL_PATH), "/");
        if ($slug && $current === $slug) {
            ?>
<script>
if (typeof gtag === 'function') {
  gtag('event', 'conversion', {'send_to': 'AW-<?php echo esc_js($o['ads_conversion_id']); ?>/<?php echo esc_js($o['ads_conversion_label']); ?>'});
}
</script>
            <?php
        }
    }
}