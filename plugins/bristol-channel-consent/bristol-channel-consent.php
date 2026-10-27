<?php
/**
 * Plugin Name: Bristol Channel Consent
 * Description: Minimal cookie banner. Google analytics storage stays denied until the reader clicks Accept. No dashboard, no settings.
 * Version: 1.0.0
 * Author: Patrick Dunne
 * License: GPL-2.0-or-later
 * Text Domain: bristol-channel-consent
 *
 * @package bristol-channel-consent
 */

defined( 'ABSPATH' ) || exit;

/* Runs before Site Kit's Google tag: analytics storage is denied by default, and switched on at once for readers who already accepted. */
add_action(
	'wp_head',
	function () {
		echo <<<'HTML'
<script>
window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}
gtag('consent','default',{ad_storage:'denied',ad_user_data:'denied',ad_personalization:'denied',analytics_storage:'denied',wait_for_update:500});
try{if(localStorage.getItem('bc_consent')==='granted'){gtag('consent','update',{analytics_storage:'granted'});}}catch(e){}
</script>

HTML;
	},
	1
);

/* The banner itself: hidden until the script finds that no choice has been made yet. */
add_action(
	'wp_footer',
	function () {
		$policy = '';
		$page   = (int) get_option( 'wp_page_for_privacy_policy' );
		if ( $page && 'publish' === get_post_status( $page ) ) {
			$policy = ' <a href="' . esc_url( get_permalink( $page ) ) . '">' . esc_html__( 'Privacy policy', 'bristol-channel-consent' ) . '</a>';
		}
		?>
<style>
#bc-consent{position:fixed;left:0;right:0;bottom:0;z-index:99999;display:flex;flex-wrap:wrap;gap:12px 16px;align-items:center;justify-content:center;padding:12px 20px;background:#fff;border-top:3px solid #005470;font:14px/1.4 inherit;font-family:inherit;color:#111}
#bc-consent[hidden]{display:none}
#bc-consent p{margin:0}
#bc-consent a{color:inherit;text-decoration:underline}
#bc-consent button{font:inherit;font-weight:600;padding:7px 16px;cursor:pointer;border:1px solid #005470;background:#fff;color:#005470}
#bc-consent button[data-bc="granted"]{background:#005470;color:#fff}
</style>
<div id="bc-consent" role="dialog" aria-label="<?php echo esc_attr__( 'Cookie notice', 'bristol-channel-consent' ); ?>" hidden>
	<p><?php echo esc_html__( 'We use cookies to see how the site is used.', 'bristol-channel-consent' ); ?><?php echo $policy; // phpcs:ignore WordPress.Security.EscapeOutput ?></p>
	<div><button type="button" data-bc="denied"><?php echo esc_html__( 'Decline', 'bristol-channel-consent' ); ?></button> <button type="button" data-bc="granted"><?php echo esc_html__( 'Accept', 'bristol-channel-consent' ); ?></button></div>
</div>
<script>
(function(){
var b=document.getElementById('bc-consent');if(!b)return;
function get(){try{return localStorage.getItem('bc_consent');}catch(e){return null;}}
function set(v){try{localStorage.setItem('bc_consent',v);}catch(e){}}
if(!get()){b.hidden=false;}
b.addEventListener('click',function(e){var t=e.target.closest('[data-bc]');if(!t)return;var v=t.getAttribute('data-bc');set(v);if(typeof gtag==='function'){gtag('consent','update',{analytics_storage:v==='granted'?'granted':'denied'});}b.hidden=true;});
document.addEventListener('click',function(e){var a=e.target.closest('a[href="#cookie-settings"]');if(a){e.preventDefault();b.hidden=false;}});
})();
</script>
		<?php
	},
	99
);
