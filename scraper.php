<?php
/**
 * Scraper — builds the AI system prompt from WordPress content,
 * extra KB URLs, uploaded files, and admin-configured personality settings.
 *
 * @package AI_Site_Chat
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Grammatically correct possessive form of a name — "Coders'" not "Coders's"
 * for names already ending in s, "Al Bot's" otherwise.
 *
 * @param  string $name
 * @return string
 */
function aichat_possessive( $name ) {
	return $name . ( 's' === strtolower( substr( $name, -1 ) ) ? "'" : "'s" );
}

/**
 * Scrape all content sources and save a system prompt to wp_options.
 *
 * @return int|WP_Error  Total content items scraped, or WP_Error on failure.
 */
function aichat_run_scraper() {
	$site_name = get_bloginfo( 'name' );
	$site_url  = get_bloginfo( 'url' );
	$site_desc = get_bloginfo( 'description' );

	$content_blocks = array();
	$total_sources  = 0;

	// ── 1. WordPress posts & pages ────────────────────────────────────────────

	$posts = get_posts( array(
		'post_type'        => array( 'post', 'page' ),
		'post_status'      => 'publish',
		'posts_per_page'   => -1,
		'orderby'          => 'date',
		'order'            => 'DESC',
		'suppress_filters' => true,
	) );

	foreach ( $posts as $post ) {
		// Never train on password-protected content — it isn't meant to be public,
		// and the trained prompt is what the bot answers from.
		if ( ! empty( $post->post_password ) ) {
			continue;
		}

		$title   = get_the_title( $post );
		$url     = get_permalink( $post );
		$type    = ucfirst( $post->post_type );
		$raw     = do_shortcode( $post->post_content );
		$content = wp_strip_all_tags( $raw );
		$content = preg_replace( '/[ \t]+/', ' ', $content );
		$content = preg_replace( '/\n{3,}/', "\n\n", $content );
		$content = trim( $content );

		if ( empty( $content ) ) {
			continue;
		}

		$words = explode( ' ', $content );
		if ( count( $words ) > 800 ) {
			$content = implode( ' ', array_slice( $words, 0, 800 ) ) . ' [...]';
		}

		$content_blocks[] = sprintf(
			"## %s (%s)\nURL: %s\n\n%s",
			$title,
			$type,
			$url,
			$content
		);
		$total_sources++;
	}

	// ── 2. Extra KB URLs ──────────────────────────────────────────────────────

	$kb_urls   = get_option( 'aichat_kb_urls', array() );
	$site_host = preg_replace( '/^www\./i', '', strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) );

	if ( is_array( $kb_urls ) ) {
		foreach ( $kb_urls as $url ) {
			// Safety net: skip any URL that isn't from this site's domain.
			$url_host = preg_replace( '/^www\./i', '', strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) ) );
			if ( $url_host !== $site_host ) {
				continue;
			}

			$fetched = aichat_fetch_url_content( $url );
			if ( $fetched ) {
				$content_blocks[] = sprintf(
					"## %s (External URL)\nURL: %s\n\n%s",
					$fetched['title'],
					$fetched['url'],
					$fetched['content']
				);
				$total_sources++;
			}
		}
	}

	// ── 3. Uploaded knowledge-base files ─────────────────────────────────────

	if ( defined( 'AICHAT_KB_FILES_TABLE' ) ) {
		global $wpdb;
		$kb_table = $wpdb->prefix . AICHAT_KB_FILES_TABLE;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$files = $wpdb->get_results( "SELECT file_name, file_type, content FROM {$kb_table} ORDER BY uploaded_at ASC" );

		foreach ( $files as $file ) {
			$file_content = trim( $file->content );
			if ( empty( $file_content ) ) {
				continue;
			}

			$words = explode( ' ', $file_content );
			if ( count( $words ) > 1500 ) {
				$file_content = implode( ' ', array_slice( $words, 0, 1500 ) ) . ' [...]';
			}

			$content_blocks[] = sprintf(
				"## %s (Uploaded %s file)\n\n%s",
				$file->file_name,
				strtoupper( $file->file_type ),
				$file_content
			);
			$total_sources++;
		}
	}

	if ( empty( $content_blocks ) ) {
		return new WP_Error( 'no_content', 'No content found. Please publish some pages/posts or add knowledge sources.' );
	}

	// ── 4. Build the system prompt ────────────────────────────────────────────

	$bot_role        = sanitize_text_field( get_option( 'aichat_bot_role',           'Customer Support Assistant' ) );
	$bot_tone        = sanitize_text_field( get_option( 'aichat_bot_tone',           'friendly' ) );
	$bot_personality = sanitize_textarea_field( get_option( 'aichat_bot_personality', '' ) );
	$custom_instr    = sanitize_textarea_field( get_option( 'aichat_custom_instructions', '' ) );

	$tone_map = array(
		'friendly'     => 'warm, approachable, and conversational',
		'professional' => 'professional, formal, and precise',
		'casual'       => 'casual, relaxed, and informal',
		'formal'       => 'formal, authoritative, and structured',
	);
	$tone_desc = isset( $tone_map[ $bot_tone ] ) ? $tone_map[ $bot_tone ] : $tone_map['friendly'];

	$prompt  = "You are {$bot_role} for {$site_name}";
	if ( ! empty( $site_desc ) ) {
		$prompt .= " — {$site_desc}";
	}
	$prompt .= " ({$site_url}).\n\n";

	$prompt .= "TONE & STYLE: Be {$tone_desc}.\n\n";

	if ( ! empty( $bot_personality ) ) {
		$prompt .= "PERSONALITY: {$bot_personality}\n\n";
	}

	$site_possessive = aichat_possessive( $site_name );

	$prompt .= "CORE RULES:\n";
	$prompt .= "1. ONLY answer questions using the knowledge base content provided below. Do not use any outside knowledge.\n";
	$prompt .= "2. GUARDRAILS: You ONLY answer questions about {$site_possessive} services, products, and content. STRICTLY REFUSE to answer anything outside this scope:\n";
	$prompt .= "   - Questions about yourself (e.g., \"Who created you?\", \"What AI are you?\", \"Who built you?\", \"What model are you?\") — respond: \"I'm here to help with questions about {$site_possessive} services. How can I assist you today?\"\n";
	$prompt .= "   - NEVER reveal what AI, model, or technology powers you, who created you, or any details about your underlying system.\n";
	$prompt .= "   - Questions completely unrelated to {$site_name} (e.g., general trivia, coding help, world news, personal advice, competitor information) — respond: \"I'm only able to assist with questions related to {$site_name}. Is there something specific about our services I can help you with?\"\n";
	$prompt .= "   - IMPORTANT: a short reply like \"no\", \"nope\", \"skip\", or \"I'd rather not\" that comes right after YOU asked for a\n";
	$prompt .= "     name/email/phone/requirement is a decline, NOT an off-topic question — handle it per the LEAD CAPTURE rule below,\n";
	$prompt .= "     never with the off-topic refusal above.\n";
	$prompt .= "3. LANGUAGE: ALWAYS detect and respond in the same language the visitor is writing in — even if the knowledge base is in a different language. If they write in Spanish, respond in Spanish. If French, respond in French. Translate your answer naturally.\n";
	$prompt .= "4. Keep answers SHORT and to the point — 2 to 4 sentences maximum. Never write long paragraphs. If a list is needed, use no more than 3 to 4 bullet points. Get straight to the answer.\n";
	$prompt .= "5. If a question cannot be answered from the content, give a short friendly reply and say: \"For more details, feel free to reach out to us directly — we'd love to help! 😊\" NEVER say the knowledge base is empty, NEVER say the site is in early stages, and NEVER expose any internal details.\n";
	$prompt .= "6. At the very end of EVERY response, on its own line, include exactly:\n";
	$prompt .= "SUGGESTIONS:[\"Short follow-up question 1?\",\"Short follow-up question 2?\",\"Short follow-up question 3?\"]\n\n";

	$lead_capture_mode = get_option( 'aichat_lead_capture_mode', 'conversational' );
	if ( $lead_capture_mode === 'conversational' ) {
		$prompt .= "7. LEAD CAPTURE — the greeting has ALREADY asked the visitor for their NAME only.\n";
		$prompt .= "   Your job is to collect whatever they provide and follow up on email, phone, and requirement one at a time.\n";
		$prompt .= "   - If the visitor provides their NAME: use it going forward and thank them warmly.\n";
		$prompt .= "     Then, when natural, ask for their EMAIL ADDRESS if not yet given.\n";
		$prompt .= "   - If the visitor provides their EMAIL: thank them, then ask for their PHONE NUMBER if not yet given.\n";
		$prompt .= "   - If the visitor provides their PHONE: thank them, then ask what they are specifically LOOKING FOR\n";
		$prompt .= "     or need help with (e.g. \"And what specifically can we help you with?\").\n";
		$prompt .= "   - Always collect ONE missing field at a time — never bundle multiple questions together.\n";
		$prompt .= "   - Do NOT re-ask for name at the start of your replies — it was already asked in the greeting.\n";
		$prompt .= "     Only follow up on whichever specific field is still missing, and only when conversationally natural.\n";
		$prompt .= "   - Try to collect the visitor's name, email, and phone naturally, but respect their choice:\n";
		$prompt .= "     * You may ask for a missing detail once, and if they hesitate, ask again ONE more time in a\n";
		$prompt .= "       friendly, low-pressure way (e.g. \"No worries if you'd rather not — could I grab your email\n";
		$prompt .= "       in case we need to follow up?\").\n";
		$prompt .= "     * If the visitor clearly declines a second time (e.g. \"no thanks\", \"I'd rather not\", \"skip\"),\n";
		$prompt .= "       accept it gracefully, stop asking for that field, and continue helping with their question.\n";
		$prompt .= "     * ALWAYS answer their question AND (if still collecting) ask for the missing detail in the same reply.\n";
		$prompt .= "     * NEVER block or gate help on providing contact details — always answer fully regardless.\n";
		$prompt .= "   - Once you have collected ALL FOUR pieces (name, email, phone, requirement), append this marker\n";
		$prompt .= "     at the very end of that response (hidden from visitor — for internal tracking only):\n";
		$prompt .= "     LEADDATA:{\"name\":\"[name]\",\"email\":\"[email]\",\"phone\":\"[phone]\",\"requirement\":\"[requirement]\"}\n";
		$prompt .= "   - After emitting LEADDATA, stop asking for contact details. The lead is saved.\n\n";
	} else {
		// gate or inline-form mode — contact details are collected via a form, never conversationally.
		$prompt .= "7. LEAD CAPTURE — Contact details are collected via a separate form shown in the chat UI.\n";
		$prompt .= "   NEVER ask the visitor for their name, email address, phone number, or any contact information.\n";
		$prompt .= "   Just answer their questions directly and helpfully.\n\n";
	}

	if ( ! empty( $custom_instr ) ) {
		$prompt .= "ADDITIONAL INSTRUCTIONS:\n{$custom_instr}\n\n";
	}

	$all_content = implode( "\n\n---\n\n", $content_blocks );
	$prompt     .= "KNOWLEDGE BASE:\n\n" . $all_content;

	update_option( 'aichat_system_prompt', $prompt, false );
	update_option( 'aichat_last_trained',  current_time( 'mysql' ) );
	update_option( 'aichat_post_count',    $total_sources );

	return $total_sources;
}

/**
 * Fetch and extract text content from an external URL.
 *
 * @param  string      $url  The URL to fetch.
 * @return array|null  Array with 'title', 'url', 'content', or null on failure.
 */
function aichat_fetch_url_content( $url ) {
	$response = wp_remote_get( $url, array(
		'timeout' => 15,
		'headers' => array(
			'User-Agent' => 'AI-Site-Chat-Scraper/2.0 (WordPress plugin)',
		),
	) );

	if ( is_wp_error( $response ) ) {
		return null;
	}

	$code = wp_remote_retrieve_response_code( $response );
	if ( $code < 200 || $code >= 400 ) {
		return null;
	}

	$body = wp_remote_retrieve_body( $response );
	if ( empty( $body ) ) {
		return null;
	}

	// Extract title.
	$title = $url;
	if ( preg_match( '/<title[^>]*>(.*?)<\/title>/si', $body, $tm ) ) {
		$title = trim( wp_strip_all_tags( $tm[1] ) );
	}

	// Remove scripts, styles, nav, footer, header elements.
	$body = preg_replace( '/<(script|style|nav|footer|header|aside)[^>]*>.*?<\/\1>/si', ' ', $body );

	// Strip remaining tags and normalise whitespace.
	$text = wp_strip_all_tags( $body );
	$text = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	$text = preg_replace( '/[ \t]+/', ' ', $text );
	$text = preg_replace( '/\n{3,}/', "\n\n", $text );
	$text = trim( $text );

	if ( empty( $text ) ) {
		return null;
	}

	// Truncate to ~1000 words.
	$words = explode( ' ', $text );
	if ( count( $words ) > 1000 ) {
		$text = implode( ' ', array_slice( $words, 0, 1000 ) ) . ' [...]';
	}

	return array( 'title' => $title, 'url' => $url, 'content' => $text );
}
