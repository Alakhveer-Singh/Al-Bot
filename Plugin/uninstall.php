<?php
/**
 * Uninstall Orate — Agency Plugin.
 *
 * Runs when the plugin is deleted (not just deactivated) from the Plugins screen.
 * Removes all wp_options entries, drops custom DB tables, and deletes uploaded files.
 *
 * @package Orate_Agency
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$aichat_options = array(
	// Core.
	'aichat_bot_name', 'aichat_bot_emoji', 'aichat_primary_color',
	'aichat_welcome_message',
	'aichat_system_prompt', 'aichat_last_trained', 'aichat_post_count',
	'aichat_db_version', 'aichat_kb_dir_token', 'aichat_last_mail_error',
	// Personality.
	'aichat_bot_role', 'aichat_bot_tone', 'aichat_bot_personality',
	'aichat_custom_instructions', 'aichat_kb_urls', 'aichat_handoff_email',
	// Widget behaviour.
	'aichat_lead_capture_mode', 'aichat_widget_theme', 'aichat_hide_branding',
	'aichat_human_handoff', 'aichat_voice_mode', 'aichat_bot_icon_id',
	// Multi-LLM provider.
	'aichat_llm_provider',
	// API keys.
	'aichat_api_key',          // Claude.
	'aichat_openai_api_key',
	'aichat_gemini_api_key',
	'aichat_mistral_api_key',
	'aichat_llama_api_key',
	'aichat_deepseek_api_key',
	// Models.
	'aichat_claude_model', 'aichat_openai_model', 'aichat_gemini_model',
	'aichat_mistral_model', 'aichat_llama_model', 'aichat_deepseek_model',
);

/**
 * Clean up everything for the CURRENT blog context: uploaded KB files,
 * wp_options entries, and the plugin's custom DB tables. On multisite this
 * must run once per site (inside switch_to_blog()) because uploads paths,
 * options, and table prefixes are all per-site.
 */
function aichat_uninstall_cleanup_current_site( $options ) {
	global $wpdb;

	// Uploaded knowledge-base files. Read the folder token BEFORE deleting
	// options below (it lives in aichat_kb_dir_token). The folder name
	// includes a random per-install token (see aichat_get_kb_dir() in the
	// main plugin file) so it isn't guessable.
	$upload_dir = wp_upload_dir();
	$kb_token   = get_option( 'aichat_kb_dir_token', '' );
	$kb_dir     = $kb_token
		? $upload_dir['basedir'] . '/ai-site-chat-' . $kb_token . '/'
		: $upload_dir['basedir'] . '/ai-site-chat/'; // Fallback for pre-token installs.

	if ( is_dir( $kb_dir ) ) {
		$files = glob( $kb_dir . '*' );
		if ( $files ) {
			foreach ( $files as $file ) {
				if ( is_file( $file ) ) {
					// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
					unlink( $file );
				}
			}
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
		rmdir( $kb_dir );
	}

	// Options.
	foreach ( $options as $option ) {
		delete_option( $option );
	}

	// Custom DB tables.
	$tables = array(
		$wpdb->prefix . 'aichat_leads',
		$wpdb->prefix . 'aichat_analytics',
		$wpdb->prefix . 'aichat_kb_files',
	);
	foreach ( $tables as $table ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
	}
}

if ( is_multisite() ) {
	$sites = get_sites( array( 'fields' => 'ids', 'number' => 0 ) );
	foreach ( $sites as $site_id ) {
		switch_to_blog( $site_id );
		aichat_uninstall_cleanup_current_site( $aichat_options );
		restore_current_blog();
	}
} else {
	aichat_uninstall_cleanup_current_site( $aichat_options );
}

// ─── Clear scheduled cron ─────────────────────────────────────────────────

wp_clear_scheduled_hook( 'aichat_auto_train_cron' );
