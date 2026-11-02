<?php
/**
 * The Contact / Book me pop-up (v1.16.0), on every themed page: every
 * .djd-rec-btn opens it instead of linking away (assets/js/contact.js). A
 * record sleeve slides up from the bottom with a vinyl rising out of its
 * top (assets/css/contact.css), holding the site's real Avada form #63559,
 * so submissions keep going where they always went.
 *
 * Avada only prints a form where it is placed, so the form is rendered
 * here, once per page, hidden inside the pop-up. It is rendered early
 * (template_redirect), before wp_head, so any script or style Avada's form
 * element enqueues on its first render still makes it onto the page, and
 * printed in wp_footer. A page whose own content already holds form 63559
 * (the About page until it switches to [djdigi_about]) does not get a
 * second copy (two would share ids): contact.js moves that page's form
 * into the sleeve while the pop-up is open and puts it back on close.
 * If no form can be found at all, the buttons stay plain links.
 * See CLAUDE.md, "v1.16.0".
 *
 * @package djdigi-theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'DJDT_CONTACT_FORM_ID', 63559 );

add_action( 'wp_enqueue_scripts', function () {
	if ( ! djdt_is_active() ) {
		return;
	}
	wp_enqueue_style( 'djdt-contact', DJDT_URL . 'assets/css/contact.css', array( 'djdt-site' ), djdt_ver( 'assets/css/contact.css' ) );
	wp_enqueue_script( 'djdt-contact', DJDT_URL . 'assets/js/contact.js', array(), djdt_ver( 'assets/js/contact.js' ), true );
}, 20 );

/** True when the current page's own content already carries the contact form. */
function djdt_contact_form_in_content() {
	if ( ! is_singular() ) {
		return false;
	}
	$post = get_post();
	return $post && preg_match( '/\bform_post_id=["\']?' . DJDT_CONTACT_FORM_ID . '\b/', $post->post_content );
}

/** The rendered form, '' if the page has its own or Avada's form element is not there. Rendered once, see above. */
function djdt_contact_form_html() {
	static $html = null;
	if ( null !== $html ) {
		return $html;
	}
	$html = '';
	if ( ! djdt_is_active() || djdt_contact_form_in_content() || ! shortcode_exists( 'fusion_form' ) ) {
		return $html;
	}
	$out = do_shortcode( '[fusion_form form_post_id="' . DJDT_CONTACT_FORM_ID . '" /]' );
	if ( is_string( $out ) && false !== strpos( $out, 'fusion-form-' . DJDT_CONTACT_FORM_ID ) ) {
		$html = $out;
	}
	return $html;
}

add_action( 'template_redirect', function () {
	if ( djdt_is_active() && ! is_feed() && ! is_embed() ) {
		djdt_contact_form_html();
	}
}, 20 );

add_action( 'wp_footer', function () {
	if ( djdt_is_active() && ! is_feed() && ! is_embed() ) {
		echo djdt_contact_popup_html(); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped inside
	}
}, 5 );

/** The pop-up's markup, the form inside it (or an empty slot when the page has its own form). */
function djdt_contact_popup_html() {
	$form = djdt_contact_form_html();
	$sub  = trim( (string) djdt_get( 'popup_sub' ) );
	ob_start();
	?>
	<div class="djx-pop" id="djx-pop" hidden data-form-id="<?php echo (int) DJDT_CONTACT_FORM_ID; ?>">
		<div class="djx-pop__backdrop" data-djx-close></div>
		<div class="djx-pop__panel" role="dialog" aria-modal="true" aria-labelledby="djx-pop-title">
			<div class="djx-pop__vinyl" aria-hidden="true"><span class="djx-pop__sheen"></span></div>
			<div class="djx-pop__sleeve" tabindex="-1">
				<button type="button" class="djx-pop__close" aria-label="Close" data-djx-close><svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round"/></svg></button>
				<h3 class="djx-pop__title" id="djx-pop-title">Contact / Book me</h3>
				<?php if ( '' !== $sub ) : ?>
					<p class="djx-pop__sub"><?php echo esc_html( $sub ); ?></p>
				<?php endif; ?>
				<div class="djx-pop__form"><?php echo $form; // phpcs:ignore WordPress.Security.EscapeOutput -- Avada's own form element output ?></div>
			</div>
		</div>
	</div>
	<?php
	return ob_get_clean();
}
