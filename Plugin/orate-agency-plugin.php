<?php
/**
 * Plugin Name:       Orate — Agency Plugin
 * Plugin URI:
 * Description:       Full-featured AI chat plugin for agencies — unlimited WordPress sites, all 6 AI model integrations, auto-training, smart lead capture, knowledge base uploads, analytics, and white-label bot branding.
 * Version:           1.1.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:
 * Author URI:
 * License:           GPL v2
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       orate-agency
 * Domain Path:       /languages
 *
 * == Changelog ==
 *
 * = 1.1.0 =
 * * Proactive Triggers — the widget can open itself and send a canned opening
 *   line based on visitor behaviour (exit intent, idle, scroll depth, URL
 *   match, time on site). Off by default; rules live in `aichat_proactive_rules`.
 * * Memory Across Visits — a returning visitor whose name was captured in an
 *   earlier session is greeted by name, and the model is told not to ask again.
 * * Streaming Responses — token-by-token SSE streaming for all six providers,
 *   behind the `aichat_streaming_enabled` flag (off by default).
 * * Visual Chat Replay — Analytics sessions can be opened as a real chat
 *   transcript, with lead summary, trigger badge, and per-session CSV export.
 * * Animations — launcher pulse, panel scale-in, proactive bounce, message
 *   entrance, typing dots, and staggered suggestion chips (all reduced-motion aware).
 * * DB version 3 — adds `triggered_by` and `sequence` to the analytics table.
 *
 * = 1.0.0 =
 * * Initial release.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ─── Constants ────────────────────────────────────────────────────────────────

define( 'AICHAT_VERSION',          '1.1.0' );
define( 'AICHAT_PLUGIN_DIR',       plugin_dir_path( __FILE__ ) );
define( 'AICHAT_PLUGIN_URL',       plugin_dir_url( __FILE__ ) );
define( 'AICHAT_DB_TABLE',         'aichat_leads' );
define( 'AICHAT_ANALYTICS_TABLE',  'aichat_analytics' );
define( 'AICHAT_KB_FILES_TABLE',   'aichat_kb_files' );
define( 'AICHAT_DB_VERSION',       '3' );

// ─── Includes ─────────────────────────────────────────────────────────────────

require_once AICHAT_PLUGIN_DIR . 'scraper.php';
require_once AICHAT_PLUGIN_DIR . 'admin-page.php';
require_once AICHAT_PLUGIN_DIR . 'chat-widget.php';

// ─── Activation ───────────────────────────────────────────────────────────────

register_activation_hook( __FILE__, 'aichat_activate' );

function aichat_create_tables() {
	global $wpdb;
	$charset_collate = $wpdb->get_charset_collate();
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	// Leads table — conversational lead capture (name, email, phone, requirement).
	$leads_table = $wpdb->prefix . AICHAT_DB_TABLE;
	dbDelta( "CREATE TABLE {$leads_table} (
		id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		session_id varchar(64) NOT NULL DEFAULT '',
		name varchar(255) NOT NULL DEFAULT '',
		email varchar(255) NOT NULL DEFAULT '',
		phone varchar(50) NOT NULL DEFAULT '',
		requirement text NOT NULL,
		transcript longtext NOT NULL,
		created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
		updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY  (id),
		KEY session_id (session_id(32))
	) {$charset_collate};" );

	// Analytics table.
	$analytics_table = $wpdb->prefix . AICHAT_ANALYTICS_TABLE;
	dbDelta( "CREATE TABLE {$analytics_table} (
		id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		session_id varchar(64) NOT NULL DEFAULT '',
		user_question text NOT NULL,
		bot_answer text NOT NULL,
		is_unanswered tinyint(1) NOT NULL DEFAULT 0,
		page_url varchar(500) NOT NULL DEFAULT '',
		triggered_by varchar(64) DEFAULT NULL,
		sequence int(11) NOT NULL DEFAULT 0,
		created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY  (id),
		KEY session_id (session_id(32)),
		KEY created_at (created_at)
	) {$charset_collate};" );

	// Knowledge base files table.
	$kb_table = $wpdb->prefix . AICHAT_KB_FILES_TABLE;
	dbDelta( "CREATE TABLE {$kb_table} (
		id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		file_name varchar(255) NOT NULL DEFAULT '',
		file_type varchar(20) NOT NULL DEFAULT '',
		file_path varchar(500) NOT NULL DEFAULT '',
		file_size bigint(20) UNSIGNED NOT NULL DEFAULT 0,
		content longtext NOT NULL,
		uploaded_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY  (id)
	) {$charset_collate};" );

	update_option( 'aichat_db_version', AICHAT_DB_VERSION );
}

function aichat_activate() {
	aichat_create_tables();

	// Ensure upload directory exists and is protected.
	aichat_ensure_upload_dir();

	// Schedule daily auto-training.
	if ( ! wp_next_scheduled( 'aichat_auto_train_cron' ) ) {
		wp_schedule_event( time(), 'daily', 'aichat_auto_train_cron' );
	}

	// Default options.
	add_option( 'aichat_bot_name',           'AI Assistant' );
	add_option( 'aichat_bot_emoji',          '🤖' );
	add_option( 'aichat_primary_color',      '#22c55e' );
	add_option( 'aichat_welcome_message',    'Hi! How can I help you today?' );
	add_option( 'aichat_bot_role',           'Customer Support Assistant' );
	add_option( 'aichat_bot_tone',           'friendly' );
	add_option( 'aichat_bot_personality',    '' );
	add_option( 'aichat_custom_instructions', '' );
	add_option( 'aichat_kb_urls',            array() );
	add_option( 'aichat_handoff_email',      '' );
	add_option( 'aichat_human_handoff',      '1' );
	add_option( 'aichat_voice_mode',         '1' );
	add_option( 'aichat_bot_icon_id',        0 );
	// Multi-LLM defaults.
	add_option( 'aichat_llm_provider',    'claude' );
	add_option( 'aichat_claude_model',    'claude-sonnet-4-6' );
	add_option( 'aichat_openai_model',    'gpt-4o' );
	add_option( 'aichat_gemini_model',    'gemini-2.0-flash' );
	add_option( 'aichat_mistral_model',   'mistral-large-latest' );
	add_option( 'aichat_llama_model',     'llama-3.3-70b-versatile' );
	add_option( 'aichat_deepseek_model',  'deepseek-chat' );

	// Proactive triggers — master switch off, two disabled sample rules so an
	// admin can see the shape of a rule without having to build one blind.
	add_option( 'aichat_proactive_enabled', '' );
	add_option( 'aichat_proactive_rules',   wp_json_encode( aichat_default_proactive_rules() ) );

	// Streaming responses — off until an admin confirms their host actually
	// flushes (see the note next to the toggle in Settings).
	add_option( 'aichat_streaming_enabled', '' );
}

// ─── Proactive trigger rules ──────────────────────────────────────────────────

/**
 * Trigger types a rule may use, mapped to their admin-facing labels.
 *
 * @return array type => label.
 */
function aichat_proactive_trigger_types() {
	return array(
		'exit_intent'  => __( 'Exit Intent', 'orate-agency' ),
		'idle_on_page' => __( 'Idle On Page', 'orate-agency' ),
		'scroll_depth' => __( 'Scroll Depth', 'orate-agency' ),
		'url_contains' => __( 'URL Contains', 'orate-agency' ),
		'time_on_site' => __( 'Time On Site', 'orate-agency' ),
	);
}

/**
 * The two rules a fresh install ships with. Both disabled — turning the master
 * switch on must not change what a visitor sees until a rule is enabled too.
 *
 * @return array
 */
function aichat_default_proactive_rules() {
	return array(
		array(
			'id'              => 'default-exit-intent',
			'trigger_type'    => 'exit_intent',
			'condition_value' => '',
			'delay_seconds'   => 0,
			'message'         => 'Before you go — can I help you find something?',
			'enabled'         => false,
		),
		array(
			'id'              => 'default-pricing-idle',
			'trigger_type'    => 'url_contains',
			'condition_value' => '/pricing',
			'delay_seconds'   => 8,
			'message'         => 'Questions about pricing? Happy to help.',
			'enabled'         => false,
		),
	);
}

/**
 * Normalise a rule set coming from the admin form or the options table.
 * Everything that reaches the widget passes through here, so a hand-edited
 * option row can't put an unknown trigger type or unescaped message in front
 * of a visitor.
 *
 * @param  mixed $rules Array of rules, or a JSON-encoded array of them.
 * @return array Clean rule list.
 */
function aichat_sanitize_proactive_rules( $rules ) {
	if ( is_string( $rules ) ) {
		$rules = json_decode( $rules, true );
	}
	if ( ! is_array( $rules ) ) {
		return array();
	}

	$types = aichat_proactive_trigger_types();
	$clean = array();
	$index = 0;

	foreach ( $rules as $rule ) {
		if ( ! is_array( $rule ) ) {
			continue;
		}

		$type = isset( $rule['trigger_type'] ) ? sanitize_key( $rule['trigger_type'] ) : '';
		if ( ! isset( $types[ $type ] ) ) {
			continue;
		}

		$message = isset( $rule['message'] ) ? sanitize_textarea_field( $rule['message'] ) : '';
		if ( '' === trim( $message ) ) {
			continue; // A rule with nothing to say has nothing to fire.
		}

		$condition = isset( $rule['condition_value'] ) ? sanitize_text_field( $rule['condition_value'] ) : '';
		if ( 'idle_on_page' === $type || 'time_on_site' === $type ) {
			$condition = (string) max( 1, absint( $condition ) );
		} elseif ( 'scroll_depth' === $type ) {
			$condition = (string) min( 100, max( 1, absint( $condition ) ) );
		} elseif ( 'exit_intent' === $type ) {
			$condition = '';
		}

		$id = isset( $rule['id'] ) ? sanitize_key( $rule['id'] ) : '';
		if ( '' === $id ) {
			$id = 'rule-' . $index;
		}

		$clean[] = array(
			'id'              => $id,
			'trigger_type'    => $type,
			'condition_value' => $condition,
			'delay_seconds'   => min( 600, absint( $rule['delay_seconds'] ?? 0 ) ),
			'message'         => mb_substr( $message, 0, 500 ),
			'enabled'         => ! empty( $rule['enabled'] ),
		);
		$index++;
	}

	return $clean;
}

/**
 * Read the saved rules. Returns [] when proactive mode is off so the widget
 * config never carries rules that can't fire.
 *
 * @param  bool $respect_master_switch Return [] when the master switch is off.
 * @return array
 */
function aichat_get_proactive_rules( $respect_master_switch = true ) {
	if ( $respect_master_switch && '1' !== get_option( 'aichat_proactive_enabled', '' ) ) {
		return array();
	}
	return aichat_sanitize_proactive_rules( get_option( 'aichat_proactive_rules', '' ) );
}

/**
 * Only enabled rules are worth sending to the browser.
 *
 * @return array
 */
function aichat_get_active_proactive_rules() {
	return array_values( array_filter( aichat_get_proactive_rules(), function ( $rule ) {
		return ! empty( $rule['enabled'] );
	} ) );
}

// ─── Upload directory ─────────────────────────────────────────────────────────

/**
 * Resolve the knowledge-base upload directory path. The folder name includes
 * a random per-install token so it can't be guessed even on servers (nginx,
 * etc.) where the .htaccess "deny from all" below has no effect.
 */
function aichat_get_kb_dir() {
	$token = get_option( 'aichat_kb_dir_token', '' );
	if ( empty( $token ) ) {
		$token = wp_generate_password( 12, false, false );
		update_option( 'aichat_kb_dir_token', $token );
	}
	$upload_dir = wp_upload_dir();
	return $upload_dir['basedir'] . '/ai-site-chat-' . $token . '/';
}

function aichat_ensure_upload_dir() {
	$kb_dir = aichat_get_kb_dir();

	if ( ! file_exists( $kb_dir ) ) {
		wp_mkdir_p( $kb_dir );
	}
	if ( ! file_exists( $kb_dir . '.htaccess' ) ) {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		file_put_contents( $kb_dir . '.htaccess', "deny from all\n" );
	}
	if ( ! file_exists( $kb_dir . 'index.php' ) ) {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		file_put_contents( $kb_dir . 'index.php', "<?php // Silence is golden.\n" );
	}
}

// ─── One-time migrations (runs on every page load, cheap option check) ────────

add_action( 'init', 'aichat_run_migrations' );

function aichat_run_migrations() {
	// Upgrade DB schema on version bumps (covers sites that installed before AICHAT_DB_VERSION existed).
	if ( get_option( 'aichat_db_version', '' ) !== AICHAT_DB_VERSION ) {
		aichat_create_tables();
	}

	// Options added after 1.0.0 — add_option() is a no-op when the row exists,
	// so an existing install upgrading in place gets the same safe defaults a
	// fresh activation would, instead of undefined-option fallbacks.
	add_option( 'aichat_proactive_enabled', '' );
	add_option( 'aichat_proactive_rules',   wp_json_encode( aichat_default_proactive_rules() ) );
	add_option( 'aichat_streaming_enabled', '' );

	// Replace any outdated/invalid Claude model IDs with the current default.
	aichat_normalize_claude_model();
}

// ─── Deactivation ─────────────────────────────────────────────────────────────

register_deactivation_hook( __FILE__, 'aichat_deactivate' );

function aichat_deactivate() {
	wp_clear_scheduled_hook( 'aichat_auto_train_cron' );
}

// ─── Cron ─────────────────────────────────────────────────────────────────────

add_action( 'aichat_auto_train_cron', 'aichat_run_scraper' );

// ─── Admin Menu ───────────────────────────────────────────────────────────────

add_action( 'admin_menu', 'aichat_admin_menu' );

function aichat_admin_menu() {
	// Top-level menu.
	add_menu_page(
		__( 'Orate Agency', 'orate-agency' ),
		__( 'Orate Agency', 'orate-agency' ),
		'manage_options',
		'ai-site-chat-panel',
		'aichat_settings_page',
		'dashicons-format-chat',
		80
	);

	// Settings (replaces auto-generated duplicate).
	add_submenu_page(
		'ai-site-chat-panel',
		__( 'Settings', 'orate-agency' ),
		__( 'Settings', 'orate-agency' ),
		'manage_options',
		'ai-site-chat-panel',
		'aichat_settings_page'
	);

	// Knowledge Base.
	add_submenu_page(
		'ai-site-chat-panel',
		__( 'Knowledge Base', 'orate-agency' ),
		__( 'Knowledge Base', 'orate-agency' ),
		'manage_options',
		'ai-site-chat-kb',
		'aichat_knowledge_page'
	);

	// Analytics.
	add_submenu_page(
		'ai-site-chat-panel',
		__( 'Analytics', 'orate-agency' ),
		__( 'Analytics', 'orate-agency' ),
		'manage_options',
		'ai-site-chat-analytics',
		'aichat_analytics_page'
	);

	// Leads.
	add_submenu_page(
		'ai-site-chat-panel',
		__( 'Leads', 'orate-agency' ),
		__( 'Leads', 'orate-agency' ),
		'manage_options',
		'ai-site-chat-leads',
		'aichat_leads_page'
	);

	// Keep Settings > AI Site Chat working.
	add_options_page(
		__( 'AI Site Chat Settings', 'orate-agency' ),
		__( 'Orate Agency', 'orate-agency' ),
		'manage_options',
		'orate-agency',
		'aichat_settings_page'
	);
}

// ─── Shared provider helper ───────────────────────────────────────────────────

/**
 * Resolve the active LLM provider's key, model, and a normalised model map.
 * Single source of truth — replaces the copy-pasted key/model maps that used
 * to live in 5 different functions.
 *
 * @return array {
 *     @type string $provider    Active provider slug.
 *     @type string $api_key     Active provider's API key.
 *     @type string $model       Active provider's model ID.
 *     @type array  $key_map     provider => api key, for every provider.
 *     @type array  $model_map   provider => model, for every provider.
 * }
 */
function aichat_get_active_provider() {
	aichat_normalize_claude_model();

	$provider = sanitize_text_field( get_option( 'aichat_llm_provider', 'claude' ) );

	$key_map = array(
		'claude'   => get_option( 'aichat_api_key', '' ),
		'openai'   => get_option( 'aichat_openai_api_key', '' ),
		'gemini'   => get_option( 'aichat_gemini_api_key', '' ),
		'mistral'  => get_option( 'aichat_mistral_api_key', '' ),
		'llama'    => get_option( 'aichat_llama_api_key', '' ),
		'deepseek' => get_option( 'aichat_deepseek_api_key', '' ),
	);

	$model_map = array(
		'claude'   => get_option( 'aichat_claude_model',   'claude-sonnet-4-6' ),
		'openai'   => get_option( 'aichat_openai_model',   'gpt-4o' ),
		'gemini'   => get_option( 'aichat_gemini_model',   'gemini-2.0-flash' ),
		'mistral'  => get_option( 'aichat_mistral_model',  'mistral-large-latest' ),
		'llama'    => get_option( 'aichat_llama_model',    'llama-3.3-70b-versatile' ),
		'deepseek' => get_option( 'aichat_deepseek_model', 'deepseek-chat' ),
	);

	return array(
		'provider'  => $provider,
		'api_key'   => isset( $key_map[ $provider ] ) ? $key_map[ $provider ] : '',
		'model'     => isset( $model_map[ $provider ] ) ? $model_map[ $provider ] : '',
		'key_map'   => $key_map,
		'model_map' => $model_map,
	);
}

/**
 * Resolve the bot avatar image URL: a custom uploaded icon takes priority;
 * otherwise default to the active AI provider's real logo (matching the
 * provider picker in Settings) instead of a generic emoji.
 *
 * @return string Image URL, or '' if neither a custom icon nor a matching
 *                provider logo is available (JS then falls back to the
 *                configurable emoji).
 */
function aichat_get_bot_avatar_url() {
	$icon_id = (int) get_option( 'aichat_bot_icon_id', 0 );
	if ( $icon_id && function_exists( 'wp_get_attachment_image_url' ) ) {
		$custom_url = wp_get_attachment_image_url( $icon_id, 'thumbnail' );
		if ( $custom_url ) {
			return $custom_url;
		}
	}

	$provider   = aichat_get_active_provider()['provider'];
	$logo_files = array(
		'claude'   => 'claude.png',
		'openai'   => 'openai.png',
		'gemini'   => 'gemini.png',
		'mistral'  => 'mistral.png',
		'llama'    => 'llama.png',
		'deepseek' => 'deepseek.png',
	);

	if ( isset( $logo_files[ $provider ] ) ) {
		return AICHAT_PLUGIN_URL . 'assets/images/providers/' . $logo_files[ $provider ];
	}

	return '';
}

/**
 * Force-replace any outdated/invalid Claude model IDs with the current default.
 * Called from migrations and from aichat_get_active_provider() so it's always
 * current no matter which code path runs first on a given request.
 */
function aichat_normalize_claude_model() {
	$outdated = array(
		'claude-sonnet-4-20250514',   // never existed
		'claude-3-7-sonnet-20250219', // deprecated
		'claude-3-5-sonnet-20241022', // deprecated
		'claude-3-5-haiku-20241022',  // deprecated
		'claude-3-opus-20240229',     // deprecated
		'claude-3-haiku-20240307',    // deprecated
		'',
	);
	$current = get_option( 'aichat_claude_model', '' );
	if ( in_array( $current, $outdated, true ) ) {
		update_option( 'aichat_claude_model', 'claude-sonnet-4-6' );
	}
}

/**
 * Simple transient-based rate limiter for public-facing AJAX endpoints.
 *
 * @param  string $bucket    Unique key for the thing being limited (e.g. 'proxy_llm').
 * @param  int    $max_hits  Max requests allowed within the window.
 * @param  int    $window    Window length in seconds.
 * @return bool   True if the request is within limits (and has been counted), false if blocked.
 */
function aichat_rate_limit_check( $bucket, $max_hits = 20, $window = 300 ) {
	$ip  = '';
	if ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
		$ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
	}
	$key  = 'aichat_rl_' . md5( $bucket . '|' . $ip );
	$hits = get_transient( $key );

	if ( false === $hits ) {
		set_transient( $key, 1, $window );
		return true;
	}

	if ( (int) $hits >= $max_hits ) {
		return false;
	}

	set_transient( $key, (int) $hits + 1, $window );
	return true;
}

// ─── Admin Notice: missing API key ────────────────────────────────────────────

add_action( 'admin_notices', 'aichat_admin_notice_missing_key' );

function aichat_admin_notice_missing_key() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$active      = aichat_get_active_provider();
	$provider    = $active['provider'];
	$active_key  = $active['api_key'];

	if ( ! empty( $active_key ) ) return; // Key is set — no notice needed.

	$settings_url = admin_url( 'admin.php?page=ai-site-chat-panel' );
	$provider_label = ucfirst( $provider );
	?>
	<div class="notice" style="
		border-left: 5px solid #dc2626;
		background: #fff5f5;
		padding: 16px 20px;
		display: flex;
		align-items: center;
		gap: 14px;
		font-size: 14px;
	">
		<span style="font-size: 28px; line-height: 1;">🚨</span>
		<div>
			<strong style="color: #dc2626; font-size: 15px;">
				Orate — API Key Missing!
			</strong>
			<p style="margin: 4px 0 0; color: #7f1d1d;">
				The chat widget is <strong>not working</strong> because no API key is set for the active provider
				(<strong><?php echo esc_html( $provider_label ); ?></strong>).
				<a href="<?php echo esc_url( $settings_url ); ?>" style="color: #dc2626; font-weight: 600; text-decoration: underline;">
					Go to Settings &rarr;
				</a>
			</p>
		</div>
	</div>
	<?php
}

// ─── Admin Notice: handoff email delivery failure ─────────────────────────────

add_action( 'admin_notices', 'aichat_admin_notice_mail_failure' );

function aichat_admin_notice_mail_failure() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$error = get_option( 'aichat_last_mail_error', null );
	if ( empty( $error ) || empty( $error['time'] ) ) {
		return;
	}

	// Don't nag forever over a stale, possibly-long-since-fixed failure.
	$age_days = ( time() - strtotime( $error['time'] ) ) / DAY_IN_SECONDS;
	if ( $age_days > 30 ) {
		return;
	}

	$settings_url = admin_url( 'admin.php?page=ai-site-chat-panel' );
	?>
	<div class="notice" style="
		border-left: 5px solid #dc2626;
		background: #fff5f5;
		padding: 16px 20px;
		display: flex;
		align-items: center;
		gap: 14px;
		font-size: 14px;
	">
		<span style="font-size: 28px; line-height: 1;">✉️</span>
		<div>
			<strong style="color: #dc2626; font-size: 15px;">
				Orate — Handoff Email Failed to Send
			</strong>
			<p style="margin: 4px 0 0; color: #7f1d1d;">
				The last "talk to a human" notification email could not be delivered
				(<?php echo esc_html( $error['time'] ); ?>): <em><?php echo esc_html( $error['message'] ); ?></em>.
				This usually means your host's mail sending isn't configured — an SMTP plugin
				(WP Mail SMTP, FluentSMTP, Post SMTP, etc.) fixes this in most cases.
				<a href="<?php echo esc_url( $settings_url ); ?>" style="color: #dc2626; font-weight: 600; text-decoration: underline;">
					Go to Settings &rarr;
				</a>
			</p>
		</div>
	</div>
	<?php
}

// ─── Register Settings ────────────────────────────────────────────────────────

add_action( 'admin_init', 'aichat_register_settings' );

function aichat_register_settings() {
	// Text / key fields.
	$text_fields = array(
		// Identity.
		'aichat_bot_name', 'aichat_bot_emoji', 'aichat_bot_role', 'aichat_bot_tone',
		// Provider selection.
		'aichat_llm_provider',
		// API keys (one per provider).
		'aichat_api_key',          // Claude / Anthropic.
		'aichat_openai_api_key',   // OpenAI.
		'aichat_gemini_api_key',   // Google Gemini.
		'aichat_mistral_api_key',  // Mistral AI.
		'aichat_llama_api_key',    // Llama via Groq.
		'aichat_deepseek_api_key', // DeepSeek.
		// Model per provider.
		'aichat_claude_model',
		'aichat_openai_model',
		'aichat_gemini_model',
		'aichat_mistral_model',
		'aichat_llama_model',
		'aichat_deepseek_model',
	);
	foreach ( $text_fields as $field ) {
		register_setting( 'aichat_settings_group', $field, array(
			'sanitize_callback' => 'sanitize_text_field',
		) );
	}

	register_setting( 'aichat_settings_group', 'aichat_primary_color', array(
		'sanitize_callback' => 'sanitize_hex_color',
	) );
	register_setting( 'aichat_settings_group', 'aichat_welcome_message', array(
		'sanitize_callback' => 'sanitize_textarea_field',
	) );
	register_setting( 'aichat_settings_group', 'aichat_bot_personality', array(
		'sanitize_callback' => 'sanitize_textarea_field',
	) );
	register_setting( 'aichat_settings_group', 'aichat_custom_instructions', array(
		'sanitize_callback' => 'sanitize_textarea_field',
	) );
	register_setting( 'aichat_settings_group', 'aichat_lead_capture_mode', array(
		'sanitize_callback' => function( $val ) {
			return in_array( $val, array( 'gate', 'conversational', 'inline-form' ), true ) ? $val : 'conversational';
		},
	) );
	register_setting( 'aichat_settings_group', 'aichat_widget_theme', array(
		'sanitize_callback' => function( $val ) {
			return in_array( $val, array( 'light', 'dark' ), true ) ? $val : 'light';
		},
	) );
	register_setting( 'aichat_settings_group', 'aichat_hide_branding', array(
		'sanitize_callback' => function( $val ) { return $val ? '1' : ''; },
	) );
	register_setting( 'aichat_settings_group', 'aichat_handoff_email', array(
		'sanitize_callback' => function( $val ) {
			return implode( ', ', aichat_parse_email_list( $val ) );
		},
	) );
	register_setting( 'aichat_settings_group', 'aichat_human_handoff', array(
		'sanitize_callback' => function( $val ) { return $val ? '1' : ''; },
	) );
	register_setting( 'aichat_settings_group', 'aichat_voice_mode', array(
		'sanitize_callback' => function( $val ) { return $val ? '1' : ''; },
	) );
	register_setting( 'aichat_settings_group', 'aichat_bot_icon_id', array(
		'sanitize_callback' => 'absint',
	) );
	register_setting( 'aichat_settings_group', 'aichat_proactive_enabled', array(
		'sanitize_callback' => function( $val ) { return $val ? '1' : ''; },
	) );
	register_setting( 'aichat_settings_group', 'aichat_streaming_enabled', array(
		'sanitize_callback' => function( $val ) { return $val ? '1' : ''; },
	) );
	// The repeatable rule list posts as aichat_proactive_rules[<i>][<field>];
	// store it as one JSON blob so it stays a single option row.
	register_setting( 'aichat_settings_group', 'aichat_proactive_rules', array(
		'sanitize_callback' => function( $val ) {
			return wp_json_encode( aichat_sanitize_proactive_rules( $val ) );
		},
	) );
}

// ─── Admin Assets ─────────────────────────────────────────────────────────────

add_action( 'admin_enqueue_scripts', 'aichat_enqueue_admin_assets' );

function aichat_enqueue_admin_assets( $hook ) {
	// Analytics: the chat-replay modal renders real widget bubbles, so it
	// borrows the widget's own stylesheet. Only the bubble/message classes are
	// used — nothing that positions the floating launcher or panel.
	$page = isset( $_GET['page'] ) ? sanitize_key( $_GET['page'] ) : '';
	if ( 'ai-site-chat-analytics' === $page ) {
		wp_enqueue_style(
			'aichat-widget',
			AICHAT_PLUGIN_URL . 'assets/css/chat-widget.css',
			array(),
			AICHAT_VERSION
		);
	}

	$kb_pages = array(
		'ai-site-chat_page_ai-site-chat-kb',
		'toplevel_page_ai-site-chat-panel',
	);
	$is_kb    = in_array( $hook, $kb_pages, true ) ||
	            ( isset( $_GET['page'] ) && in_array( sanitize_key( $_GET['page'] ), array( 'ai-site-chat-kb', 'ai-site-chat-panel' ), true ) );

	if ( ! $is_kb ) {
		return;
	}

	wp_enqueue_style( 'wp-color-picker' );
	wp_enqueue_script( 'wp-color-picker' );
	wp_enqueue_media();

	wp_enqueue_script(
		'aichat-admin-kb',
		AICHAT_PLUGIN_URL . 'assets/js/admin-kb.js',
		array( 'jquery' ),
		AICHAT_VERSION,
		true
	);

	wp_localize_script( 'aichat-admin-kb', 'aichatAdmin', array(
		'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
		'uploadNonce'   => wp_create_nonce( 'aichat_upload_nonce' ),
		'deleteNonce'   => wp_create_nonce( 'aichat_delete_file_nonce' ),
		'urlNonce'      => wp_create_nonce( 'aichat_kb_url_nonce' ),
		'trainNonce'    => wp_create_nonce( 'aichat_train_nonce' ),
		'confirmDelete' => __( 'Delete this file from the knowledge base?', 'orate-agency' ),
		'confirmUrl'    => __( 'Remove this URL from the knowledge base?', 'orate-agency' ),
	) );
}

// ─── Frontend Assets ──────────────────────────────────────────────────────────

add_action( 'wp_enqueue_scripts', 'aichat_enqueue_frontend_assets' );

function aichat_enqueue_frontend_assets() {
	$active  = aichat_get_active_provider();
	$api_key = $active['api_key'];

	if ( empty( $api_key ) ) {
		return;
	}

	wp_enqueue_style(
		'aichat-google-fonts',
		'https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap',
		array(),
		null
	);

	wp_enqueue_style(
		'aichat-widget',
		AICHAT_PLUGIN_URL . 'assets/css/chat-widget.css',
		array( 'aichat-google-fonts' ),
		AICHAT_VERSION
	);

	wp_enqueue_script(
		'aichat-marked',
		'https://cdn.jsdelivr.net/npm/marked@9.1.6/marked.min.js',
		array(),
		'9.1.6',
		true
	);

	// Sanitizes marked.js output before it's inserted via innerHTML, so a
	// crafted bot response (e.g. an image tag with an onerror handler) can't
	// run script on the site.
	wp_enqueue_script(
		'aichat-dompurify',
		'https://cdn.jsdelivr.net/npm/dompurify@3.1.6/dist/purify.min.js',
		array(),
		'3.1.6',
		true
	);

	wp_enqueue_script(
		'aichat-widget',
		AICHAT_PLUGIN_URL . 'assets/js/chat-widget.js',
		array( 'aichat-marked', 'aichat-dompurify' ),
		AICHAT_VERSION,
		true
	);

	wp_localize_script( 'aichat-widget', 'aichatConfig', array(
		'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
		'nonce'          => wp_create_nonce( 'aichat_lead_nonce' ),
		'trackNonce'     => wp_create_nonce( 'aichat_track_nonce' ),
		'handoffNonce'   => wp_create_nonce( 'aichat_handoff_nonce' ),
		'chatNonce'      => wp_create_nonce( 'aichat_chat_nonce' ),
		'configNonce'    => wp_create_nonce( 'aichat_get_widget_config' ),
		// Widget config. Note: no system prompt / knowledge base content here —
		// the server builds the prompt itself in aichat_ajax_proxy_llm() so the
		// knowledge base is never exposed in page source.
		'botName'        => get_option( 'aichat_bot_name', 'AI Assistant' ),
		'botEmoji'       => get_option( 'aichat_bot_emoji', '🤖' ),
		'botIconUrl'     => aichat_get_bot_avatar_url(),
		'primaryColor'   => get_option( 'aichat_primary_color', '#22c55e' ),
		'welcomeMessage'    => get_option( 'aichat_welcome_message', 'Hi! How can I help you today?' ),
		'leadCaptureMode'   => get_option( 'aichat_lead_capture_mode', 'conversational' ),
		'widgetTheme'       => get_option( 'aichat_widget_theme', 'light' ),
		'hideBranding'      => get_option( 'aichat_hide_branding', '' ) === '1',
		'proactiveEnabled'  => get_option( 'aichat_proactive_enabled', '' ) === '1',
		'proactiveRules'    => aichat_get_active_proactive_rules(),
		'streamingEnabled'  => get_option( 'aichat_streaming_enabled', '' ) === '1',
		'siteName'       => get_bloginfo( 'name' ),
		// Use home_url() + REQUEST_URI for a safe, host-agnostic page URL.
		'pageUrl'        => esc_url_raw( home_url( isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '/' ) ),
	) );
}

// ─── AJAX: Fresh widget config ────────────────────────────────────────────────
// Everything baked into the page HTML at render time (nonces AND appearance
// settings — bot name/emoji/icon/colour/welcome message/theme) goes stale on
// cached pages (WP Rocket, Cloudflare APO, a CDN, etc.): a page cached before
// an admin change keeps serving the OLD values in its inline <script> config
// until the cache expires or is purged, so a saved Settings change can look
// like it "didn't work" on the live site even though it saved correctly. The
// widget calls this once on init, BEFORE building its DOM, and uses the fresh
// response instead of (or as well as) whatever the cached page embedded.

add_action( 'wp_ajax_aichat_get_widget_config',        'aichat_ajax_get_widget_config' );
add_action( 'wp_ajax_nopriv_aichat_get_widget_config', 'aichat_ajax_get_widget_config' );

function aichat_ajax_get_widget_config() {
	check_ajax_referer( 'aichat_get_widget_config', 'nonce', false ); // Soft check: an expired nonce is exactly why this is being called.
	wp_send_json_success( array(
		// Fresh nonces.
		'nonce'        => wp_create_nonce( 'aichat_lead_nonce' ),
		'trackNonce'   => wp_create_nonce( 'aichat_track_nonce' ),
		'handoffNonce' => wp_create_nonce( 'aichat_handoff_nonce' ),
		'chatNonce'    => wp_create_nonce( 'aichat_chat_nonce' ),
		// Fresh appearance settings — same fields as the initial localized config.
		'botName'         => get_option( 'aichat_bot_name', 'AI Assistant' ),
		'botEmoji'        => get_option( 'aichat_bot_emoji', '🤖' ),
		'botIconUrl'      => aichat_get_bot_avatar_url(),
		'primaryColor'    => get_option( 'aichat_primary_color', '#22c55e' ),
		'welcomeMessage'  => get_option( 'aichat_welcome_message', 'Hi! How can I help you today?' ),
		'leadCaptureMode' => get_option( 'aichat_lead_capture_mode', 'conversational' ),
		'widgetTheme'     => get_option( 'aichat_widget_theme', 'light' ),
		'hideBranding'    => get_option( 'aichat_hide_branding', '' ) === '1',
		// Proactive rules and the streaming flag are appearance-class settings
		// too: a cached page can be serving a stale copy of both.
		'proactiveEnabled' => get_option( 'aichat_proactive_enabled', '' ) === '1',
		'proactiveRules'   => aichat_get_active_proactive_rules(),
		'streamingEnabled' => get_option( 'aichat_streaming_enabled', '' ) === '1',
	) );
}

// ─── Shortcode ────────────────────────────────────────────────────────────────

add_shortcode( 'ai_site_chat', 'aichat_shortcode_render' );

function aichat_shortcode_render( $atts ) {
	// Only render if the active provider has a key configured.
	$active = aichat_get_active_provider();
	if ( empty( $active['api_key'] ) ) {
		return '';
	}

	// Force enqueue assets (in case they were skipped).
	aichat_enqueue_frontend_assets();

	$bot_name = esc_attr( get_option( 'aichat_bot_name', 'AI Assistant' ) );
	return '<div id="aichat-inline-root" class="aichat-inline-embed" aria-label="' . $bot_name . ' Chat" role="complementary"></div>';
}

// ─── AJAX: LLM Proxy ─────────────────────────────────────────────────────────

add_action( 'wp_ajax_aichat_proxy_llm',        'aichat_ajax_proxy_llm' );
add_action( 'wp_ajax_nopriv_aichat_proxy_llm', 'aichat_ajax_proxy_llm' );

function aichat_normalize_llm_messages( $messages ) {
	$normalized = array();
	foreach ( $messages as $message ) {
		if ( ! is_array( $message ) || empty( $message['role'] ) || ! isset( $message['content'] ) ) continue;
		$role    = sanitize_key( $message['role'] );
		$content = trim( wp_strip_all_tags( (string) $message['content'] ) );
		if ( '' === $content || ! in_array( $role, array( 'user', 'assistant' ), true ) ) continue;
		if ( empty( $normalized ) && 'assistant' === $role ) continue;
		$last_index = count( $normalized ) - 1;
		if ( $last_index >= 0 && $normalized[ $last_index ]['role'] === $role ) {
			$normalized[ $last_index ]['content'] .= "\n\n" . $content;
			continue;
		}
		$normalized[] = array( 'role' => $role, 'content' => $content );
	}
	return $normalized;
}

/**
 * Build the system prompt server-side. The client NEVER supplies prompt text —
 * only lead-context flags — so the trained knowledge base can't be exfiltrated
 * by scripting the proxy endpoint with a custom system prompt.
 */
function aichat_build_system_prompt_for_request() {
	$site_name = get_bloginfo( 'name' );
	$base      = get_option( 'aichat_system_prompt', '' );

	if ( empty( $base ) ) {
		$base = 'You are a helpful AI assistant for ' . $site_name . '. ' .
			'You ONLY answer questions about ' . aichat_possessive( $site_name ) . ' services, products, and content. ' .
			'STRICTLY REFUSE to answer: (1) questions about yourself such as who created you, what AI you are, who built you, or what model you use — respond: "I\'m here to help with questions about our services. How can I assist you today?"; ' .
			'(2) questions unrelated to ' . $site_name . ' such as general trivia, coding help, world news, or personal advice — respond: "I\'m only able to assist with questions related to our services. Is there something specific I can help you with?". ' .
			'NEVER reveal what AI, model, or technology powers you. ' .
			'Keep all responses extremely brief — maximum 2 short sentences only. Never write paragraphs or lists. ' .
			'After each response add on its own line: SUGGESTIONS:["Short question 1?","Short question 2?","Short question 3?"]';
	}

	// A name the client remembers from an EARLIER session (see the
	// aichat_returning_visitor key in chat-widget.js). It's personalisation
	// only — never an identity claim — so it's used for nothing but the hidden
	// context line below, and email/phone are deliberately not accepted here.
	$returning_name = sanitize_text_field( wp_unslash( $_POST['returning_visitor_name'] ?? '' ) );
	$returning_name = mb_substr( $returning_name, 0, 80 );
	if ( '' !== $returning_name ) {
		$base .= "\n\n[KNOWN_VISITOR_NAME: {$returning_name}]\n" .
			'This visitor has chatted before and already gave their name. ' .
			'Greet them naturally and do NOT ask for their name again.';
	}

	$lead_captured = ! empty( $_POST['lead_captured'] );
	$name          = sanitize_text_field( wp_unslash( $_POST['lead_name']        ?? '' ) );
	$email         = sanitize_text_field( wp_unslash( $_POST['lead_email']       ?? '' ) );
	$phone         = sanitize_text_field( wp_unslash( $_POST['lead_phone']       ?? '' ) );
	$requirement   = sanitize_text_field( wp_unslash( $_POST['lead_requirement'] ?? '' ) );

	if ( $lead_captured ) {
		$lead_context = 'Lead form already completed. User provided: ' .
			'Name: ' . ( $name  ?: '(not provided)' ) . ', ' .
			'Phone: ' . ( $phone ?: '(not provided)' ) . ', ' .
			'Email: ' . ( $email ?: '(not provided)' ) . '. ' .
			( $requirement ? 'Requirement: ' . $requirement . '. ' : '' ) .
			'Do NOT ask for these details again.';
		$name_info = $name ? "Visitor name: {$name}. Use their name naturally in replies.\n" : '';

		$override =
			"CRITICAL OVERRIDE - READ THIS FIRST:\n" . $lead_context . "\n" .
			"The visitor has already submitted their contact details via a form.\n" . $name_info .
			"You MUST NOT ask for name, email, phone, or any contact information - not even once.\n" .
			"Ignore any instructions below that say to collect contact details.\n" .
			"Just answer their questions directly.\n\n";
		$reminder =
			"\n\n===FINAL REMINDER===\n" . $lead_context . "\n" .
			"The visitor has ALREADY provided their contact details (name, email, phone).\n" .
			"DO NOT ask for any contact information under any circumstances.\n" .
			"Just answer their questions directly and helpfully.";

		return $override . $base . $reminder;
	}

	if ( $name ) {
		$base .= "\n\nThe visitor's name is {$name}. Address them by name. Do NOT ask for their name again.";
	}

	return $base;
}

function aichat_ajax_proxy_llm() {
	check_ajax_referer( 'aichat_chat_nonce', 'nonce' );

	if ( ! aichat_rate_limit_check( 'proxy_llm', 30, 300 ) ) {
		wp_send_json_error( 'Too many requests. Please wait a moment and try again.' );
		return;
	}

	$active   = aichat_get_active_provider();
	$provider = $active['provider'];
	$api_key  = $active['api_key'];
	$model    = $active['model'];

	if ( empty( $api_key ) ) { wp_send_json_error( 'No API key configured.' ); return; }

	$messages = json_decode( isset( $_POST['messages'] ) ? wp_unslash( $_POST['messages'] ) : '[]', true );
	if ( ! is_array( $messages ) ) { wp_send_json_error( 'Invalid messages.' ); return; }

	$messages = aichat_normalize_llm_messages( $messages );
	if ( empty( $messages ) ) { wp_send_json_error( 'No user message provided.' ); return; }

	// Same caps for every provider — a client can no longer inflate cost by
	// sending a huge system prompt (that's server-built now) or endless history.
	if ( count( $messages ) > 6 ) {
		$messages = array_slice( $messages, -6 );
	}

	$system = aichat_build_system_prompt_for_request();
	if ( mb_strlen( $system ) > 20000 ) {
		$system = mb_substr( $system, 0, 20000 );
	}

	// Streaming only changes how the body is delivered — every check above
	// (nonce, rate limit, history cap, prompt truncation) has already run and
	// applies identically to both paths. Falls through to the buffered path
	// when the flag is off, the client didn't ask, or curl isn't available.
	$wants_stream = ! empty( $_POST['stream'] );
	if ( $wants_stream && '1' === get_option( 'aichat_streaming_enabled', '' ) && aichat_streaming_supported() ) {
		aichat_stream_llm_response( $provider, $api_key, $model, $messages, $system );
		return; // Not reached — the streamer exits.
	}

	switch ( $provider ) {
		case 'claude':  $result = aichat_llm_claude( $api_key, $model, $messages, $system ); break;
		case 'gemini':  $result = aichat_llm_gemini( $api_key, $model, $messages, $system ); break;
		default:
			$ep = array( 'openai' => 'https://api.openai.com/v1/chat/completions', 'mistral' => 'https://api.mistral.ai/v1/chat/completions', 'llama' => 'https://api.groq.com/openai/v1/chat/completions', 'deepseek' => 'https://api.deepseek.com/v1/chat/completions' );
			$result = aichat_llm_openai_compat( $api_key, $model, $messages, $system, isset( $ep[ $provider ] ) ? $ep[ $provider ] : 'https://api.openai.com/v1/chat/completions' );
	}
	if ( is_wp_error( $result ) ) { wp_send_json_error( $result->get_error_message() ); return; }
	wp_send_json_success( $result );
}
function aichat_llm_claude( $api_key, $model, $messages, $system ) {
	$r = wp_remote_post( 'https://api.anthropic.com/v1/messages', array( 'timeout' => 30, 'headers' => array( 'Content-Type' => 'application/json', 'x-api-key' => $api_key, 'anthropic-version' => '2023-06-01' ), 'body' => wp_json_encode( array( 'model' => $model ?: 'claude-sonnet-4-6', 'max_tokens' => 1024, 'system' => $system, 'messages' => $messages ) ) ) );
	if ( is_wp_error( $r ) ) return $r;
	$code = wp_remote_retrieve_response_code( $r ); $body = json_decode( wp_remote_retrieve_body( $r ), true );
	if ( 200 !== $code ) return new WP_Error( 'api_error', isset( $body['error']['message'] ) ? $body['error']['message'] : 'Claude error ' . $code );
	return empty( $body['content'][0]['text'] ) ? new WP_Error( 'api_error', 'Unexpected Claude response.' ) : $body['content'][0]['text'];
}
function aichat_llm_openai_compat( $api_key, $model, $messages, $system, $endpoint ) {
	$r = wp_remote_post( $endpoint, array( 'timeout' => 30, 'headers' => array( 'Content-Type' => 'application/json', 'Authorization' => 'Bearer ' . $api_key ), 'body' => wp_json_encode( array( 'model' => $model, 'messages' => array_merge( array( array( 'role' => 'system', 'content' => $system ) ), $messages ), 'max_tokens' => 1024 ) ) ) );
	if ( is_wp_error( $r ) ) return $r;
	$code = wp_remote_retrieve_response_code( $r ); $body = json_decode( wp_remote_retrieve_body( $r ), true );
	if ( 200 !== $code ) return new WP_Error( 'api_error', isset( $body['error']['message'] ) ? $body['error']['message'] : 'API error ' . $code );
	return empty( $body['choices'][0]['message']['content'] ) ? new WP_Error( 'api_error', 'Unexpected response.' ) : $body['choices'][0]['message']['content'];
}
function aichat_llm_gemini( $api_key, $model, $messages, $system ) {
	$contents = array(); foreach ( $messages as $m ) { $contents[] = array( 'role' => ( 'assistant' === $m['role'] ) ? 'model' : 'user', 'parts' => array( array( 'text' => $m['content'] ) ) ); }
	$cleaned = array(); foreach ( $contents as $c ) { if ( empty( $cleaned ) || $c['role'] !== end( $cleaned )['role'] ) $cleaned[] = $c; } if ( ! empty( $cleaned ) && 'model' === $cleaned[0]['role'] ) array_shift( $cleaned );
	$r = wp_remote_post( 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode( $model ?: 'gemini-2.0-flash' ) . ':generateContent', array( 'timeout' => 30, 'headers' => array( 'Content-Type' => 'application/json', 'x-goog-api-key' => $api_key ), 'body' => wp_json_encode( array( 'system_instruction' => array( 'parts' => array( array( 'text' => $system ) ) ), 'contents' => $cleaned, 'generationConfig' => array( 'maxOutputTokens' => 1024 ) ) ) ) );
	if ( is_wp_error( $r ) ) return $r;
	$code = wp_remote_retrieve_response_code( $r ); $body = json_decode( wp_remote_retrieve_body( $r ), true );
	if ( 200 !== $code ) return new WP_Error( 'api_error', isset( $body['error']['message'] ) ? $body['error']['message'] : 'Gemini error ' . $code );
	return empty( $body['candidates'][0]['content']['parts'][0]['text'] ) ? new WP_Error( 'api_error', 'Unexpected Gemini response.' ) : $body['candidates'][0]['content']['parts'][0]['text'];
}

// ─── Streaming (SSE) ──────────────────────────────────────────────────────────
// Feature-flagged behind `aichat_streaming_enabled`. wp_remote_post() buffers
// the whole response before returning, so streaming uses a raw curl handle with
// CURLOPT_WRITEFUNCTION and flushes after every chunk.
//
// The six providers speak three different SSE dialects, and two of them name
// the model in their frames. Rather than proxying provider frames through to
// the browser, each dialect is decoded server-side and re-emitted as one
// neutral event stream — `data: {"t":"<text>"}` per token, `data: [DONE]` at
// the end — so streaming keeps the same "never reveal which model powers this"
// property the buffered path has.

/**
 * Can this install stream at all? Needs curl, and needs to still own its
 * headers (nothing may have been echoed before the stream starts).
 *
 * @return bool
 */
function aichat_streaming_supported() {
	return function_exists( 'curl_init' ) && ! headers_sent();
}

/**
 * Emit the SSE headers and unwind any buffering PHP itself is doing.
 * Nginx/PHP-FPM setups with proxy_buffering on will still buffer at the web
 * server — that's the documented limitation next to the Settings toggle.
 */
function aichat_stream_send_headers() {
	header( 'Content-Type: text/event-stream; charset=utf-8' );
	header( 'Cache-Control: no-cache, no-store, must-revalidate' );
	header( 'Connection: keep-alive' );
	header( 'X-Accel-Buffering: no' ); // Asks nginx not to buffer this response.

	while ( ob_get_level() > 0 ) {
		ob_end_flush();
	}
}

/**
 * Write one normalised SSE frame and push it out immediately.
 *
 * @param string $payload Already-encoded JSON, or the [DONE] sentinel.
 */
function aichat_stream_emit( $payload ) {
	echo 'data: ' . $payload . "\n\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	if ( ob_get_level() > 0 ) {
		ob_flush();
	}
	flush();
}

/**
 * Pull the text delta out of one decoded provider SSE frame.
 *
 * @param  array $frame Decoded JSON frame.
 * @return string Text to append, or '' when the frame carries no text.
 */
function aichat_stream_extract_delta( $frame ) {
	if ( ! is_array( $frame ) ) {
		return '';
	}
	// Anthropic: content_block_delta / text_delta.
	if ( isset( $frame['delta']['text'] ) && is_string( $frame['delta']['text'] ) ) {
		return $frame['delta']['text'];
	}
	// OpenAI-compatible (OpenAI, Mistral, Groq/Llama, DeepSeek).
	if ( isset( $frame['choices'][0]['delta']['content'] ) && is_string( $frame['choices'][0]['delta']['content'] ) ) {
		return $frame['choices'][0]['delta']['content'];
	}
	// Gemini (alt=sse).
	if ( isset( $frame['candidates'][0]['content']['parts'] ) && is_array( $frame['candidates'][0]['content']['parts'] ) ) {
		$text = '';
		foreach ( $frame['candidates'][0]['content']['parts'] as $part ) {
			if ( isset( $part['text'] ) && is_string( $part['text'] ) ) {
				$text .= $part['text'];
			}
		}
		return $text;
	}
	return '';
}

/**
 * Build the outbound streaming request (URL, headers, JSON body) for a provider.
 *
 * @param  string $provider Provider slug.
 * @param  string $api_key  API key.
 * @param  string $model    Model ID.
 * @param  array  $messages Normalised conversation.
 * @param  string $system   Server-built system prompt.
 * @return array  { url, headers, body }
 */
function aichat_stream_build_request( $provider, $api_key, $model, $messages, $system ) {
	if ( 'claude' === $provider ) {
		return array(
			'url'     => 'https://api.anthropic.com/v1/messages',
			'headers' => array(
				'Content-Type: application/json',
				'x-api-key: ' . $api_key,
				'anthropic-version: 2023-06-01',
			),
			'body'    => wp_json_encode( array(
				'model'      => $model ?: 'claude-sonnet-4-6',
				'max_tokens' => 1024,
				'system'     => $system,
				'messages'   => $messages,
				'stream'     => true,
			) ),
		);
	}

	if ( 'gemini' === $provider ) {
		// Same role-mapping and de-duplication the buffered path does.
		$contents = array();
		foreach ( $messages as $m ) {
			$contents[] = array(
				'role'  => ( 'assistant' === $m['role'] ) ? 'model' : 'user',
				'parts' => array( array( 'text' => $m['content'] ) ),
			);
		}
		$cleaned = array();
		foreach ( $contents as $c ) {
			if ( empty( $cleaned ) || $c['role'] !== end( $cleaned )['role'] ) {
				$cleaned[] = $c;
			}
		}
		if ( ! empty( $cleaned ) && 'model' === $cleaned[0]['role'] ) {
			array_shift( $cleaned );
		}

		return array(
			'url'     => 'https://generativelanguage.googleapis.com/v1beta/models/' .
			             rawurlencode( $model ?: 'gemini-2.0-flash' ) . ':streamGenerateContent?alt=sse',
			'headers' => array( 'Content-Type: application/json', 'x-goog-api-key: ' . $api_key ),
			'body'    => wp_json_encode( array(
				'system_instruction' => array( 'parts' => array( array( 'text' => $system ) ) ),
				'contents'           => $cleaned,
				'generationConfig'   => array( 'maxOutputTokens' => 1024 ),
			) ),
		);
	}

	$endpoints = array(
		'openai'   => 'https://api.openai.com/v1/chat/completions',
		'mistral'  => 'https://api.mistral.ai/v1/chat/completions',
		'llama'    => 'https://api.groq.com/openai/v1/chat/completions',
		'deepseek' => 'https://api.deepseek.com/v1/chat/completions',
	);

	return array(
		'url'     => isset( $endpoints[ $provider ] ) ? $endpoints[ $provider ] : $endpoints['openai'],
		'headers' => array( 'Content-Type: application/json', 'Authorization: Bearer ' . $api_key ),
		'body'    => wp_json_encode( array(
			'model'      => $model,
			'messages'   => array_merge( array( array( 'role' => 'system', 'content' => $system ) ), $messages ),
			'max_tokens' => 1024,
			'stream'     => true,
		) ),
	);
}

/**
 * Stream one completion straight through to the browser. Terminates the
 * request itself (like wp_send_json_* does on the buffered path).
 *
 * @param string $provider Provider slug.
 * @param string $api_key  API key.
 * @param string $model    Model ID.
 * @param array  $messages Normalised conversation.
 * @param string $system   Server-built system prompt.
 */
function aichat_stream_llm_response( $provider, $api_key, $model, $messages, $system ) {
	$request = aichat_stream_build_request( $provider, $api_key, $model, $messages, $system );

	aichat_stream_send_headers();

	// Provider chunks split wherever the network happens to break them, so a
	// single SSE line can arrive across two callbacks. Keep a tail buffer and
	// only parse complete lines out of it.
	$tail       = '';
	$emitted    = 0;
	$error_body = '';

	$ch = curl_init( $request['url'] );
	curl_setopt_array( $ch, array(
		CURLOPT_POST           => true,
		CURLOPT_POSTFIELDS     => $request['body'],
		CURLOPT_HTTPHEADER     => $request['headers'],
		CURLOPT_RETURNTRANSFER => false,
		CURLOPT_TIMEOUT        => 120,
		CURLOPT_CONNECTTIMEOUT => 15,
		CURLOPT_WRITEFUNCTION  => function ( $handle, $chunk ) use ( &$tail, &$emitted, &$error_body ) {
			$written = strlen( $chunk );

			// A non-200 response body is an error payload, not an event
			// stream — collect it and report it once the transfer ends.
			if ( 200 !== (int) curl_getinfo( $handle, CURLINFO_RESPONSE_CODE ) ) {
				$error_body .= $chunk;
				return $written;
			}

			$tail  .= $chunk;
			$lines  = explode( "\n", $tail );
			$tail   = array_pop( $lines ); // Possibly-incomplete final line.

			foreach ( $lines as $line ) {
				$line = trim( $line );
				if ( '' === $line || 0 !== strpos( $line, 'data:' ) ) {
					continue; // Comments, `event:` lines, and keep-alives.
				}
				$data = trim( substr( $line, strlen( 'data:' ) ) );
				if ( '' === $data || '[DONE]' === $data ) {
					continue;
				}
				$delta = aichat_stream_extract_delta( json_decode( $data, true ) );
				if ( '' !== $delta ) {
					$emitted++;
					aichat_stream_emit( wp_json_encode( array( 't' => $delta ) ) );
				}
			}

			return $written;
		},
	) );

	curl_exec( $ch );
	$curl_error = curl_error( $ch );
	$http_code  = (int) curl_getinfo( $ch, CURLINFO_RESPONSE_CODE );
	curl_close( $ch );

	if ( 200 !== $http_code || '' !== $curl_error ) {
		$decoded = json_decode( $error_body, true );
		$message = isset( $decoded['error']['message'] )
			? $decoded['error']['message']
			: ( '' !== $curl_error ? $curl_error : 'API error ' . $http_code );
		aichat_stream_emit( wp_json_encode( array( 'error' => $message ) ) );
		aichat_stream_emit( '[DONE]' );
		exit;
	}

	if ( 0 === $emitted ) {
		// A clean 200 that produced no text — let the client fall back rather
		// than settle for an empty bubble.
		aichat_stream_emit( wp_json_encode( array( 'error' => 'Empty response from provider.' ) ) );
	}

	aichat_stream_emit( '[DONE]' );
	exit;
}

// ─── AJAX: Save Lead ──────────────────────────────────────────────────────────

add_action( 'wp_ajax_aichat_save_lead',        'aichat_ajax_save_lead' );
add_action( 'wp_ajax_nopriv_aichat_save_lead', 'aichat_ajax_save_lead' );

function aichat_ajax_save_lead() {
	check_ajax_referer( 'aichat_lead_nonce', 'nonce' );

	if ( ! aichat_rate_limit_check( 'save_lead', 20, 300 ) ) {
		wp_send_json_error( array( 'message' => 'Too many requests. Please wait a moment and try again.' ) );
		return;
	}

	$session_id  = sanitize_text_field( wp_unslash( $_POST['session_id']    ?? '' ) );
	$name        = sanitize_text_field( wp_unslash( $_POST['name']          ?? '' ) );
	$email       = sanitize_email( wp_unslash( $_POST['email']              ?? '' ) );
	$phone       = sanitize_text_field( wp_unslash( $_POST['phone']         ?? '' ) );
	$requirement = sanitize_textarea_field( wp_unslash( $_POST['requirement'] ?? '' ) );
	$transcript  = wp_kses_post( wp_unslash( $_POST['transcript']           ?? '' ) );

	// Need at least one piece of contact info.
	if ( empty( $name ) && empty( $email ) && empty( $phone ) ) {
		wp_send_json_error( array( 'message' => 'No contact information provided.' ) );
		return;
	}

	global $wpdb;
	$table = $wpdb->prefix . AICHAT_DB_TABLE;
	$data  = array(
		'name'        => $name,
		'email'       => $email,
		'phone'       => $phone,
		'requirement' => $requirement,
		'transcript'  => $transcript,
		'updated_at'  => current_time( 'mysql' ),
	);
	$formats = array( '%s', '%s', '%s', '%s', '%s', '%s' );

	// One conversation = one row: upsert on session_id instead of inserting
	// again on every save (gate submit, chat close, and beforeunload beacon
	// used to each create their own row).
	$existing_id = 0;
	if ( ! empty( $session_id ) ) {
		$session_id  = substr( $session_id, 0, 64 );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$existing_id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE session_id = %s", $session_id ) );
	}

	if ( $existing_id ) {
		$updated = $wpdb->update( $table, $data, array( 'id' => $existing_id ), $formats, array( '%d' ) );
		if ( false === $updated ) {
			wp_send_json_error( array( 'message' => 'Database error.' ) );
			return;
		}
		wp_send_json_success( array( 'id' => $existing_id ) );
		return;
	}

	$data['session_id'] = $session_id;
	$formats[]          = '%s';

	$inserted = $wpdb->insert( $table, $data, $formats );

	if ( false === $inserted ) {
		wp_send_json_error( array( 'message' => 'Database error.' ) );
		return;
	}

	wp_send_json_success( array( 'id' => $wpdb->insert_id ) );
}

// ─── AJAX: Track Message (Analytics) ─────────────────────────────────────────

/**
 * Normalise the analytics attribution string. Accepts 'manual' or
 * 'proactive:<trigger_type>' for a known trigger type; anything else is
 * recorded as plain 'manual' so a scripted POST can't write arbitrary text
 * into a column the admin UI renders.
 *
 * @param  string $raw Client-supplied value.
 * @return string
 */
function aichat_sanitize_triggered_by( $raw ) {
	$raw = sanitize_text_field( (string) $raw );
	if ( '' === $raw || 'manual' === $raw ) {
		return 'manual';
	}
	if ( 0 === strpos( $raw, 'proactive:' ) ) {
		$type = sanitize_key( substr( $raw, strlen( 'proactive:' ) ) );
		if ( isset( aichat_proactive_trigger_types()[ $type ] ) ) {
			return 'proactive:' . $type;
		}
	}
	return 'manual';
}

add_action( 'wp_ajax_aichat_track_message',        'aichat_ajax_track_message' );
add_action( 'wp_ajax_nopriv_aichat_track_message', 'aichat_ajax_track_message' );

function aichat_ajax_track_message() {
	check_ajax_referer( 'aichat_track_nonce', 'nonce' );

	if ( ! aichat_rate_limit_check( 'track_message', 40, 300 ) ) {
		wp_send_json_error();
		return;
	}

	$session_id    = sanitize_text_field( wp_unslash( $_POST['session_id'] ?? '' ) );
	$question      = sanitize_textarea_field( wp_unslash( $_POST['question'] ?? '' ) );
	$answer        = sanitize_textarea_field( wp_unslash( $_POST['answer'] ?? '' ) );
	$is_unanswered = absint( $_POST['is_unanswered'] ?? 0 ) ? 1 : 0;
	$page_url      = esc_url_raw( wp_unslash( $_POST['page_url'] ?? '' ) );
	$triggered_by  = aichat_sanitize_triggered_by( wp_unslash( $_POST['triggered_by'] ?? '' ) );

	if ( empty( $session_id ) || empty( $question ) ) {
		wp_send_json_error();
		return;
	}

	global $wpdb;
	$table      = $wpdb->prefix . AICHAT_ANALYTICS_TABLE;
	$session_id = substr( $session_id, 0, 64 );

	// created_at alone can't order a conversation — two messages can land in
	// the same second — so each row carries its position within the session.
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
	$sequence = 1 + (int) $wpdb->get_var( $wpdb->prepare(
		"SELECT COALESCE(MAX(sequence), 0) FROM {$table} WHERE session_id = %s",
		$session_id
	) );

	$wpdb->insert(
		$table,
		array(
			'session_id'    => $session_id,
			'user_question' => substr( $question, 0, 2000 ),
			'bot_answer'    => substr( $answer, 0, 5000 ),
			'is_unanswered' => $is_unanswered,
			'page_url'      => substr( $page_url, 0, 500 ),
			'triggered_by'  => $triggered_by,
			'sequence'      => $sequence,
		),
		array( '%s', '%s', '%s', '%d', '%s', '%s', '%d' )
	);

	wp_send_json_success();
}

// ─── AJAX: Session Transcript (Visual Chat Replay) ───────────────────────────
// Backs the "View Conversation" modal in Analytics. The list view stays light
// — session_id + counts only — and the full transcript is fetched here, once,
// when a modal actually opens.

define( 'AICHAT_TRANSCRIPT_LIMIT', 200 );

add_action( 'wp_ajax_aichat_get_session_transcript', 'aichat_ajax_get_session_transcript' );

function aichat_ajax_get_session_transcript() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => 'Unauthorized' ), 403 );
		return;
	}
	check_ajax_referer( 'aichat_transcript_nonce', 'nonce' );

	if ( ! aichat_rate_limit_check( 'get_transcript', 60, 300 ) ) {
		wp_send_json_error( array( 'message' => 'Too many requests. Please wait a moment and try again.' ) );
		return;
	}

	$session_id = substr( sanitize_text_field( wp_unslash( $_POST['session_id'] ?? '' ) ), 0, 64 );
	if ( empty( $session_id ) ) {
		wp_send_json_error( array( 'message' => 'Missing session.' ) );
		return;
	}

	$rows = aichat_get_session_rows( $session_id, AICHAT_TRANSCRIPT_LIMIT );
	if ( empty( $rows ) ) {
		wp_send_json_error( array( 'message' => 'No messages found for this session.' ) );
		return;
	}

	// The whole session's row count, so the modal can say when it is showing
	// only the most recent slice.
	global $wpdb;
	$at = $wpdb->prefix . AICHAT_ANALYTICS_TABLE;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
	$total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$at} WHERE session_id = %s", $session_id ) );

	$messages   = array();
	$prev_stamp = null;

	foreach ( $rows as $row ) {
		$stamp = strtotime( $row['created_at'] );
		$gap   = ( null === $prev_stamp ) ? '' : aichat_humanize_gap( $stamp - $prev_stamp );

		$messages[] = array(
			'role'        => 'user',
			'text'        => $row['user_question'],
			'time'        => mysql2date( get_option( 'time_format', 'g:i a' ), $row['created_at'] ),
			'gap'         => $gap,
			'unanswered'  => false,
		);
		$messages[] = array(
			'role'        => 'bot',
			'text'        => $row['bot_answer'],
			'time'        => mysql2date( get_option( 'time_format', 'g:i a' ), $row['created_at'] ),
			'gap'         => '',
			'unanswered'  => ( 1 === (int) $row['is_unanswered'] ),
		);

		$prev_stamp = $stamp;
	}

	wp_send_json_success( array(
		'sessionId'   => $session_id,
		'messages'    => $messages,
		'total'       => $total,
		'shown'       => count( $rows ),
		'truncated'   => $total > count( $rows ),
		'startedAt'   => mysql2date( get_option( 'date_format', 'M j, Y' ) . ' ' . get_option( 'time_format', 'g:i a' ), $rows[0]['created_at'] ),
		'triggeredBy' => aichat_describe_trigger( $rows[0]['triggered_by'] ),
		'lead'        => aichat_get_session_lead( $session_id ),
		'exportUrl'   => wp_nonce_url(
			admin_url( 'admin-post.php?action=aichat_export_transcript_csv&session_id=' . rawurlencode( $session_id ) ),
			'aichat_export_transcript_' . $session_id
		),
	) );
}

/**
 * Ordered rows for one session, capped at the most recent $limit.
 *
 * @param  string $session_id Session to load.
 * @param  int    $limit      Max rows.
 * @return array  Rows in chronological order.
 */
function aichat_get_session_rows( $session_id, $limit ) {
	global $wpdb;
	$at = $wpdb->prefix . AICHAT_ANALYTICS_TABLE;

	// Take the most recent $limit rows, then flip them back into reading order.
	// `sequence` is the real ordering key (created_at ties within a second);
	// rows written before DB version 3 all carry sequence 0, so created_at and
	// id keep those ordered sensibly too.
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
	$rows = $wpdb->get_results( $wpdb->prepare(
		"SELECT user_question, bot_answer, is_unanswered, triggered_by, created_at
		 FROM {$at}
		 WHERE session_id = %s
		 ORDER BY sequence DESC, created_at DESC, id DESC
		 LIMIT %d",
		$session_id,
		$limit
	), ARRAY_A );

	return array_reverse( (array) $rows );
}

/**
 * The lead row captured during this session, if there is one. Returned to the
 * admin modal only — this is behind a manage_options check.
 *
 * @param  string $session_id Session to look up.
 * @return array|null
 */
function aichat_get_session_lead( $session_id ) {
	global $wpdb;
	$lt = $wpdb->prefix . AICHAT_DB_TABLE;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
	$lead = $wpdb->get_row( $wpdb->prepare(
		"SELECT name, email, phone, requirement FROM {$lt} WHERE session_id = %s LIMIT 1",
		$session_id
	), ARRAY_A );

	return $lead ?: null;
}

/**
 * Turn a stored triggered_by value into an admin-facing label, or '' for a
 * conversation the visitor started themselves.
 *
 * @param  string $triggered_by Stored value.
 * @return string
 */
function aichat_describe_trigger( $triggered_by ) {
	$triggered_by = (string) $triggered_by;
	if ( '' === $triggered_by || 0 !== strpos( $triggered_by, 'proactive:' ) ) {
		return '';
	}
	$type  = substr( $triggered_by, strlen( 'proactive:' ) );
	$types = aichat_proactive_trigger_types();

	return isset( $types[ $type ] ) ? $types[ $type ] : '';
}

/**
 * Render a gap between two messages as "2m apart".
 *
 * @param  int $seconds Elapsed seconds.
 * @return string Empty when the gap is too small to be interesting.
 */
function aichat_humanize_gap( $seconds ) {
	$seconds = max( 0, (int) $seconds );
	if ( $seconds < 30 ) {
		return '';
	}
	if ( $seconds < 3600 ) {
		/* translators: %d: number of minutes between two messages. */
		return sprintf( _n( '%d min apart', '%d min apart', (int) round( $seconds / 60 ), 'orate-agency' ), (int) round( $seconds / 60 ) );
	}
	if ( $seconds < 86400 ) {
		/* translators: %d: number of hours between two messages. */
		return sprintf( _n( '%d hr apart', '%d hrs apart', (int) round( $seconds / 3600 ), 'orate-agency' ), (int) round( $seconds / 3600 ) );
	}
	/* translators: %d: number of days between two messages. */
	return sprintf( _n( '%d day apart', '%d days apart', (int) round( $seconds / 86400 ), 'orate-agency' ), (int) round( $seconds / 86400 ) );
}

// ─── Export: Single Session Transcript CSV ───────────────────────────────────

add_action( 'admin_post_aichat_export_transcript_csv', 'aichat_handle_export_transcript_csv' );

function aichat_handle_export_transcript_csv() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Unauthorized' );
	}

	$session_id = substr( sanitize_text_field( wp_unslash( $_GET['session_id'] ?? '' ) ), 0, 64 );
	check_admin_referer( 'aichat_export_transcript_' . $session_id );

	if ( empty( $session_id ) ) {
		wp_die( 'Missing session.' );
	}

	$rows = aichat_get_session_rows( $session_id, AICHAT_TRANSCRIPT_LIMIT );

	$filename = 'transcript-' . $session_id . '-' . gmdate( 'Y-m-d' ) . '.csv';

	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
	header( 'Pragma: no-cache' );
	header( 'Expires: 0' );

	$out = fopen( 'php://output', 'w' );
	fputcsv( $out, array( 'Time', 'Speaker', 'Message', 'Unanswered', 'Started Via' ) );

	foreach ( $rows as $row ) {
		$trigger = aichat_describe_trigger( $row['triggered_by'] );
		fputcsv( $out, array( $row['created_at'], 'Visitor', $row['user_question'], '', $trigger ) );
		fputcsv( $out, array( $row['created_at'], 'Bot', $row['bot_answer'], $row['is_unanswered'] ? 'Yes' : '', '' ) );
	}

	fclose( $out );
	exit;
}

// ─── AJAX: Train Now ──────────────────────────────────────────────────────────

add_action( 'wp_ajax_aichat_train_now', 'aichat_ajax_train_now' );

function aichat_ajax_train_now() {
	check_ajax_referer( 'aichat_train_nonce', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => 'Unauthorized.' ) );
		return;
	}

	$result = aichat_run_scraper();

	if ( is_wp_error( $result ) ) {
		wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		return;
	}

	wp_send_json_success( array(
		'message'      => sprintf( 'Training complete! Indexed %d content items.', intval( $result ) ),
		'last_trained' => get_option( 'aichat_last_trained', '' ),
		'post_count'   => intval( $result ),
	) );
}

// ─── AJAX: Send Test Handoff Email ─────────────────────────────────────────────
// Lets an admin verify mail delivery straight from wp-admin — no SSH/WP-CLI
// needed. Uses whatever address is currently typed in the field (even if
// unsaved yet), so you can test before committing to Save.

add_action( 'wp_ajax_aichat_send_test_email', 'aichat_ajax_send_test_email' );

function aichat_ajax_send_test_email() {
	check_ajax_referer( 'aichat_test_email_nonce', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => 'Unauthorized.' ) );
		return;
	}

	if ( ! aichat_rate_limit_check( 'test_email', 5, 300 ) ) {
		wp_send_json_error( array( 'message' => 'Too many test emails sent. Please wait a few minutes and try again.' ) );
		return;
	}

	$to = aichat_parse_email_list( wp_unslash( $_POST['email'] ?? '' ) );
	if ( empty( $to ) ) {
		wp_send_json_error( array( 'message' => 'Enter at least one valid email address first.' ) );
		return;
	}

	$site_name = get_bloginfo( 'name' );
	$subject   = '[' . $site_name . '] Orate — Test Email';
	$body      = "This is a test email from the Orate Agency plugin on {$site_name}.\n\n" .
		"If you're reading this, handoff notification emails are working correctly — " .
		"real ones will include the visitor's name, phone, email, and full chat transcript.\n\n" .
		'Sent: ' . current_time( 'mysql' );

	$sent = aichat_send_notification_email( $to, $subject, $body );

	if ( ! $sent ) {
		$error = get_option( 'aichat_last_mail_error', null );
		wp_send_json_error( array(
			'message' => 'Failed to send: ' . ( $error['message'] ?? 'Unknown error — check your SMTP/mail configuration.' ),
		) );
		return;
	}

	wp_send_json_success( array( 'message' => 'Test email sent to ' . implode( ', ', $to ) . '! Check the inbox (and spam folder).' ) );
}

// ─── AJAX: Upload KB File ─────────────────────────────────────────────────────

add_action( 'wp_ajax_aichat_upload_kb_file', 'aichat_ajax_upload_kb_file' );

function aichat_ajax_upload_kb_file() {
	check_ajax_referer( 'aichat_upload_nonce', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => 'Unauthorized.' ) );
		return;
	}

	if ( empty( $_FILES['kb_file'] ) ) {
		wp_send_json_error( array( 'message' => 'No file received.' ) );
		return;
	}

	$allowed_types = array( 'txt', 'csv', 'json', 'pdf', 'docx', 'pptx' );
	$file_name     = sanitize_file_name( $_FILES['kb_file']['name'] );
	$ext           = strtolower( pathinfo( $file_name, PATHINFO_EXTENSION ) );

	if ( ! in_array( $ext, $allowed_types, true ) ) {
		wp_send_json_error( array( 'message' => 'File type not allowed. Supported: TXT, CSV, JSON, PDF, DOCX, PPTX.' ) );
		return;
	}

	// Max 10 MB.
	if ( $_FILES['kb_file']['size'] > 10 * 1024 * 1024 ) {
		wp_send_json_error( array( 'message' => 'File size exceeds 10 MB limit.' ) );
		return;
	}

	aichat_ensure_upload_dir();
	$kb_dir = aichat_get_kb_dir();
	$unique = wp_unique_filename( $kb_dir, $file_name );
	$dest       = $kb_dir . $unique;

	if ( ! move_uploaded_file( $_FILES['kb_file']['tmp_name'], $dest ) ) {
		wp_send_json_error( array( 'message' => 'Failed to save file. Check server permissions.' ) );
		return;
	}

	$content = aichat_extract_file_content( $dest, $ext );

	global $wpdb;
	$wpdb->insert(
		$wpdb->prefix . AICHAT_KB_FILES_TABLE,
		array(
			'file_name' => $file_name,
			'file_type' => $ext,
			'file_path' => $dest,
			'file_size' => (int) $_FILES['kb_file']['size'],
			'content'   => $content,
		),
		array( '%s', '%s', '%s', '%d', '%s' )
	);

	wp_send_json_success( array(
		'id'        => $wpdb->insert_id,
		'file_name' => $file_name,
		'file_type' => strtoupper( $ext ),
		'file_size' => aichat_human_filesize( (int) $_FILES['kb_file']['size'] ),
		'message'   => 'File uploaded. Re-train to include it in the AI.',
	) );
}

// ─── AJAX: Delete KB File ─────────────────────────────────────────────────────

add_action( 'wp_ajax_aichat_delete_kb_file', 'aichat_ajax_delete_kb_file' );

function aichat_ajax_delete_kb_file() {
	check_ajax_referer( 'aichat_delete_file_nonce', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => 'Unauthorized.' ) );
		return;
	}

	$file_id = absint( $_POST['file_id'] ?? 0 );
	if ( ! $file_id ) {
		wp_send_json_error( array( 'message' => 'Invalid file ID.' ) );
		return;
	}

	global $wpdb;
	$table = $wpdb->prefix . AICHAT_KB_FILES_TABLE;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $file_id ) );

	if ( ! $row ) {
		wp_send_json_error( array( 'message' => 'File not found.' ) );
		return;
	}

	// Remove physical file.
	if ( file_exists( $row->file_path ) ) {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
		unlink( $row->file_path );
	}

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$wpdb->delete( $table, array( 'id' => $file_id ), array( '%d' ) );

	wp_send_json_success( array( 'message' => 'File deleted.' ) );
}

// ─── AJAX: Save KB URLs ───────────────────────────────────────────────────────

add_action( 'wp_ajax_aichat_save_kb_urls', 'aichat_ajax_save_kb_urls' );

function aichat_ajax_save_kb_urls() {
	check_ajax_referer( 'aichat_kb_url_nonce', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => 'Unauthorized.' ) );
		return;
	}

	$raw_urls = isset( $_POST['urls'] ) ? wp_unslash( $_POST['urls'] ) : '';
	$lines    = array_filter( array_map( 'trim', explode( "\n", $raw_urls ) ) );
	$clean    = array();
	$rejected = array();

	// Only allow URLs from this site's domain (strip www. for comparison).
	$site_host = preg_replace( '/^www\./i', '', strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) );

	foreach ( $lines as $line ) {
		$url = esc_url_raw( $line );
		if ( ! $url ) {
			continue;
		}

		$url_host = preg_replace( '/^www\./i', '', strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) ) );

		if ( $url_host !== $site_host ) {
			$rejected[] = $url;
			continue;
		}

		$clean[] = $url;
	}

	update_option( 'aichat_kb_urls', array_unique( $clean ) );

	if ( ! empty( $rejected ) ) {
		$msg = sprintf(
			/* translators: 1: saved count, 2: rejected count, 3: site domain */
			'%d URL(s) saved. %d URL(s) rejected — only pages from %s are allowed.',
			count( $clean ),
			count( $rejected ),
			$site_host
		);
		wp_send_json_success( array( 'count' => count( $clean ), 'message' => $msg ) );
		return;
	}

	wp_send_json_success( array( 'count' => count( $clean ), 'message' => 'URLs saved. Re-train to apply.' ) );
}

// ─── File Content Extraction ──────────────────────────────────────────────────

function aichat_extract_file_content( $file_path, $ext ) {
	switch ( $ext ) {
		case 'txt':
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			$content = file_get_contents( $file_path );
			return $content !== false ? mb_substr( $content, 0, 100000 ) : '';

		case 'csv':
			return aichat_extract_csv( $file_path );

		case 'json':
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			$raw  = file_get_contents( $file_path );
			$data = json_decode( $raw, true );
			return $data !== null
				? wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE )
				: (string) $raw;

		case 'pdf':
			return aichat_extract_pdf( $file_path );

		case 'docx':
			return aichat_extract_docx( $file_path );

		case 'pptx':
			return aichat_extract_pptx( $file_path );

		default:
			return '';
	}
}

function aichat_extract_csv( $file_path ) {
	$rows   = array();
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
	$handle = fopen( $file_path, 'r' );
	if ( ! $handle ) {
		return '';
	}
	$count = 0;
	while ( ( $row = fgetcsv( $handle ) ) !== false && $count < 2000 ) {
		$rows[] = implode( ' | ', array_map( 'strval', $row ) );
		$count++;
	}
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
	fclose( $handle );
	return implode( "\n", $rows );
}

function aichat_extract_pdf( $file_path ) {
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	$content = file_get_contents( $file_path );
	if ( $content === false ) {
		return '(PDF could not be read)';
	}

	$text = '';
	// Extract readable text strings from PDF text objects.
	if ( preg_match_all( '/BT[\s\S]*?ET/', $content, $bt_matches ) ) {
		foreach ( $bt_matches[0] as $bt ) {
			if ( preg_match_all( '/\(([^)\\\\]*(?:\\\\.[^)\\\\]*)*)\)/', $bt, $str_matches ) ) {
				foreach ( $str_matches[1] as $s ) {
					$s = str_replace( array( '\\n', '\\r', '\\t' ), array( "\n", "\r", "\t" ), $s );
					$s = preg_replace( '/\\\\(.)/', '$1', $s );
					$text .= ' ' . $s;
				}
			}
		}
	}

	$text = preg_replace( '/\s+/', ' ', $text );
	$text = trim( $text );

	return $text ?: '(PDF text could not be extracted — use a text-based PDF or convert to TXT)';
}

function aichat_extract_docx( $file_path ) {
	if ( ! class_exists( 'ZipArchive' ) ) {
		return '(DOCX extraction unavailable — ZipArchive PHP extension is required)';
	}
	$zip = new ZipArchive();
	if ( $zip->open( $file_path ) !== true ) {
		return '(Could not open DOCX file)';
	}
	$xml = $zip->getFromName( 'word/document.xml' );
	$zip->close();

	if ( ! $xml ) {
		return '(No content found in DOCX)';
	}

	// Replace paragraph/run breaks with spaces.
	$xml  = preg_replace( '/<\/w:p>/', "\n", $xml );
	$xml  = preg_replace( '/<\/w:r>/', ' ', $xml );
	$text = wp_strip_all_tags( $xml );
	$text = preg_replace( '/[ \t]+/', ' ', $text );
	$text = preg_replace( '/\n{3,}/', "\n\n", $text );
	return trim( $text );
}

function aichat_extract_pptx( $file_path ) {
	if ( ! class_exists( 'ZipArchive' ) ) {
		return '(PPTX extraction unavailable — ZipArchive PHP extension is required)';
	}
	$zip = new ZipArchive();
	if ( $zip->open( $file_path ) !== true ) {
		return '(Could not open PPTX file)';
	}
	$text = '';
	for ( $i = 0; $i < $zip->numFiles; $i++ ) {
		$name = $zip->getNameIndex( $i );
		if ( preg_match( '#^ppt/slides/slide[0-9]+\.xml$#', $name ) ) {
			$xml  = $zip->getFromIndex( $i );
			$xml  = preg_replace( '/<\/a:p>/', "\n", $xml );
			$xml  = preg_replace( '/<\/a:r>/', ' ', $xml );
			$part = wp_strip_all_tags( $xml );
			$part = preg_replace( '/\s+/', ' ', $part );
			$text .= trim( $part ) . "\n";
		}
	}
	$zip->close();
	return trim( $text ) ?: '(No text content found in PPTX)';
}

function aichat_human_filesize( $bytes ) {
	if ( $bytes >= 1048576 ) {
		return round( $bytes / 1048576, 1 ) . ' MB';
	}
	if ( $bytes >= 1024 ) {
		return round( $bytes / 1024, 1 ) . ' KB';
	}
	return $bytes . ' B';
}

// ─── AJAX: Human Handoff Notification ────────────────────────────────────────

add_action( 'wp_ajax_aichat_handoff_notification',        'aichat_ajax_handoff_notification' );
add_action( 'wp_ajax_nopriv_aichat_handoff_notification', 'aichat_ajax_handoff_notification' );

function aichat_ajax_handoff_notification() {
	check_ajax_referer( 'aichat_handoff_nonce', 'nonce' );

	if ( ! aichat_rate_limit_check( 'handoff', 10, 300 ) ) {
		wp_send_json_error( array( 'message' => 'Too many requests. Please wait a moment and try again.' ) );
		return;
	}

	$session_id = sanitize_text_field( wp_unslash( $_POST['session_id']       ?? '' ) );
	$name       = sanitize_text_field( wp_unslash( $_POST['name']             ?? '' ) );
	$phone      = sanitize_text_field( wp_unslash( $_POST['phone']            ?? '' ) );
	$email      = sanitize_email( wp_unslash( $_POST['email']                 ?? '' ) );
	$question   = sanitize_textarea_field( wp_unslash( $_POST['trigger_question'] ?? '' ) );
	$transcript = wp_kses_post( wp_unslash( $_POST['transcript']              ?? '' ) );

	// Need at least one piece of contact info — otherwise there's nothing to hand off.
	if ( empty( $name ) && empty( $email ) && empty( $phone ) ) {
		wp_send_json_error( array( 'message' => 'No contact information provided.' ) );
		return;
	}

	// Save/update the leads table row for this session.
	global $wpdb;
	$table = $wpdb->prefix . AICHAT_DB_TABLE;
	$data  = array(
		'name'        => $name,
		'email'       => $email,
		'phone'       => $phone,
		'requirement' => 'Human handoff: ' . $question,
		'transcript'  => $transcript,
		'updated_at'  => current_time( 'mysql' ),
	);
	$formats = array( '%s', '%s', '%s', '%s', '%s', '%s' );

	$existing_id = 0;
	if ( ! empty( $session_id ) ) {
		$session_id  = substr( $session_id, 0, 64 );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$existing_id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE session_id = %s", $session_id ) );
	}

	if ( $existing_id ) {
		$wpdb->update( $table, $data, array( 'id' => $existing_id ), $formats, array( '%d' ) );
	} else {
		$data['session_id'] = $session_id;
		$formats[]          = '%s';
		$wpdb->insert( $table, $data, $formats );
	}

	// Send email notification — to every address configured, not just one.
	$notify_emails = aichat_parse_email_list( get_option( 'aichat_handoff_email', '' ) );
	if ( ! empty( $notify_emails ) ) {
		$site_name = get_bloginfo( 'name' );
		$bot_name  = get_option( 'aichat_bot_name', 'AI Assistant' );
		$subject   = '[' . $site_name . '] Human Handoff Request — ' . ( $name ?: 'Visitor' );
		$body      = aichat_build_handoff_email_html( $name, $phone, $email, $question, $transcript, $bot_name );
		// wp_mail() accepts an array of "to" addresses natively — one send,
		// every recipient, rather than looping aichat_send_notification_email()
		// once per address (which would also multiply rate-limit-adjacent SMTP
		// calls for no benefit).
		aichat_send_notification_email( $notify_emails, $subject, $body, true );
	}

	wp_send_json_success();
}

/**
 * Parse a handoff-email field into a clean list of valid addresses. Accepts
 * comma- and/or newline-separated input (the Settings textarea allows either,
 * since people paste lists both ways), sanitises each one, drops anything
 * that isn't a real email, and de-dupes case-insensitively.
 *
 * @param  string $raw Raw field value.
 * @return array  Valid, sanitised email addresses (possibly empty).
 */
/**
 * Colour-code the plain-text transcript by speaker for the HTML handoff
 * email: the bot's name and everything it wrote in red, the visitor's name
 * and everything they wrote in green — matching how the admin reads it at a
 * glance rather than a flat wall of "Name: text" lines.
 *
 * The transcript arrives as one flat string (built client-side in
 * buildTranscript()): messages are "Sender: content" pairs joined by blank
 * lines, and a single multi-paragraph reply already contains blank lines of
 * its own — so a paragraph with no "Sender:" prefix is a continuation of
 * whoever spoke last, not a new turn. That's what the running $current
 * speaker below is tracking.
 *
 * @param  string $transcript   Plain-text transcript (already run through
 *                               wp_kses_post by the caller).
 * @param  string $bot_name     The bot's configured display name.
 * @param  string $visitor_name The visitor's name, or '' to fall back to
 *                               whatever buildTranscript() used ('Visitor').
 * @return string HTML.
 */
function aichat_build_handoff_transcript_html( $transcript, $bot_name, $visitor_name ) {
	$bot_name     = trim( (string) $bot_name )     ?: 'AI Assistant';
	$visitor_name = trim( (string) $visitor_name ) ?: 'Visitor';

	$bot_color     = '#dc2626'; // red
	$visitor_color = '#16a34a'; // green
	$default_color = '#334155';

	$lines   = preg_split( '/\r\n|\r|\n/', (string) $transcript );
	$html    = '';
	$current = null; // 'bot' | 'visitor' | null — whoever spoke last.

	foreach ( $lines as $line ) {
		$trimmed = trim( $line );
		if ( '' === $trimmed ) {
			continue; // Blank lines are paragraph spacing, not content — the
			          // per-line <div> margins below already provide spacing.
		}

		$speaker = null;
		$text    = $trimmed;

		if ( 0 === strpos( $trimmed, $bot_name . ':' ) ) {
			$speaker = 'bot';
			$text    = ltrim( substr( $trimmed, strlen( $bot_name ) + 1 ) );
		} elseif ( 0 === strpos( $trimmed, $visitor_name . ':' ) ) {
			$speaker = 'visitor';
			$text    = ltrim( substr( $trimmed, strlen( $visitor_name ) + 1 ) );
		}

		if ( null !== $speaker ) {
			$current = $speaker;
			$color   = ( 'bot' === $speaker ) ? $bot_color : $visitor_color;
			$label   = ( 'bot' === $speaker ) ? $bot_name : $visitor_name;
			$html   .= '<div style="color:' . $color . ';margin:0 0 6px;">' .
				'<strong>' . esc_html( $label ) . ':</strong> ' . esc_html( $text ) . '</div>';
		} else {
			// Continuation line of whoever last spoke (or, if the transcript
			// somehow starts mid-paragraph, a neutral colour rather than
			// guessing a speaker).
			$color = ( 'bot' === $current ) ? $bot_color : ( 'visitor' === $current ? $visitor_color : $default_color );
			$html .= '<div style="color:' . $color . ';margin:0 0 6px;">' . esc_html( $trimmed ) . '</div>';
		}
	}

	return $html;
}

/**
 * Build the human-handoff notification email as three visually separate
 * blocks — visitor details, the question that triggered the handoff, and the
 * colour-coded transcript — rather than one flat wall of text.
 *
 * @param  string $name       Visitor name.
 * @param  string $phone      Visitor phone.
 * @param  string $email      Visitor email.
 * @param  string $question   The message that triggered the handoff.
 * @param  string $transcript Plain-text transcript (already sanitised by the caller).
 * @param  string $bot_name   The bot's configured display name.
 * @return string Full HTML email body.
 */
function aichat_build_handoff_email_html( $name, $phone, $email, $question, $transcript, $bot_name ) {
	$block_style = 'background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;' .
		'padding:16px 18px;margin:0 0 14px;';
	$heading_style = 'margin:0 0 10px;font-weight:700;color:#0f172a;font-size:14px;';

	$transcript_html = aichat_build_handoff_transcript_html( $transcript, $bot_name, $name ?: 'Visitor' );

	$html  = '<div style="font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif;' .
		'font-size:14px;line-height:1.6;color:#1f2937;max-width:640px;margin:0 auto;">';

	// Block 1 — visitor details.
	$html .= '<div style="' . $block_style . '">';
	$html .= '<p style="' . $heading_style . '">A visitor requested to speak with a human agent.</p>';
	$html .= '<p style="margin:0;">';
	$html .= 'Name:&nbsp;&nbsp;' . esc_html( $name  ?: '—' ) . '<br>';
	$html .= 'Phone: ' . esc_html( $phone ?: '—' ) . '<br>';
	$html .= 'Email: ' . esc_html( $email ?: '—' );
	$html .= '</p></div>';

	// Block 2 — trigger question.
	$html .= '<div style="' . $block_style . '">';
	$html .= '<p style="' . $heading_style . '">Trigger Question:</p>';
	$html .= '<p style="margin:0;">' . esc_html( $question ?: '—' ) . '</p>';
	$html .= '</div>';

	// Block 3 — colour-coded transcript.
	$html .= '<div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:16px 18px;margin:0;">';
	$html .= '<p style="' . $heading_style . '">--- Full Chat Transcript ---</p>';
	$html .= $transcript_html;
	$html .= '</div>';

	$html .= '</div>';

	return $html;
}

function aichat_parse_email_list( $raw ) {
	$pieces = preg_split( '/[,\n]+/', (string) $raw );
	$emails = array();
	$seen   = array();

	foreach ( $pieces as $piece ) {
		$email = sanitize_email( trim( $piece ) );
		if ( '' === $email || ! is_email( $email ) ) {
			continue;
		}
		$key = strtolower( $email );
		if ( isset( $seen[ $key ] ) ) {
			continue;
		}
		$seen[ $key ] = true;
		$emails[]     = $email;
	}

	return $emails;
}

/**
 * wp_mail() wrapper that actually surfaces delivery failures instead of
 * silently discarding them. wp_mail()'s boolean return value only tells you
 * whether PHPMailer *accepted* the message, not whether it was rejected by
 * the SMTP server — WordPress fires the 'wp_mail_failed' action with the
 * real WP_Error in that case, so we hook it transiently around this one
 * send to capture the actual reason without catching failures from
 * unrelated mail sent elsewhere on the site.
 *
 * On failure, records aichat_last_mail_error (surfaced via an admin notice
 * and the Settings page status list). On success, clears it, so a notice
 * from a since-fixed problem doesn't linger forever.
 *
 * @param  string|array $to      Recipient address(es).
 * @param  string       $subject Subject line.
 * @param  string       $body    Message body — HTML when $is_html is true.
 * @param  bool         $is_html Send as text/html instead of the site's default.
 * @return bool True if wp_mail() reported success and no WP_Error fired.
 */
function aichat_send_notification_email( $to, $subject, $body, $is_html = false ) {
	$mail_error = null;
	$capture    = function ( $wp_error ) use ( &$mail_error ) {
		$mail_error = $wp_error;
	};
	$force_html = function () {
		return 'text/html';
	};

	add_action( 'wp_mail_failed', $capture );
	if ( $is_html ) {
		// Filtered rather than passed as a wp_mail() header, so this doesn't
		// depend on whatever the active SMTP plugin does with headers — and
		// removed again right after, so it never leaks into unrelated mail
		// sent elsewhere on the site during the same request.
		add_filter( 'wp_mail_content_type', $force_html );
	}
	$sent = wp_mail( $to, $subject, $body );
	if ( $is_html ) {
		remove_filter( 'wp_mail_content_type', $force_html );
	}
	remove_action( 'wp_mail_failed', $capture );

	if ( ! $sent || $mail_error ) {
		update_option( 'aichat_last_mail_error', array(
			'time'    => current_time( 'mysql' ),
			'message' => $mail_error ? $mail_error->get_error_message() : __( 'wp_mail() reported failure with no further detail — check your SMTP/mail configuration.', 'orate-agency' ),
		), false );
		return false;
	}

	delete_option( 'aichat_last_mail_error' );
	return true;
}

// ─── Export: Leads CSV ────────────────────────────────────────────────────────

add_action( 'admin_post_aichat_export_leads_csv', 'aichat_handle_export_leads_csv' );

function aichat_handle_export_leads_csv() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Unauthorized' );
	}
	check_admin_referer( 'aichat_export_leads' );

	global $wpdb;
	$table = $wpdb->prefix . AICHAT_DB_TABLE;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$leads = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY created_at DESC", ARRAY_A );

	$filename = 'leads-' . gmdate( 'Y-m-d' ) . '.csv';

	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
	header( 'Pragma: no-cache' );
	header( 'Expires: 0' );

	$out = fopen( 'php://output', 'w' );
	fputcsv( $out, array( 'ID', 'Name', 'Email', 'Phone', 'Requirement', 'Transcript', 'Date' ) );

	foreach ( $leads as $lead ) {
		fputcsv( $out, array(
			$lead['id'],
			$lead['name'],
			$lead['email'],
			$lead['phone'],
			$lead['requirement'],
			$lead['transcript'],
			$lead['created_at'],
		) );
	}

	fclose( $out );
	exit;
}

// ─── Export: Leads PDF (print-ready HTML) ────────────────────────────────────

add_action( 'admin_post_aichat_export_leads_pdf', 'aichat_handle_export_leads_pdf' );

function aichat_handle_export_leads_pdf() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Unauthorized' );
	}
	check_admin_referer( 'aichat_export_leads' );

	global $wpdb;
	$table = $wpdb->prefix . AICHAT_DB_TABLE;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$leads     = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY created_at DESC", ARRAY_A );
	$site_name = get_bloginfo( 'name' );
	$date      = gmdate( 'Y-m-d' );

	header( 'Content-Type: text/html; charset=utf-8' );
	?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title><?php echo esc_html( $site_name ); ?> — Leads Export <?php echo esc_html( $date ); ?></title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: Arial, sans-serif; font-size: 12px; color: #1e293b; padding: 30px; }
  h1 { font-size: 18px; margin-bottom: 4px; }
  .meta { font-size: 11px; color: #64748b; margin-bottom: 20px; }
  table { width: 100%; border-collapse: collapse; }
  th { background: #f1f5f9; font-weight: 700; text-align: left; padding: 8px 10px; border: 1px solid #e2e8f0; font-size: 11px; }
  td { padding: 7px 10px; border: 1px solid #e2e8f0; vertical-align: top; font-size: 11px; }
  tr:nth-child(even) td { background: #f8fafc; }
  .transcript { font-size: 10px; color: #475569; max-width: 300px; white-space: pre-wrap; word-break: break-word; }
  @media print {
    body { padding: 0; }
    button { display: none; }
  }
</style>
</head>
<body>
<h1><?php echo esc_html( $site_name ); ?> — Leads</h1>
<p class="meta">Exported on <?php echo esc_html( $date ); ?> &nbsp;|&nbsp; <?php echo count( $leads ); ?> leads</p>
<button onclick="window.print()" style="margin-bottom:16px;padding:8px 16px;background:#22c55e;color:#fff;border:none;border-radius:6px;cursor:pointer;font-size:13px;">🖨 Print / Save as PDF</button>
<table>
  <thead>
    <tr>
      <th style="width:30px">#</th>
      <th>Name</th>
      <th>Email</th>
      <th>Phone</th>
      <th>Requirement</th>
      <th>Transcript</th>
      <th>Date</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ( $leads as $lead ) : ?>
    <tr>
      <td><?php echo (int) $lead['id']; ?></td>
      <td><?php echo esc_html( $lead['name'] ); ?></td>
      <td><?php echo esc_html( $lead['email'] ); ?></td>
      <td><?php echo esc_html( $lead['phone'] ); ?></td>
      <td><?php echo esc_html( $lead['requirement'] ); ?></td>
      <td class="transcript"><?php echo esc_html( $lead['transcript'] ); ?></td>
      <td style="white-space:nowrap"><?php echo esc_html( $lead['created_at'] ); ?></td>
    </tr>
    <?php endforeach; ?>
    <?php if ( empty( $leads ) ) : ?>
    <tr><td colspan="7" style="text-align:center;color:#94a3b8;padding:20px;">No leads yet.</td></tr>
    <?php endif; ?>
  </tbody>
</table>
<script>window.onload = function() { window.print(); };</script>
</body>
</html>
	<?php
	exit;
}

// ─── Widget Output ────────────────────────────────────────────────────────────
// Only hook on frontend requests. wp_footer doesn't fire in admin normally,
// but being explicit prevents edge cases where third-party code calls
// do_action('wp_footer') inside an admin context.

if ( ! is_admin() ) {
	add_action( 'wp_footer', 'aichat_output_inline_config', 1 );  // Priority 1 — before any scripts.
	add_action( 'wp_footer', 'aichat_render_chat_widget', 10 );
}
