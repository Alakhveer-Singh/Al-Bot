<?php
/**
 * Chat Widget — outputs the widget mount point in wp_footer.
 * The shortcode [ai_site_chat] is registered in al-bot-agency-plugin.php.
 * All rendering is handled by assets/js/chat-widget.js.
 *
 * @package AI_Site_Chat
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Output window.aichatConfig at the very start of wp_footer (priority 1)
 * so it is always defined before chat-widget.js runs, even when a script
 * optimiser (Autoptimize, WP Rocket, etc.) reorders or bundles scripts.
 *
 * data-noptimize  — tells Autoptimize to leave this script alone
 * data-cfasync    — tells Cloudflare Rocket Loader not to defer it
 * type="text/javascript" — some optimisers skip non-default types; this
 *                          ensures maximum compatibility
 */
function aichat_output_inline_config() {

	if ( is_admin() ) {
		return;
	}

	$active_key = trim( aichat_get_active_provider()['api_key'] );

	if ( empty( $active_key ) ) {
		return;
	}

	$inline_config = array(
		'ajaxUrl'         => admin_url( 'admin-ajax.php' ),
		'nonce'           => wp_create_nonce( 'aichat_lead_nonce' ),
		'trackNonce'      => wp_create_nonce( 'aichat_track_nonce' ),
		'handoffNonce'    => wp_create_nonce( 'aichat_handoff_nonce' ),
		'chatNonce'       => wp_create_nonce( 'aichat_chat_nonce' ),
		'configNonce'     => wp_create_nonce( 'aichat_get_widget_config' ),
		'botName'         => get_option( 'aichat_bot_name', 'AI Assistant' ),
		'botEmoji'        => get_option( 'aichat_bot_emoji', '🤖' ),
		'botIconUrl'      => aichat_get_bot_avatar_url(),
		'primaryColor'    => get_option( 'aichat_primary_color', '#22c55e' ),
		'welcomeMessage'  => get_option( 'aichat_welcome_message', 'Hi! How can I help you today?' ),
		'leadCaptureMode' => get_option( 'aichat_lead_capture_mode', 'conversational' ),
		'widgetTheme'     => get_option( 'aichat_widget_theme', 'light' ),
		'hideBranding'    => get_option( 'aichat_hide_branding', '' ) === '1',
		'proactiveEnabled' => get_option( 'aichat_proactive_enabled', '' ) === '1',
		'proactiveRules'   => aichat_get_active_proactive_rules(),
		'streamingEnabled' => get_option( 'aichat_streaming_enabled', '' ) === '1',
		// No system prompt / knowledge base content here — the server builds
		// the prompt itself in aichat_ajax_proxy_llm() so it's never exposed
		// in page source (see al-bot-agency-plugin.php).
		'siteName'        => get_bloginfo( 'name' ),
		'pageUrl'         => esc_url_raw( home_url( isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '/' ) ),
	);

	// JSON_HEX_TAG escapes < > so </script> inside values can never break the tag.
	$config_json = wp_json_encode( $inline_config, JSON_HEX_TAG | JSON_UNESCAPED_UNICODE );

	if ( false === $config_json ) {
		return; // Encoding failed — wp_localize_script output will act as fallback.
	}
	?>
	<script type="text/javascript" data-noptimize="1" data-cfasync="false">window.aichatConfig=<?php echo $config_json; ?>;</script>
	<?php
}

/**
 * Render the floating widget mount point in the page footer.
 */
function aichat_render_chat_widget() {

	// Never output on admin pages.
	if ( is_admin() ) {
		return;
	}

	// Only render when an API key is configured for the active provider.
	$active_key = trim( aichat_get_active_provider()['api_key'] );

	if ( empty( $active_key ) ) {
		return;
	}

	// Fallback enqueue in case wp_enqueue_scripts was suppressed.
	if ( ! wp_script_is( 'aichat-widget', 'enqueued' ) &&
	     ! wp_script_is( 'aichat-widget', 'done' ) ) {
		aichat_enqueue_frontend_assets();
	}
	?>
	<div
		id="aichat-widget-root"
		aria-label="<?php echo esc_attr( get_option( 'aichat_bot_name', 'AI Assistant' ) ); ?> Chat"
		role="complementary"
	></div>
	<?php
}
