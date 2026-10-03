<?php
/**
 * Admin pages: Settings, Knowledge Base, Analytics, Leads.
 *
 * @package AI_Site_Chat
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ═══════════════════════════════════════════════════════════════════════════
   SHARED ADMIN STYLES
═══════════════════════════════════════════════════════════════════════════ */

function aichat_admin_styles() {
	?>
	<style>
	/* ─────────────────────────────────────────────────────────────────────────
	   AI Site Chat — Admin Global Styles
	───────────────────────────────────────────────────────────────────────── */

	:root {
		--aichat-c-primary:      #16a34a;
		--aichat-c-primary-light:#22c55e;
		--aichat-c-primary-bg:   #f0fdf4;
		--aichat-c-primary-ring: rgba(34,197,94,.14);
		--aichat-c-ink:          #0f172a;
		--aichat-c-body:         #475569;
		--aichat-c-muted:        #94a3b8;
		--aichat-c-border:       #e8edf2;
		--aichat-c-border-soft:  #f1f5f9;
		--aichat-c-surface:      #ffffff;
		--aichat-c-surface-2:    #f8fafc;
		--aichat-r-sm: 8px;
		--aichat-r-md: 12px;
		--aichat-r-lg: 16px;
		--aichat-shadow-card: 0 1px 2px rgba(15,23,42,.04), 0 8px 24px -6px rgba(15,23,42,.07);
		--aichat-shadow-hover: 0 4px 10px rgba(15,23,42,.06), 0 14px 34px -10px rgba(15,23,42,.14);
	}

	/* ── Layout ── */
	.aichat-wrap { padding-top: 22px; max-width: 100%; }
	.aichat-wrap * { box-sizing: border-box; }
	.aichat-page-title {
		display:flex; align-items:center; gap:12px; flex-wrap:wrap; margin-bottom:6px;
		font-size: 23px; font-weight: 800; color: var(--aichat-c-ink); letter-spacing:-.01em;
	}
	.aichat-title-icon {
		font-size:20px !important; width:40px; height:40px; line-height:40px !important;
		text-align:center; color:#fff; border-radius: 11px;
		background: linear-gradient(135deg, var(--aichat-c-primary) 0%, var(--aichat-c-primary-light) 100%);
		box-shadow: 0 4px 14px rgba(22,163,74,.3);
	}
	.aichat-version-badge { font-size:11px; font-weight:600; background:#eef2f7; color:#64748b; padding:3px 10px; border-radius:20px; letter-spacing:.02em; }
	.aichat-page-sub { margin: 2px 0 0; font-size: 13.5px; color: var(--aichat-c-body); max-width: 720px; line-height:1.55; }

	.aichat-admin-layout { display:grid; grid-template-columns:1fr 300px; gap:22px; margin-top:18px; align-items:start; }
	@media (max-width:1280px) { .aichat-admin-layout { grid-template-columns:1fr; } }
	.aichat-admin-sidebar { position: sticky; top: 32px; }
	@media (max-width:1280px) { .aichat-admin-sidebar { position:static; } }

	/* ── Tab navigation (shared across Settings / KB / Analytics / Leads) ── */
	.aichat-tabs {
		display:flex; gap:4px; margin: 18px 0 4px; padding: 5px;
		background:#eef2f7; border-radius: 13px; width:fit-content; max-width:100%;
		overflow-x:auto; -webkit-overflow-scrolling:touch;
	}
	.aichat-tab {
		display:flex; align-items:center; gap:7px; padding:9px 16px; border-radius:9px;
		font-size:13px; font-weight:600; color:#475569; text-decoration:none; white-space:nowrap;
		transition: background .15s, color .15s, box-shadow .15s;
	}
	.aichat-tab .dashicons { font-size:16px; width:16px; height:16px; }
	.aichat-tab:hover { color:var(--aichat-c-ink); background:rgba(255,255,255,.6); }
	.aichat-tab.is-active { background:#fff; color:var(--aichat-c-primary); box-shadow:0 1px 3px rgba(15,23,42,.1), 0 1px 2px rgba(15,23,42,.06); }
	.aichat-tab:focus-visible { outline:2px solid var(--aichat-c-primary-light); outline-offset:2px; }

	/* ── Card ── */
	.aichat-card {
		background: var(--aichat-c-surface);
		border: 1px solid var(--aichat-c-border);
		border-radius: var(--aichat-r-lg);
		padding: 26px 28px;
		margin-bottom: 22px;
		box-shadow: var(--aichat-shadow-card);
		transition: box-shadow .2s ease, transform .2s ease;
	}
	.aichat-card-title {
		margin: 0 0 18px;
		font-size: 11px;
		font-weight: 700;
		color: var(--aichat-c-muted);
		text-transform: uppercase;
		letter-spacing: .08em;
		display: flex;
		align-items: center;
		gap: 8px;
		padding-bottom: 14px;
		border-bottom: 1px solid var(--aichat-c-border-soft);
	}
	.aichat-card-title .dashicons {
		color: var(--aichat-c-primary); font-size:14px; width:26px; height:26px; line-height:26px;
		text-align:center; background: var(--aichat-c-primary-bg); border-radius:7px;
	}

	/* ── Hero banner ── */
	.aichat-hero {
		background: linear-gradient(135deg, #15803d 0%, #16a34a 45%, #4ade80 100%);
		border-radius: var(--aichat-r-lg);
		padding: 26px 30px;
		margin-bottom: 22px;
		color: #fff;
		display: flex;
		align-items: center;
		gap: 20px;
		box-shadow: 0 10px 30px -8px rgba(22,163,74,.4);
		position: relative;
		overflow: hidden;
	}
	.aichat-hero::after {
		content:''; position:absolute; inset:0;
		background: radial-gradient(500px 160px at 88% -20%, rgba(255,255,255,.25), transparent 60%);
		pointer-events:none;
	}
	.aichat-hero-icon { font-size:46px; line-height:1; filter:drop-shadow(0 2px 6px rgba(0,0,0,.15)); position:relative; }
	.aichat-hero h2 { margin:0 0 5px; font-size:20px; font-weight:700; color:#fff; position:relative; }
	.aichat-hero p  { margin:0; font-size:13px; color:rgba(255,255,255,.88); line-height:1.5; position:relative; }

	/* ── Form table overrides (all admin cards) ── */
	.aichat-card .form-table { margin:0; border:none; }
	.aichat-card .form-table th {
		width: 180px;
		padding: 14px 20px 14px 0;
		font-size: 13px;
		font-weight: 600;
		color: #374151;
		vertical-align: middle;
	}
	.aichat-card .form-table td { padding: 12px 0; vertical-align: middle; }
	.aichat-card .form-table tr { border-bottom: 1px solid var(--aichat-c-border-soft); }
	.aichat-card .form-table tr:last-child { border-bottom: none; }
	.aichat-card .form-table input[type="text"],
	.aichat-card .form-table input[type="url"],
	.aichat-card .form-table input[type="email"],
	.aichat-card .form-table input[type="password"],
	.aichat-card .form-table textarea {
		border: 1.5px solid var(--aichat-c-border) !important;
		border-radius: var(--aichat-r-sm) !important;
		padding: 10px 13px !important;
		font-size: 14px !important;
		color: var(--aichat-c-ink) !important;
		background: var(--aichat-c-surface-2) !important;
		transition: border-color .15s, box-shadow .15s, background .15s;
		box-shadow: none !important;
	}
	.aichat-card .form-table input:hover,
	.aichat-card .form-table textarea:hover { border-color:#cbd5e1 !important; }
	.aichat-card .form-table input:focus,
	.aichat-card .form-table textarea:focus {
		border-color: var(--aichat-c-primary-light) !important;
		background: #fff !important;
		box-shadow: 0 0 0 3.5px var(--aichat-c-primary-ring) !important;
		outline: none !important;
	}
	.aichat-card .form-table select {
		border: 1.5px solid var(--aichat-c-border);
		border-radius: var(--aichat-r-sm);
		padding: 10px 36px 10px 13px;
		font-size: 14px;
		color: var(--aichat-c-ink);
		background-color: var(--aichat-c-surface-2);
		appearance: none;
		-webkit-appearance: none;
		background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' viewBox='0 0 14 14'%3E%3Cpath fill='%2364748b' d='M7 9L2 4h10z'/%3E%3C/svg%3E");
		background-repeat: no-repeat;
		background-position: right 12px center;
		cursor: pointer;
		transition: border-color .15s, box-shadow .15s;
		min-width: 220px;
		max-width: 320px;
	}
	.aichat-card .form-table select:hover  { border-color:#cbd5e1; }
	.aichat-card .form-table select:focus {
		border-color: var(--aichat-c-primary-light);
		outline: none;
		box-shadow: 0 0 0 3.5px var(--aichat-c-primary-ring);
	}
	.aichat-card .form-table .description {
		font-size: 12px;
		color: var(--aichat-c-body);
		margin-top: 6px;
		line-height: 1.5;
	}
	.aichat-card .form-table .description a {
		color: #2563eb;
		font-weight: 500;
		text-decoration: none;
	}
	.aichat-card .form-table .description a:hover { text-decoration: underline; }

	/* ── API key wrapper ── */
	.aichat-api-key-wrap { display:flex; gap:8px; align-items:center; max-width:460px; }
	.aichat-api-key-wrap input { flex:1; min-width:0; }
	.aichat-toggle-pw { flex-shrink:0; border-radius:var(--aichat-r-sm) !important; }
	.aichat-toggle-pw .dashicons { margin:0; }

	/* ── Badges ── */
	.aichat-badge { font-size:11px; font-weight:700; padding:3.5px 10px; border-radius:20px; letter-spacing:.01em; display:inline-flex; align-items:center; gap:4px; }
	.aichat-badge--green  { background:#dcfce7; color:#15803d; }
	.aichat-badge--yellow { background:#fef9c3; color:#854d0e; }
	.aichat-badge--red    { background:#fee2e2; color:#b91c1c; }
	.aichat-badge--blue   { background:#dbeafe; color:#1d4ed8; }

	/* ── Status list ── */
	.aichat-status-list { margin:0; padding:0; list-style:none; }
	.aichat-status-list li { display:flex; align-items:center; justify-content:space-between; padding:10px 0; border-bottom:1px solid var(--aichat-c-border-soft); font-size:13px; color:var(--aichat-c-body); }
	.aichat-status-list li:last-child { border-bottom:none; }

	/* ── Steps ── */
	.aichat-steps { margin:0; padding-left:20px; }
	.aichat-steps li { margin-bottom:8px; font-size:13px; color:var(--aichat-c-body); line-height:1.5; }

	/* ── Train button ── */
	.aichat-train-btn {
		display:flex; align-items:center; gap:7px; width:100%; justify-content:center;
		padding:11px 14px !important; font-size:13px !important; border-radius:var(--aichat-r-sm) !important;
		box-shadow: 0 4px 12px rgba(22,163,74,.22) !important; transition: transform .15s ease, box-shadow .15s ease !important;
	}
	.aichat-train-btn:hover:not(:disabled) { transform: translateY(-1px); box-shadow: 0 6px 16px rgba(22,163,74,.3) !important; }
	.aichat-train-status { margin-top:10px; min-height:20px; font-size:12.5px; line-height:1.5; }
	.aichat-train-status.is-success { color:#15803d; }
	.aichat-train-status.is-error   { color:#dc2626; }
	.aichat-trained-info { display:flex; align-items:flex-start; gap:8px; background:var(--aichat-c-primary-bg); border:1px solid #bbf7d0; border-radius:var(--aichat-r-md); padding:12px 13px; margin-bottom:14px; font-size:12.5px; color:#15803d; line-height:1.5; }
	.aichat-trained-info .dashicons { flex-shrink:0; color:#16a34a; margin-top:1px; }

	/* ── Shortcode box ── */
	.aichat-shortcode-box { background:#f1f5f9; border:1.5px dashed #cbd5e1; border-radius:var(--aichat-r-md); padding:13px 16px; font-family:ui-monospace,monospace; font-size:14px; color:#1e40af; cursor:pointer; user-select:all; transition:background .15s, border-color .15s, transform .1s; letter-spacing:.02em; }
	.aichat-shortcode-box:hover { background:#e0e7ff; border-color:#818cf8; transform: translateY(-1px); }

	/* ── Sidebar card body text ── */
	.aichat-admin-sidebar .aichat-card p { font-size:13px; color:var(--aichat-c-body); line-height:1.6; margin-bottom:14px; }

	/* ── Empty states ── */
	.aichat-empty {
		display:flex; flex-direction:column; align-items:center; text-align:center;
		gap:6px; padding: 40px 20px; color:var(--aichat-c-muted);
	}
	.aichat-empty .dashicons { font-size:34px; width:34px; height:34px; color:#cbd5e1; }
	.aichat-empty strong { color:#64748b; font-size:14px; }
	.aichat-empty span { font-size:12.5px; max-width:340px; line-height:1.6; }

	/* ── Submit button ── */
	.aichat-wrap #submit { border-radius:var(--aichat-r-sm) !important; padding:10px 24px !important; font-size:14px !important; box-shadow: 0 4px 12px rgba(22,163,74,.22) !important; transition: transform .15s ease, box-shadow .15s ease !important; }
	.aichat-wrap #submit:hover { transform: translateY(-1px); box-shadow: 0 6px 16px rgba(22,163,74,.3) !important; }
	.aichat-wrap .button { border-radius:var(--aichat-r-sm) !important; transition: transform .1s ease, box-shadow .15s ease; }
	.aichat-wrap .button:hover { transform: translateY(-1px); }

	/* ── Spin ── */
	@keyframes aichat-spin { to { transform:rotate(360deg); } }
	.aichat-spinning { animation:aichat-spin .8s linear infinite; display:inline-block; }

	/* ── Reduced motion ── */
	@media (prefers-reduced-motion: reduce) {
		.aichat-card, .aichat-tab, .aichat-wrap .button, .aichat-shortcode-box { transition:none !important; }
	}
	</style>
	<?php
}

/* ═══════════════════════════════════════════════════════════════════════════
   SHARED TAB NAVIGATION
═══════════════════════════════════════════════════════════════════════════ */

/**
 * Renders the pill tab-bar linking Settings / Knowledge Base / Analytics / Leads,
 * so all four admin screens read as one cohesive app instead of separate pages.
 *
 * @param string $current One of 'settings', 'kb', 'analytics', 'leads'.
 */
function aichat_admin_page_nav( $current ) {
	$tabs = array(
		'settings'  => array( 'label' => __( 'Settings', 'orate-agency' ),        'icon' => 'dashicons-admin-generic', 'page' => 'ai-site-chat-panel' ),
		'kb'        => array( 'label' => __( 'Knowledge Base', 'orate-agency' ),  'icon' => 'dashicons-database',      'page' => 'ai-site-chat-kb' ),
		'analytics' => array( 'label' => __( 'Analytics', 'orate-agency' ),       'icon' => 'dashicons-chart-bar',     'page' => 'ai-site-chat-analytics' ),
		'leads'     => array( 'label' => __( 'Leads', 'orate-agency' ),           'icon' => 'dashicons-groups',        'page' => 'ai-site-chat-leads' ),
	);
	?>
	<nav class="aichat-tabs" aria-label="<?php esc_attr_e( 'Orate Agency sections', 'orate-agency' ); ?>">
		<?php foreach ( $tabs as $key => $tab ) : ?>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . $tab['page'] ) ); ?>"
				class="aichat-tab <?php echo ( $key === $current ) ? 'is-active' : ''; ?>"
				<?php echo ( $key === $current ) ? 'aria-current="page"' : ''; ?>>
				<span class="dashicons <?php echo esc_attr( $tab['icon'] ); ?>"></span>
				<?php echo esc_html( $tab['label'] ); ?>
			</a>
		<?php endforeach; ?>
	</nav>
	<?php
}

/* ═══════════════════════════════════════════════════════════════════════════
   SETTINGS PAGE
═══════════════════════════════════════════════════════════════════════════ */

/**
 * Render one editable proactive-rule row.
 *
 * Also used to build the blank template the "Add Rule" button clones, which is
 * why $index may be the literal '__INDEX__' placeholder rather than an int.
 *
 * @param  int|string $index     Row index, or '__INDEX__' for the template.
 * @param  array      $rule      Rule data.
 * @param  array      $types     type => label.
 * @param  array      $hints     type => condition-field hint.
 * @return string HTML.
 */
function aichat_render_proactive_rule_row( $index, $rule, $types, $hints ) {
	$name = 'aichat_proactive_rules[' . $index . ']';
	$uid  = 'aichat-rule-' . $index;

	$type      = isset( $rule['trigger_type'] ) ? $rule['trigger_type'] : 'idle_on_page';
	$condition = isset( $rule['condition_value'] ) ? $rule['condition_value'] : '';
	$delay     = isset( $rule['delay_seconds'] ) ? (int) $rule['delay_seconds'] : 0;
	$message   = isset( $rule['message'] ) ? $rule['message'] : '';
	$enabled   = ! empty( $rule['enabled'] );
	$rule_id   = isset( $rule['id'] ) ? $rule['id'] : '';

	ob_start();
	?>
	<div class="aichat-rule" data-rule-row>
		<input type="hidden" name="<?php echo esc_attr( $name ); ?>[id]" value="<?php echo esc_attr( $rule_id ); ?>" />

		<div class="aichat-rule-head">
			<label class="aichat-rule-enable">
				<input type="checkbox" name="<?php echo esc_attr( $name ); ?>[enabled]" value="1" <?php checked( $enabled ); ?> />
				<span><?php esc_html_e( 'Enabled', 'orate-agency' ); ?></span>
			</label>
			<button type="button" class="button-link aichat-rule-remove" aria-label="<?php esc_attr_e( 'Remove this rule', 'orate-agency' ); ?>">
				<span class="dashicons dashicons-trash"></span>
			</button>
		</div>

		<div class="aichat-rule-grid">
			<div>
				<label for="<?php echo esc_attr( $uid ); ?>-type"><?php esc_html_e( 'When', 'orate-agency' ); ?></label>
				<select id="<?php echo esc_attr( $uid ); ?>-type" name="<?php echo esc_attr( $name ); ?>[trigger_type]" class="aichat-rule-type">
					<?php foreach ( $types as $key => $label ) : ?>
						<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $type, $key ); ?>>
							<?php echo esc_html( $label ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</div>

			<div class="aichat-rule-condition"<?php echo ( 'exit_intent' === $type ) ? ' style="display:none;"' : ''; ?>>
				<label for="<?php echo esc_attr( $uid ); ?>-condition"><?php esc_html_e( 'Value', 'orate-agency' ); ?></label>
				<input type="text" id="<?php echo esc_attr( $uid ); ?>-condition"
					name="<?php echo esc_attr( $name ); ?>[condition_value]"
					value="<?php echo esc_attr( $condition ); ?>" />
				<span class="aichat-rule-hint">
					<?php echo esc_html( isset( $hints[ $type ] ) ? $hints[ $type ] : '' ); ?>
				</span>
			</div>

			<div>
				<label for="<?php echo esc_attr( $uid ); ?>-delay"><?php esc_html_e( 'Then wait (sec)', 'orate-agency' ); ?></label>
				<input type="number" min="0" max="600" id="<?php echo esc_attr( $uid ); ?>-delay"
					name="<?php echo esc_attr( $name ); ?>[delay_seconds]"
					value="<?php echo esc_attr( $delay ); ?>" />
			</div>
		</div>

		<div class="aichat-rule-message">
			<label for="<?php echo esc_attr( $uid ); ?>-message"><?php esc_html_e( 'Say', 'orate-agency' ); ?></label>
			<textarea id="<?php echo esc_attr( $uid ); ?>-message" rows="2"
				name="<?php echo esc_attr( $name ); ?>[message]"
				placeholder="<?php esc_attr_e( 'Before you go — can I help you find something?', 'orate-agency' ); ?>"><?php echo esc_textarea( $message ); ?></textarea>
		</div>
	</div>
	<?php
	return ob_get_clean();
}

function aichat_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$last_trained = get_option( 'aichat_last_trained', '' );
	$post_count   = (int) get_option( 'aichat_post_count', 0 );

	wp_enqueue_style( 'wp-color-picker' );
	wp_enqueue_script( 'wp-color-picker' );

	aichat_admin_styles();
	?>
	<div class="wrap aichat-wrap">
		<h1 class="aichat-page-title">
			<span class="dashicons dashicons-format-chat aichat-title-icon"></span>
			<?php esc_html_e( 'Orate — Agency Plugin', 'orate-agency' ); ?>
			<span class="aichat-version-badge">v<?php echo esc_html( AICHAT_VERSION ); ?></span>
		</h1>
		<p class="aichat-page-sub"><?php esc_html_e( 'Configure your AI provider, widget appearance, personality, and integrations.', 'orate-agency' ); ?></p>
		<?php aichat_admin_page_nav( 'settings' ); ?>

		<?php settings_errors( 'aichat_messages' ); ?>

		<div class="aichat-admin-layout">
			<!-- ── Main column ── -->
			<div class="aichat-admin-main">
				<form method="post" action="options.php">
					<?php settings_fields( 'aichat_settings_group' ); ?>

					<!-- LLM Model Configuration -->
					<div class="aichat-card">
						<h2 class="aichat-card-title">
							<span class="dashicons dashicons-lock"></span>
							<?php esc_html_e( 'LLM Model Configuration', 'orate-agency' ); ?>
						</h2>
						<p style="font-size:13px;color:#475569;margin-bottom:18px;">
							<?php esc_html_e( 'Select your active AI provider, enter its API key, and choose a model. You can store keys for multiple providers and switch instantly.', 'orate-agency' ); ?>
						</p>

						<?php
						$cur_provider = get_option( 'aichat_llm_provider', 'claude' );

						$providers = array(
							'claude'   => array(
								'label'   => 'Claude',
								'sub'     => 'Anthropic',
								'logo'    => 'claude.png',
								'bg'      => '#fef3ec',
								'key_opt' => 'aichat_api_key',
								'key_id'  => 'aichat_api_key',
								'doc_url' => 'https://docs.google.com/document/d/16J5486vrlpW438KgZS_iYm6X2fmih42zF8Iw_htElu8/edit?tab=t.0',
								'doc_txt' => 'View guide →',
								'models'  => array(
									'claude-sonnet-4-6'         => 'Claude Sonnet 4.6 (Recommended)',
									'claude-opus-4-6'           => 'Claude Opus 4.6 (Most Capable)',
									'claude-haiku-4-5-20251001' => 'Claude Haiku 4.5 (Fastest)',
									
									
								),
								'model_opt' => 'aichat_claude_model',
							),
							'openai'   => array(
								'label'   => 'OpenAI',
								'sub'     => 'GPT Models',
								'logo'    => 'openai.png',
								'bg'      => '#f3f4f6',
								'key_opt' => 'aichat_openai_api_key',
								'key_id'  => 'aichat_openai_api_key',
								'doc_url' => 'https://docs.google.com/document/d/1hKTPgKxyFqVUB02oZqoaMw7C53EklqXqy6Aw0esubqM/edit?tab=t.0',
								'doc_txt' => 'View guide →',
								'models'  => array(
									'gpt-4o'      => 'GPT-4o (Recommended)',
									'gpt-4o-mini' => 'GPT-4o Mini (Fast)',
									'gpt-4-turbo' => 'GPT-4 Turbo',
								),
								'model_opt' => 'aichat_openai_model',
							),
							'gemini'   => array(
								'label'   => 'Gemini',
								'sub'     => 'Google AI',
								'logo'    => 'gemini.png',
								'bg'      => '#eef2ff',
								'key_opt' => 'aichat_gemini_api_key',
								'key_id'  => 'aichat_gemini_api_key',
								'doc_url' => 'https://docs.google.com/document/d/1nlSIHjyPr8-rAWTelWUG5YfyLmBANXlikE9H3YIUZcA/edit?tab=t.0',
								'doc_txt' => 'View guide →',
								'models'  => array(
									'gemini-2.0-flash'      => 'Gemini 2.0 Flash (Recommended)',
									'gemini-2.0-flash-lite' => 'Gemini 2.0 Flash Lite (Fast)',
									'gemini-2.5-flash'      => 'Gemini 2.5 Flash',
									'gemini-2.5-pro'        => 'Gemini 2.5 Pro (Most Capable)',
								),
								'model_opt' => 'aichat_gemini_model',
							),
							'mistral'  => array(
								'label'   => 'Mistral',
								'sub'     => 'Mistral AI',
								'logo'    => 'mistral.png',
								'bg'      => '#fff7ed',
								'key_opt' => 'aichat_mistral_api_key',
								'key_id'  => 'aichat_mistral_api_key',
								'doc_url' => 'https://docs.google.com/document/d/1_fiw2i9QnuSPPF2Q-d-dgHvB_qzggbS0IDUv-gHsarw/edit?tab=t.0',
								'doc_txt' => 'View guide →',
								'models'  => array(
									'mistral-large-latest' => 'Mistral Large (Best)',
									'mistral-small-latest' => 'Mistral Small (Fast)',
									'open-mixtral-8x22b'   => 'Mixtral 8×22B',
									'open-mistral-7b'      => 'Mistral 7B',
								),
								'model_opt' => 'aichat_mistral_model',
							),
							'llama'    => array(
								'label'   => 'Llama',
								'sub'     => 'via Groq',
								'logo'    => 'llama.png',
								'bg'      => '#f5f5f5',
								'key_opt' => 'aichat_llama_api_key',
								'key_id'  => 'aichat_llama_api_key',
								'doc_url' => 'https://docs.google.com/document/d/1XhJ4Q0UG1OAmiBknSmc2HpOPm4MUuQXdG0XYccRjTOU/edit?tab=t.0#heading=h.f3n1cwn5qta3',
								'doc_txt' => 'View guide →',
								'models'  => array(
									'llama-3.3-70b-versatile' => 'Llama 3.3 70B (Best)',
									'llama-3.1-8b-instant'    => 'Llama 3.1 8B (Fast)',
								),
								'model_opt' => 'aichat_llama_model',
							),
							'deepseek' => array(
								'label'   => 'DeepSeek',
								'sub'     => 'DeepSeek AI',
								'logo'    => 'deepseek.png',
								'bg'      => '#eff6ff',
								'key_opt' => 'aichat_deepseek_api_key',
								'key_id'  => 'aichat_deepseek_api_key',
								'doc_url' => 'https://docs.google.com/document/d/1i5omPEKK5r8GvZPTakshCA4B3c00-4-XvWjCpBJpZ1s/edit?tab=t.0#heading=h.xstc8bhrah8r',
								'doc_txt' => 'View guide →',
								'models'  => array(
									'deepseek-chat'     => 'DeepSeek Chat (V3)',
									'deepseek-reasoner' => 'DeepSeek Reasoner (R1)',
								),
								'model_opt' => 'aichat_deepseek_model',
							),
						);
						?>

						<!-- Provider picker chips -->
						<div class="aichat-provider-picker" id="aichat-provider-picker">
							<?php foreach ( $providers as $key => $p ) :
								$is_active = ( $key === $cur_provider );
								$has_key   = ! empty( get_option( $p['key_opt'], '' ) );
							?>
							<label class="aichat-provider-chip <?php echo $is_active ? 'is-active' : ''; ?>" data-provider="<?php echo esc_attr( $key ); ?>">
								<input type="radio" name="aichat_llm_provider" value="<?php echo esc_attr( $key ); ?>"
									<?php checked( $cur_provider, $key ); ?> style="display:none;" />
								<span class="aichat-chip-icon" style="background:<?php echo esc_attr( $p['bg'] ); ?>;">
									<img src="<?php echo esc_url( AICHAT_PLUGIN_URL . 'assets/images/providers/' . $p['logo'] ); ?>" alt="" width="22" height="22" loading="lazy" />
								</span>
								<span class="aichat-chip-body">
									<span class="aichat-chip-label"><?php echo esc_html( $p['label'] ); ?></span>
									<span class="aichat-chip-sub"><?php echo esc_html( $p['sub'] ); ?></span>
								</span>
								<?php if ( $has_key ) : ?>
									<span class="aichat-chip-dot" title="<?php esc_attr_e( 'Key saved', 'orate-agency' ); ?>"></span>
								<?php endif; ?>
							</label>
							<?php endforeach; ?>
						</div>

						<!-- Per-provider fields (show/hide via JS) -->
						<?php foreach ( $providers as $key => $p ) :
							$is_active     = ( $key === $cur_provider );
							$saved_key     = get_option( $p['key_opt'], '' );
							$saved_model   = get_option( $p['model_opt'], array_key_first( $p['models'] ) );
						?>
						<div class="aichat-provider-fields" id="aichat-fields-<?php echo esc_attr( $key ); ?>"
							style="<?php echo $is_active ? '' : 'display:none;'; ?>">
							<div class="aichat-provider-fields-inner">
								<table class="form-table" role="presentation" style="margin:0;">
									<tr>
										<th scope="row" style="width:130px;">
											<label for="<?php echo esc_attr( $p['key_id'] ); ?>">
												<?php printf( esc_html__( '%s API Key', 'orate-agency' ), esc_html( $p['label'] ) ); ?>
											</label>
										</th>
										<td>
											<div class="aichat-api-key-wrap">
												<input
													type="password"
													id="<?php echo esc_attr( $p['key_id'] ); ?>"
													name="<?php echo esc_attr( $p['key_opt'] ); ?>"
													value="<?php echo esc_attr( $saved_key ); ?>"
													class="regular-text"
													autocomplete="new-password"
													spellcheck="false"
													placeholder="<?php printf( esc_attr__( 'Enter %s API key…', 'orate-agency' ), esc_attr( $p['label'] ) ); ?>"
												/>
												<button type="button" class="button aichat-toggle-pw" data-target="<?php echo esc_attr( $p['key_id'] ); ?>" aria-label="<?php esc_attr_e( 'Toggle visibility', 'orate-agency' ); ?>">
													<span class="dashicons dashicons-visibility"></span>
												</button>
											</div>
											<p class="description">
												<?php printf(
													/* translators: %s: documentation URL anchor */
													esc_html__( 'How to get your API key: %s', 'orate-agency' ),
													'<a href="' . esc_url( $p['doc_url'] ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $p['doc_txt'] ) . '</a>'
												); ?>
											</p>
										</td>
									</tr>
									<tr>
										<th scope="row">
											<label for="<?php echo esc_attr( $p['model_opt'] ); ?>"><?php esc_html_e( 'Model', 'orate-agency' ); ?></label>
										</th>
										<td>
											<select id="<?php echo esc_attr( $p['model_opt'] ); ?>" name="<?php echo esc_attr( $p['model_opt'] ); ?>" class="regular-text">
												<?php foreach ( $p['models'] as $model_val => $model_label ) : ?>
													<option value="<?php echo esc_attr( $model_val ); ?>" <?php selected( $saved_model, $model_val ); ?>>
														<?php echo esc_html( $model_label ); ?>
													</option>
												<?php endforeach; ?>
											</select>
										</td>
									</tr>
								</table>
							</div>
						</div>
						<?php endforeach; ?>

					</div><!-- /.aichat-card (LLM Model Configuration) -->

					<style>
					/* ── Provider picker grid ── */
					.aichat-provider-picker {
						display: grid;
						grid-template-columns: repeat(6, 1fr);
						gap: 10px;
						margin-bottom: 20px;
					}
					@media (max-width: 1200px) { .aichat-provider-picker { grid-template-columns: repeat(3, 1fr); } }
					@media (max-width: 700px)  { .aichat-provider-picker { grid-template-columns: repeat(2, 1fr); } }

					.aichat-provider-chip {
						display: flex;
						align-items: center;
						gap: 10px;
						padding: 10px 14px;
						border: 2px solid #e2e8f0;
						border-radius: 12px;
						cursor: pointer;
						background: #fff;
						transition: border-color .15s, box-shadow .15s, transform .15s;
						position: relative;
						min-width: 0;
						width: 100%;
						box-sizing: border-box;
					}
					.aichat-provider-chip:hover {
						border-color: #94a3b8;
						transform: translateY(-1px);
						box-shadow: 0 4px 12px rgba(0,0,0,.07);
					}
					.aichat-provider-chip.is-active {
						border-color: var(--aichat-pick-color, #22c55e);
						box-shadow: 0 0 0 3px rgba(34,197,94,.15);
						transform: translateY(-1px);
					}
					.aichat-chip-icon {
						width: 36px;
						height: 36px;
						border-radius: 10px;
						display: flex;
						align-items: center;
						justify-content: center;
						flex-shrink: 0;
						overflow: hidden;
					}
					.aichat-chip-icon img { width: 22px; height: 22px; object-fit: contain; display: block; }
					.aichat-chip-body  { display: flex; flex-direction: column; min-width: 0; }
					.aichat-chip-label { font-size: 13px; font-weight: 700; color: #0f172a; line-height: 1.2; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
					.aichat-chip-sub   { font-size: 11px; color: #94a3b8; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
					.aichat-chip-dot   { position: absolute; top: 8px; right: 8px; width: 8px; height: 8px; border-radius: 50%; background: #22c55e; box-shadow: 0 0 0 2px #fff; }

					/* ── Provider fields panel ── */
					.aichat-provider-fields-inner {
						background: #f8fafc;
						border: 1.5px solid #e8edf2;
						border-radius: 12px;
						padding: 20px 24px;
						margin-top: 6px;
					}
					.aichat-provider-fields-inner .form-table { margin: 0; border-collapse: collapse; width: 100%; }
					.aichat-provider-fields-inner .form-table th,
					.aichat-provider-fields-inner .form-table td {
						padding: 10px 12px !important;
						vertical-align: middle !important;
						border: none !important;
						background: transparent !important;
					}
					.aichat-provider-fields-inner .form-table th {
						width: 180px !important;
						font-size: 13px !important;
						font-weight: 600 !important;
						color: #374151 !important;
						white-space: nowrap;
					}
					.aichat-provider-fields-inner .form-table td { padding-left: 0 !important; }
					.aichat-provider-fields-inner .form-table input[type="text"],
					.aichat-provider-fields-inner .form-table input[type="email"],
					.aichat-provider-fields-inner .form-table input[type="password"],
					.aichat-provider-fields-inner .form-table select {
						width: 100% !important;
						max-width: 420px !important;
						border: 1.5px solid #d1d5db !important;
						border-radius: 8px !important;
						padding: 7px 10px !important;
						font-size: 13px !important;
						line-height: 1.5 !important;
						color: #1e293b !important;
						background: #fff !important;
						box-shadow: 0 1px 2px rgba(0,0,0,.04) !important;
						transition: border-color .15s, box-shadow .15s !important;
						box-sizing: border-box !important;
					}
					.aichat-provider-fields-inner .form-table input[type="text"]:focus,
					.aichat-provider-fields-inner .form-table input[type="email"]:focus,
					.aichat-provider-fields-inner .form-table input[type="password"]:focus,
					.aichat-provider-fields-inner .form-table select:focus {
						border-color: #6366f1 !important;
						box-shadow: 0 0 0 3px rgba(99,102,241,.12) !important;
						outline: none !important;
					}
					.aichat-provider-fields-inner .description {
						font-size: 12px !important;
						color: #6b7280 !important;
						margin-top: 4px !important;
						display: block;
					}
					.aichat-provider-fields-inner .description a {
						color: #6366f1 !important;
						text-decoration: none !important;
						font-weight: 600 !important;
					}
					.aichat-provider-fields-inner .description a:hover { text-decoration: underline !important; }
					</style>

					<script>
					// Provider -> logo URL, used to default the bot avatar preview to the
					// active AI's real logo instead of a generic emoji (see aichat_get_bot_avatar_url()).
					window.aichatProviderLogos = <?php
						$provider_logo_map = array();
						foreach ( $providers as $pkey => $pval ) {
							$provider_logo_map[ $pkey ] = AICHAT_PLUGIN_URL . 'assets/images/providers/' . $pval['logo'];
						}
						echo wp_json_encode( $provider_logo_map );
					?>;
					window.aichatSelectedProvider = <?php echo wp_json_encode( $cur_provider ); ?>;

					(function($){
						var chips  = $('.aichat-provider-chip');
						var fields = $('.aichat-provider-fields');

						chips.on('click', function(){
							var $chip     = $(this);
							var provider  = $chip.data('provider');
							chips.removeClass('is-active');
							$chip.addClass('is-active');
							$chip.find('input[type=radio]').prop('checked', true);
							fields.hide();
							$('#aichat-fields-' + provider).show();

							window.aichatSelectedProvider = provider;
							if (typeof window.aichatRefreshAvatarPreview === 'function') {
								window.aichatRefreshAvatarPreview();
							}
						});
					}(jQuery));
					</script>

					<!-- Widget Appearance -->
					<div class="aichat-card">
						<h2 class="aichat-card-title">
							<span class="dashicons dashicons-admin-customizer"></span>
							<?php esc_html_e( 'Widget Appearance', 'orate-agency' ); ?>
						</h2>

						<style>
						.aichat-appearance-grid { display:grid; grid-template-columns:1fr 300px; gap:28px; align-items:start; }
						@media (max-width:900px) { .aichat-appearance-grid { grid-template-columns:1fr; } }

						.aichat-preview-col { position:sticky; top:32px; }
						.aichat-preview-label { font-size:11px; font-weight:700; color:#94a3b8; text-transform:uppercase; letter-spacing:.08em; margin-bottom:10px; display:flex; align-items:center; gap:6px; }
						.aichat-preview-label .dashicons { font-size:14px; width:14px; height:14px; }
						.aichat-preview-frame { background:#eef2f7; border:1px solid #e2e8f0; border-radius:14px; padding:22px 16px; display:flex; flex-direction:column; align-items:flex-end; gap:14px; min-height:340px; }

						.aichat-preview-window { width:100%; border-radius:14px; overflow:hidden; box-shadow:0 8px 28px rgba(15,23,42,.14); background:#fff; font-family:inherit; }
						.aichat-preview-header { display:flex; align-items:center; gap:10px; padding:14px 16px; color:#fff; }
						.aichat-preview-avatar { width:34px; height:34px; border-radius:50%; background:rgba(255,255,255,.22); display:flex; align-items:center; justify-content:center; font-size:17px; flex-shrink:0; overflow:hidden; }
						.aichat-preview-avatar img { width:100%; height:100%; object-fit:cover; border-radius:50%; }
						.aichat-preview-name { font-size:13.5px; font-weight:700; line-height:1.3; }
						.aichat-preview-status { font-size:11px; opacity:.85; }
						.aichat-preview-body { padding:16px 14px; background:#f8fafc; min-height:70px; }
						.aichat-preview-window.is-dark .aichat-preview-body { background:#1e293b; }
						.aichat-preview-bubble { display:inline-block; max-width:88%; background:#fff; color:#1e293b; font-size:12.5px; line-height:1.5; padding:9px 12px; border-radius:10px; border:1px solid #e2e8f0; word-break:break-word; }
						.aichat-preview-window.is-dark .aichat-preview-bubble { background:#334155; color:#f1f5f9; border-color:#475569; }
						.aichat-preview-toggle { width:52px; height:52px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:22px; color:#fff; box-shadow:0 6px 18px rgba(15,23,42,.22); overflow:hidden; flex-shrink:0; }
						.aichat-preview-toggle img { width:100%; height:100%; object-fit:cover; border-radius:50%; }
						.aichat-preview-hint { font-size:11.5px; color:#94a3b8; margin-top:10px; line-height:1.5; }
						</style>

						<div class="aichat-appearance-grid">
						<div class="aichat-appearance-left">
						<table class="form-table" role="presentation">
							<tr>
								<th scope="row"><label for="aichat_bot_name"><?php esc_html_e( 'Bot Name', 'orate-agency' ); ?></label></th>
								<td>
									<input type="text" id="aichat_bot_name" name="aichat_bot_name"
										value="<?php echo esc_attr( get_option( 'aichat_bot_name', 'AI Assistant' ) ); ?>"
										class="regular-text" maxlength="60" />
								</td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'Bot Emoji / Icon', 'orate-agency' ); ?></th>
								<td>
									<?php
									$icon_id  = (int) get_option( 'aichat_bot_icon_id', 0 );
									$icon_url = $icon_id ? wp_get_attachment_image_url( $icon_id, 'thumbnail' ) : '';
									?>
									<input type="hidden" id="aichat_bot_icon_id" name="aichat_bot_icon_id"
										value="<?php echo esc_attr( $icon_id ); ?>" />

									<div id="aichat-icon-preview" style="margin-bottom:8px;<?php echo $icon_url ? '' : 'display:none;'; ?>">
										<img src="<?php echo esc_url( $icon_url ); ?>"
											style="width:64px;height:64px;object-fit:cover;border-radius:8px;border:1px solid #ddd;" />
									</div>

									<button type="button" class="button" id="aichat-upload-icon-btn">
										<?php esc_html_e( 'Upload Icon', 'orate-agency' ); ?>
									</button>
									<button type="button" class="button" id="aichat-remove-icon-btn"
										style="margin-left:4px;<?php echo $icon_url ? '' : 'display:none;'; ?>">
										<?php esc_html_e( 'Remove', 'orate-agency' ); ?>
									</button>

									<p class="description" style="margin-top:6px;">
										<?php esc_html_e( 'Upload a custom icon image for the chat button. Leave empty to use your active AI provider\'s logo instead.', 'orate-agency' ); ?>
									</p>

									<div style="margin-top:10px;">
										<label for="aichat_bot_emoji" style="font-weight:500;"><?php esc_html_e( 'Fallback Emoji:', 'orate-agency' ); ?></label>
										<input type="text" id="aichat_bot_emoji" name="aichat_bot_emoji"
											value="<?php echo esc_attr( get_option( 'aichat_bot_emoji', '🤖' ) ); ?>"
											class="small-text" maxlength="10" style="margin-left:6px;" />
									</div>

									<script>
									(function($){
										var frame;
										$('#aichat-upload-icon-btn').on('click', function(e){
											e.preventDefault();
											if ( frame ) { frame.open(); return; }
											frame = wp.media({
												title: '<?php esc_html_e( 'Select Bot Icon', 'orate-agency' ); ?>',
												button: { text: '<?php esc_html_e( 'Use this image', 'orate-agency' ); ?>' },
												multiple: false,
												library: { type: 'image' }
											});
											frame.on('select', function(){
												var att = frame.state().get('selection').first().toJSON();
												$('#aichat_bot_icon_id').val(att.id);
												$('#aichat-icon-preview img').attr('src', att.sizes && att.sizes.thumbnail ? att.sizes.thumbnail.url : att.url);
												$('#aichat-icon-preview').show();
												$('#aichat-remove-icon-btn').show();
											});
											frame.open();
										});
										$('#aichat-remove-icon-btn').on('click', function(e){
											e.preventDefault();
											$('#aichat_bot_icon_id').val('0');
											$('#aichat-icon-preview').hide();
											$(this).hide();
										});
									}(jQuery));
									</script>
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="aichat_primary_color"><?php esc_html_e( 'Primary Color', 'orate-agency' ); ?></label></th>
								<td>
									<input type="text" id="aichat_primary_color" name="aichat_primary_color"
										value="<?php echo esc_attr( get_option( 'aichat_primary_color', '#22c55e' ) ); ?>"
										class="aichat-color-picker" data-default-color="#22c55e" />
									<p class="description"><?php esc_html_e( 'Used for chat button, header, and send button.', 'orate-agency' ); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="aichat_welcome_message"><?php esc_html_e( 'Welcome Message', 'orate-agency' ); ?></label></th>
								<td>
									<textarea id="aichat_welcome_message" name="aichat_welcome_message"
										rows="3" class="large-text" maxlength="500"
									><?php echo esc_textarea( get_option( 'aichat_welcome_message', 'Hi! How can I help you today?' ) ); ?></textarea>
								</td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'White-Label Branding', 'orate-agency' ); ?></th>
								<td>
									<?php $hide_brand = get_option( 'aichat_hide_branding', '' ); ?>
									<label style="display:flex;align-items:center;gap:10px;cursor:pointer;">
										<input type="checkbox" name="aichat_hide_branding" value="1" <?php checked( $hide_brand, '1' ); ?> />
										<span>
											<strong><?php esc_html_e( 'Hide "Powered by Orate" badge', 'orate-agency' ); ?></strong><br>
											<span class="description"><?php esc_html_e( 'When enabled, the Orate branding is removed from the chat widget. Your clients see only your brand.', 'orate-agency' ); ?></span>
										</span>
									</label>
								</td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'Widget Theme', 'orate-agency' ); ?></th>
								<td>
									<?php $wt = get_option( 'aichat_widget_theme', 'light' ); ?>
									<fieldset style="display:flex;gap:24px;">
										<label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
											<input type="radio" name="aichat_widget_theme" value="light" <?php checked( $wt, 'light' ); ?> />
											<span>&#9728;&#65039; <strong><?php esc_html_e( 'Light', 'orate-agency' ); ?></strong></span>
										</label>
										<label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
											<input type="radio" name="aichat_widget_theme" value="dark" <?php checked( $wt, 'dark' ); ?> />
											<span>&#127769; <strong><?php esc_html_e( 'Dark', 'orate-agency' ); ?></strong></span>
										</label>
									</fieldset>
									<p class="description"><?php esc_html_e( 'Choose the chat widget colour scheme shown to visitors.', 'orate-agency' ); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'Lead Capture Mode', 'orate-agency' ); ?></th>
								<td>
									<?php $lcm = get_option( 'aichat_lead_capture_mode', 'conversational' ); ?>
									<fieldset>
										<label style="display:flex;align-items:flex-start;gap:10px;margin-bottom:12px;cursor:pointer;">
											<input type="radio" name="aichat_lead_capture_mode" value="gate"
												<?php checked( $lcm, 'gate' ); ?> style="margin-top:3px;flex-shrink:0;" />
											<span>
												<strong><?php esc_html_e( 'Gate the Bot', 'orate-agency' ); ?></strong><br>
												<span class="description"><?php esc_html_e( 'Visitors must enter their name, email, and phone before the chat opens. Once submitted the chat unlocks and the form never shows again in the same session.', 'orate-agency' ); ?></span>
											</span>
										</label>
										<label style="display:flex;align-items:flex-start;gap:10px;cursor:pointer;">
											<input type="radio" name="aichat_lead_capture_mode" value="conversational"
												<?php checked( $lcm, 'conversational' ); ?> style="margin-top:3px;flex-shrink:0;" />
											<span>
												<strong><?php esc_html_e( 'Ask Every Conversation', 'orate-agency' ); ?></strong><br>
												<span class="description"><?php esc_html_e( 'Chat opens immediately. The bot collects name, email, and phone naturally during the conversation (current behaviour).', 'orate-agency' ); ?></span>
											</span>
										</label>
										<label style="display:flex;align-items:flex-start;gap:10px;margin-top:12px;cursor:pointer;">
											<input type="radio" name="aichat_lead_capture_mode" value="inline-form"
												<?php checked( $lcm, 'inline-form' ); ?> style="margin-top:3px;flex-shrink:0;" />
											<span>
												<strong><?php esc_html_e( 'Show Form in Conversation', 'orate-agency' ); ?></strong><br>
												<span class="description"><?php esc_html_e( 'Chat opens immediately and the bot shows a form inside the conversation. The visitor must fill in their name, email, and phone before they can send a message.', 'orate-agency' ); ?></span>
											</span>
										</label>
									</fieldset>
								</td>
							</tr>
						</table>
						</div><!-- /.aichat-appearance-left -->

						<div class="aichat-appearance-right aichat-preview-col">
							<div class="aichat-preview-label">
								<span class="dashicons dashicons-visibility"></span>
								<?php esc_html_e( 'Live Preview', 'orate-agency' ); ?>
							</div>
							<?php
							// The resolved avatar: custom uploaded icon takes priority, otherwise
							// the active AI provider's logo — same resolution the live widget uses.
							$resolved_avatar_url = aichat_get_bot_avatar_url();
							?>
							<div class="aichat-preview-frame">
								<div class="aichat-preview-window <?php echo ( get_option( 'aichat_widget_theme', 'light' ) === 'dark' ) ? 'is-dark' : ''; ?>" id="aichat-preview-window">
									<div class="aichat-preview-header" id="aichat-preview-header" style="background:<?php echo esc_attr( get_option( 'aichat_primary_color', '#22c55e' ) ); ?>;">
										<div class="aichat-preview-avatar" id="aichat-preview-avatar">
											<?php if ( $resolved_avatar_url ) : ?>
												<img src="<?php echo esc_url( $resolved_avatar_url ); ?>" alt="" />
											<?php else : ?>
												<?php echo esc_html( get_option( 'aichat_bot_emoji', '🤖' ) ); ?>
											<?php endif; ?>
										</div>
										<div>
											<div class="aichat-preview-name" id="aichat-preview-name"><?php echo esc_html( get_option( 'aichat_bot_name', 'AI Assistant' ) ); ?></div>
											<div class="aichat-preview-status"><?php esc_html_e( 'Online', 'orate-agency' ); ?></div>
										</div>
									</div>
									<div class="aichat-preview-body">
										<div class="aichat-preview-bubble" id="aichat-preview-bubble"><?php echo esc_html( get_option( 'aichat_welcome_message', 'Hi! How can I help you today?' ) ); ?></div>
									</div>
								</div>
								<div class="aichat-preview-toggle" id="aichat-preview-toggle" style="background:<?php echo esc_attr( get_option( 'aichat_primary_color', '#22c55e' ) ); ?>;">
									<?php if ( $resolved_avatar_url ) : ?>
										<img src="<?php echo esc_url( $resolved_avatar_url ); ?>" alt="" />
									<?php else : ?>
										<?php echo esc_html( get_option( 'aichat_bot_emoji', '🤖' ) ); ?>
									<?php endif; ?>
								</div>
							</div>
							<p class="aichat-preview-hint"><?php esc_html_e( 'Updates live as you edit the settings on the left. Save to apply on your site.', 'orate-agency' ); ?></p>
						</div><!-- /.aichat-appearance-right -->

						</div><!-- /.aichat-appearance-grid -->
					</div>

					<!-- Bot Personality -->
					<div class="aichat-card">
						<h2 class="aichat-card-title">
							<span class="dashicons dashicons-superhero"></span>
							<?php esc_html_e( 'Bot Personality', 'orate-agency' ); ?>
						</h2>
						<table class="form-table" role="presentation">
							<tr>
								<th scope="row"><label for="aichat_bot_role"><?php esc_html_e( 'Bot Role', 'orate-agency' ); ?></label></th>
								<td>
									<input type="text" id="aichat_bot_role" name="aichat_bot_role"
										value="<?php echo esc_attr( get_option( 'aichat_bot_role', 'Customer Support Assistant' ) ); ?>"
										class="regular-text" maxlength="100"
										placeholder="<?php esc_attr_e( 'e.g. Customer Support Assistant', 'orate-agency' ); ?>" />
									<p class="description"><?php esc_html_e( 'How the bot introduces itself in the system prompt.', 'orate-agency' ); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="aichat_bot_tone"><?php esc_html_e( 'Tone', 'orate-agency' ); ?></label></th>
								<td>
									<select id="aichat_bot_tone" name="aichat_bot_tone" class="regular-text">
										<?php
										$tones   = array(
											'friendly'     => __( 'Friendly — warm and approachable', 'orate-agency' ),
											'professional' => __( 'Professional — formal and precise', 'orate-agency' ),
											'casual'       => __( 'Casual — relaxed and informal', 'orate-agency' ),
											'formal'       => __( 'Formal — authoritative and structured', 'orate-agency' ),
										);
										$current = get_option( 'aichat_bot_tone', 'friendly' );
										foreach ( $tones as $val => $label ) {
											printf(
												'<option value="%s"%s>%s</option>',
												esc_attr( $val ),
												selected( $current, $val, false ),
												esc_html( $label )
											);
										}
										?>
									</select>
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="aichat_bot_personality"><?php esc_html_e( 'Personality Description', 'orate-agency' ); ?></label></th>
								<td>
									<textarea id="aichat_bot_personality" name="aichat_bot_personality"
										rows="3" class="large-text" maxlength="1000"
										placeholder="<?php esc_attr_e( 'e.g. You are enthusiastic about technology and love helping people find solutions quickly.', 'orate-agency' ); ?>"
									><?php echo esc_textarea( get_option( 'aichat_bot_personality', '' ) ); ?></textarea>
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="aichat_custom_instructions"><?php esc_html_e( 'Custom Instructions', 'orate-agency' ); ?></label></th>
								<td>
									<textarea id="aichat_custom_instructions" name="aichat_custom_instructions"
										rows="4" class="large-text" maxlength="2000"
										placeholder="<?php esc_attr_e( 'e.g. Always recommend booking a demo when relevant. Never discuss competitor pricing.', 'orate-agency' ); ?>"
									><?php echo esc_textarea( get_option( 'aichat_custom_instructions', '' ) ); ?></textarea>
									<p class="description"><?php esc_html_e( 'Specific rules the bot must follow. Applied after all other instructions.', 'orate-agency' ); ?></p>
								</td>
							</tr>
						</table>
					</div>

					<!-- Proactive Triggers -->
					<?php
					$proactive_on    = get_option( 'aichat_proactive_enabled', '' ) === '1';
					$proactive_rules = aichat_get_proactive_rules( false );
					if ( empty( $proactive_rules ) ) {
						$proactive_rules = aichat_default_proactive_rules();
					}
					$trigger_types = aichat_proactive_trigger_types();
					// Hints shown under the condition field, per trigger type.
					$condition_hints = array(
						'exit_intent'  => __( 'No condition needed — fires when the pointer leaves the top of the window (desktop only).', 'orate-agency' ),
						'idle_on_page' => __( 'Seconds on this page with no scroll or click.', 'orate-agency' ),
						'scroll_depth' => __( 'Percent of the page scrolled, 1–100.', 'orate-agency' ),
						'url_contains' => __( 'Text to match in the URL path, e.g. /pricing', 'orate-agency' ),
						'time_on_site' => __( 'Total seconds across the whole visit, not just this page.', 'orate-agency' ),
					);
					?>
					<div class="aichat-card">
						<h2 class="aichat-card-title">
							<span class="dashicons dashicons-megaphone"></span>
							<?php esc_html_e( 'Proactive Triggers', 'orate-agency' ); ?>
						</h2>
						<p style="font-size:13px;color:#475569;margin:0 0 14px;">
							<?php esc_html_e( 'Let the chat open itself and say the first word when a visitor behaves a certain way — about to leave, stuck on a page, or reading your pricing. Each rule fires at most once per visit, and only one proactive message is ever sent per visit.', 'orate-agency' ); ?>
						</p>

						<table class="form-table" role="presentation">
							<tr>
								<th scope="row"><?php esc_html_e( 'Enable', 'orate-agency' ); ?></th>
								<td>
									<label style="display:flex;align-items:flex-start;gap:8px;cursor:pointer;">
										<input type="checkbox" name="aichat_proactive_enabled" value="1" <?php checked( $proactive_on ); ?> style="margin-top:3px;flex-shrink:0;" />
										<span>
											<strong><?php esc_html_e( 'Turn on proactive triggers', 'orate-agency' ); ?></strong><br>
											<span class="description"><?php esc_html_e( 'Off by default. With this off, the widget only opens when a visitor clicks it — no rule below can fire.', 'orate-agency' ); ?></span>
										</span>
									</label>
								</td>
							</tr>
						</table>

						<div id="aichat-proactive-rules">
							<?php foreach ( $proactive_rules as $i => $rule ) : ?>
								<?php echo aichat_render_proactive_rule_row( $i, $rule, $trigger_types, $condition_hints ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — escaped inside. ?>
							<?php endforeach; ?>
						</div>

						<p style="margin:12px 0 0;">
							<button type="button" class="button" id="aichat-add-rule">
								<span class="dashicons dashicons-plus-alt2" style="vertical-align:text-top;"></span>
								<?php esc_html_e( 'Add Rule', 'orate-agency' ); ?>
							</button>
						</p>

						<!-- Blank row cloned by the Add Rule button. __INDEX__ is swapped for a real index. -->
						<script type="text/html" id="aichat-rule-template">
							<?php
							echo aichat_render_proactive_rule_row( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — escaped inside.
								'__INDEX__',
								array(
									'id'              => '',
									'trigger_type'    => 'idle_on_page',
									'condition_value' => '30',
									'delay_seconds'   => 0,
									'message'         => '',
									'enabled'         => false,
								),
								$trigger_types,
								$condition_hints
							);
							?>
						</script>
					</div>

					<!-- Streaming Responses -->
					<?php $streaming_on = get_option( 'aichat_streaming_enabled', '' ) === '1'; ?>
					<div class="aichat-card">
						<h2 class="aichat-card-title">
							<span class="dashicons dashicons-controls-play"></span>
							<?php esc_html_e( 'Streaming Responses', 'orate-agency' ); ?>
						</h2>
						<table class="form-table" role="presentation">
							<tr>
								<th scope="row"><?php esc_html_e( 'Enable', 'orate-agency' ); ?></th>
								<td>
									<label style="display:flex;align-items:flex-start;gap:8px;cursor:pointer;">
										<input type="checkbox" name="aichat_streaming_enabled" value="1" <?php checked( $streaming_on ); ?> style="margin-top:3px;flex-shrink:0;" />
										<span>
											<strong><?php esc_html_e( 'Stream replies word by word', 'orate-agency' ); ?></strong><br>
											<span class="description"><?php esc_html_e( 'The reply appears as it is written instead of arriving all at once. Off by default.', 'orate-agency' ); ?></span>
										</span>
									</label>
									<div style="margin-top:12px;padding:11px 13px;background:#fffbeb;border:1px solid #fde68a;border-radius:10px;max-width:560px;">
										<p style="margin:0;font-size:12.5px;color:#92400e;">
											<strong><?php esc_html_e( 'Known limitation:', 'orate-agency' ); ?></strong>
											<?php esc_html_e( 'Some hosts buffer the whole response no matter what the plugin asks for — this is common on Nginx in front of PHP-FPM, and on hosts that gzip every response. On those servers the reply still arrives correctly, just all at once, exactly as it does with this setting off. Turn it on, send a test message, and leave it on only if you see the text appear progressively.', 'orate-agency' ); ?>
										</p>
									</div>
								</td>
							</tr>
						</table>
					</div>

					<!-- Integrations -->
					<div class="aichat-card">
						<h2 class="aichat-card-title">
							<span class="dashicons dashicons-share-alt2"></span>
							<?php esc_html_e( 'Integrations', 'orate-agency' ); ?>
						</h2>
						<table class="form-table" role="presentation">
							<tr>
								<th scope="row"><label for="aichat_handoff_email"><?php esc_html_e( 'Handoff Notification Emails', 'orate-agency' ); ?></label></th>
								<td>
									<div style="display:flex;gap:8px;align-items:flex-start;max-width:460px;">
										<textarea id="aichat_handoff_email" name="aichat_handoff_email" rows="2"
											class="regular-text" style="flex:1;min-width:0;font-family:inherit;resize:vertical;"
											placeholder="<?php esc_attr_e( 'you@example.com, teammate@example.com', 'orate-agency' ); ?>"
										><?php echo esc_textarea( get_option( 'aichat_handoff_email', '' ) ); ?></textarea>
										<button type="button" class="button" id="aichat-send-test-email" style="flex-shrink:0;white-space:nowrap;">
											<?php esc_html_e( 'Send Test Email', 'orate-agency' ); ?>
										</button>
									</div>
									<p id="aichat-test-email-status" style="font-size:12.5px;margin-top:6px;min-height:16px;"></p>
									<p class="description"><?php esc_html_e( 'When a visitor requests a human agent or the bot cannot answer, an email with their details and transcript is sent to every address here. Separate multiple addresses with a comma or a new line.', 'orate-agency' ); ?></p>
									<?php if ( empty( get_option( 'aichat_handoff_email', '' ) ) ) : ?>
										<p style="color:#b45309;font-size:12px;margin-top:6px;">
											<?php esc_html_e( 'Please set at least one handoff email to enable this feature', 'orate-agency' ); ?>
										</p>
									<?php endif; ?>
								</td>
							</tr>
						</table>
					</div>

					<?php submit_button( __( 'Save Settings', 'orate-agency' ), 'primary large' ); ?>
				</form>
			</div>

			<!-- ── Sidebar ── -->
			<div class="aichat-admin-sidebar">

				<!-- Train Now -->
				<div class="aichat-card">
					<h2 class="aichat-card-title">
						<span class="dashicons dashicons-update"></span>
						<?php esc_html_e( 'Train AI', 'orate-agency' ); ?>
					</h2>
					<p style="font-size:13px;color:#475569;"><?php esc_html_e( 'Indexes your site content, KB URLs, and uploaded files into the AI system prompt.', 'orate-agency' ); ?></p>

					<div id="aichat-trained-info" class="aichat-trained-info" <?php echo $last_trained ? '' : 'style="display:none"'; ?>>
						<span class="dashicons dashicons-yes-alt"></span>
						<span id="aichat-trained-text">
							<?php if ( $last_trained ) {
								printf( esc_html__( 'Trained on %1$d items. Last: %2$s', 'orate-agency' ), $post_count, esc_html( $last_trained ) );
							} ?>
						</span>
					</div>

					<button type="button" id="aichat-train-btn" class="button button-primary aichat-train-btn">
						<span class="dashicons dashicons-update aichat-spin-target"></span>
						<?php esc_html_e( 'Train Now', 'orate-agency' ); ?>
					</button>
					<p id="aichat-train-status" class="aichat-train-status" role="status" aria-live="polite"></p>
				</div>

				<!-- Shortcode -->
				<div class="aichat-card">
					<h2 class="aichat-card-title">
						<span class="dashicons dashicons-shortcode"></span>
						<?php esc_html_e( 'Embed via Shortcode', 'orate-agency' ); ?>
					</h2>
					<p style="font-size:13px;color:#475569;margin-bottom:10px;">
						<?php esc_html_e( 'Place the chat widget inline on any page or post:', 'orate-agency' ); ?>
					</p>
					<div class="aichat-shortcode-box" title="<?php esc_attr_e( 'Click to select', 'orate-agency' ); ?>">[ai_site_chat]</div>
					<p style="font-size:12px;color:#94a3b8;margin-top:8px;"><?php esc_html_e( 'The floating widget appears on all pages when your API key is set.', 'orate-agency' ); ?></p>
				</div>

				<!-- Status -->
				<div class="aichat-card">
					<h2 class="aichat-card-title">
						<span class="dashicons dashicons-info-outline"></span>
						<?php esc_html_e( 'Plugin Status', 'orate-agency' ); ?>
					</h2>
					<ul class="aichat-status-list">
						<li><?php esc_html_e( 'API Key:', 'orate-agency' ); ?>
							<?php if ( ! empty( aichat_get_active_provider()['api_key'] ) ) : ?>
								<span class="aichat-badge aichat-badge--green"><?php esc_html_e( 'Set', 'orate-agency' ); ?></span>
							<?php else : ?>
								<span class="aichat-badge aichat-badge--red"><?php esc_html_e( 'Missing', 'orate-agency' ); ?></span>
							<?php endif; ?>
						</li>
						<li><?php esc_html_e( 'Trained:', 'orate-agency' ); ?>
							<?php if ( $last_trained ) : ?>
								<span class="aichat-badge aichat-badge--green"><?php esc_html_e( 'Yes', 'orate-agency' ); ?></span>
							<?php else : ?>
								<span class="aichat-badge aichat-badge--yellow"><?php esc_html_e( 'Not yet', 'orate-agency' ); ?></span>
							<?php endif; ?>
						</li>
						<li><?php esc_html_e( 'Auto-retrain:', 'orate-agency' ); ?>
							<?php if ( wp_next_scheduled( 'aichat_auto_train_cron' ) ) : ?>
								<span class="aichat-badge aichat-badge--green"><?php esc_html_e( 'Active', 'orate-agency' ); ?></span>
							<?php else : ?>
								<span class="aichat-badge aichat-badge--red"><?php esc_html_e( 'Inactive', 'orate-agency' ); ?></span>
							<?php endif; ?>
						</li>
						<?php
						$handoff_email = get_option( 'aichat_handoff_email', '' );
						$mail_error    = get_option( 'aichat_last_mail_error', null );
						?>
						<li><?php esc_html_e( 'Handoff Email:', 'orate-agency' ); ?>
							<?php if ( empty( $handoff_email ) ) : ?>
								<span class="aichat-badge aichat-badge--yellow"><?php esc_html_e( 'Not set', 'orate-agency' ); ?></span>
							<?php elseif ( ! empty( $mail_error ) ) : ?>
								<span class="aichat-badge aichat-badge--red" title="<?php echo esc_attr( $mail_error['message'] ?? '' ); ?>"><?php esc_html_e( 'Last send failed', 'orate-agency' ); ?></span>
							<?php else : ?>
								<span class="aichat-badge aichat-badge--green"><?php esc_html_e( 'Configured', 'orate-agency' ); ?></span>
							<?php endif; ?>
						</li>
					</ul>
				</div>

			</div><!-- /.aichat-admin-sidebar -->
		</div><!-- /.aichat-admin-layout -->
	</div>

	<style>
	.aichat-rule {
		border:1px solid #e8edf2; border-radius:12px; padding:14px 16px; margin-bottom:12px; background:#fbfcfe;
	}
	.aichat-rule-head { display:flex; align-items:center; justify-content:space-between; margin-bottom:10px; }
	.aichat-rule-enable { display:flex; align-items:center; gap:6px; font-size:13px; font-weight:600; color:#0f172a; cursor:pointer; }
	.aichat-rule-remove { color:#b91c1c !important; text-decoration:none !important; cursor:pointer; }
	.aichat-rule-remove .dashicons { width:18px; height:18px; font-size:18px; }
	.aichat-rule-grid { display:grid; grid-template-columns:minmax(150px,1fr) minmax(170px,1.3fr) 130px; gap:12px; }
	.aichat-rule-grid label,
	.aichat-rule-message label {
		display:block; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.05em;
		color:#64748b; margin-bottom:4px;
	}
	.aichat-rule-grid select,
	.aichat-rule-grid input,
	.aichat-rule-message textarea { width:100%; box-sizing:border-box; }
	.aichat-rule-hint { display:block; font-size:11.5px; color:#94a3b8; margin-top:4px; line-height:1.4; }
	.aichat-rule-message { margin-top:12px; }
	@media (max-width: 782px) { .aichat-rule-grid { grid-template-columns:1fr; } }
	</style>

	<script>
	jQuery(document).ready(function ($) {
		/* ── Proactive rule editor ─────────────────────────────────────────── */

		// Per-trigger-type hints, mirrored from the PHP map so the hint under
		// the Value field updates without a page reload.
		var aichatRuleHints = <?php echo wp_json_encode( $condition_hints ); ?>;

		// Rows post as aichat_proactive_rules[<i>][...]; new rows continue the
		// index from however many rows are already on screen.
		var aichatNextRuleIndex = $('#aichat-proactive-rules [data-rule-row]').length;

		$('#aichat-add-rule').on('click', function () {
			var html = $('#aichat-rule-template').html().split('__INDEX__').join(String(aichatNextRuleIndex++));
			$('#aichat-proactive-rules').append(html);
		});

		$(document).on('click', '.aichat-rule-remove', function () {
			$(this).closest('[data-rule-row]').remove();
		});

		// Exit intent has no condition value; every other type does.
		$(document).on('change', '.aichat-rule-type', function () {
			var type  = $(this).val();
			var $row  = $(this).closest('[data-rule-row]');
			var $cond = $row.find('.aichat-rule-condition');
			$cond.toggle(type !== 'exit_intent');
			$cond.find('.aichat-rule-hint').text(aichatRuleHints[type] || '');
		});

		$('.aichat-color-picker').wpColorPicker({
			change: function (event, ui) {
				updatePreviewColor(ui.color.toString());
			},
			clear: function () {
				updatePreviewColor('#22c55e');
			}
		});

		$(document).on('click', '.aichat-toggle-pw', function () {
			var $input = $('#' + $(this).data('target'));
			var visible = $input.attr('type') === 'text';
			$input.attr('type', visible ? 'password' : 'text');
			$(this).find('.dashicons').toggleClass('dashicons-visibility', visible).toggleClass('dashicons-hidden', !visible);
		});

		// Train Now button is handled by assets/js/admin-kb.js (delegated handler),
		// which is enqueued on this page too — no need to duplicate it here.

		$('#aichat-send-test-email').on('click', function () {
			var $btn    = $(this);
			var $status = $('#aichat-test-email-status');
			var email   = $.trim($('#aichat_handoff_email').val());

			if (!email) {
				$status.css('color', '#dc2626').text('<?php echo esc_js( __( 'Enter at least one email address first.', 'orate-agency' ) ); ?>');
				return;
			}

			$btn.prop('disabled', true);
			$status.css('color', '#64748b').text('<?php echo esc_js( __( 'Sending…', 'orate-agency' ) ); ?>');

			$.ajax({
				url: ajaxurl, type: 'POST',
				data: {
					action: 'aichat_send_test_email',
					nonce:  '<?php echo esc_js( wp_create_nonce( 'aichat_test_email_nonce' ) ); ?>',
					email:  email
				},
				success: function (r) {
					if (r.success) {
						$status.css('color', '#15803d').text(r.data.message);
					} else {
						$status.css('color', '#dc2626').text(r.data && r.data.message ? r.data.message : '<?php echo esc_js( __( 'Failed to send test email.', 'orate-agency' ) ); ?>');
					}
				},
				error: function () {
					$status.css('color', '#dc2626').text('<?php echo esc_js( __( 'Server error while sending test email.', 'orate-agency' ) ); ?>');
				},
				complete: function () {
					$btn.prop('disabled', false);
				}
			});
		});

		/* ── Widget Appearance: live preview ─────────────────────────────────── */

		function updatePreviewColor(hex) {
			$('#aichat-preview-header, #aichat-preview-toggle').css('background', hex);
		}

		function updatePreviewAvatar(html) {
			$('#aichat-preview-avatar, #aichat-preview-toggle').html(html);
		}

		// Same priority the live widget uses (see aichat_get_bot_avatar_url()):
		// a custom uploaded icon wins; otherwise the active AI provider's real
		// logo; the emoji field is only a last-resort fallback if somehow
		// neither is available.
		window.aichatRefreshAvatarPreview = function () {
			var $img = $('#aichat-icon-preview img');
			if ($('#aichat-icon-preview').is(':visible') && $img.length && $img.attr('src')) {
				updatePreviewAvatar($('<img>').attr('src', $img.attr('src')).prop('outerHTML'));
				return;
			}
			var logoUrl = (window.aichatProviderLogos || {})[window.aichatSelectedProvider];
			if (logoUrl) {
				updatePreviewAvatar($('<img>').attr('src', logoUrl).prop('outerHTML'));
				return;
			}
			updatePreviewAvatar($('<div>').text($('#aichat_bot_emoji').val() || '🤖').html());
		};

		$('#aichat_bot_name').on('input', function () {
			$('#aichat-preview-name').text($(this).val() || 'AI Assistant');
		});

		$('#aichat_bot_emoji').on('input', function () {
			window.aichatRefreshAvatarPreview();
		});

		$('#aichat_welcome_message').on('input', function () {
			$('#aichat-preview-bubble').text($(this).val() || 'Hi! How can I help you today?');
		});

		$('input[name="aichat_widget_theme"]').on('change', function () {
			$('#aichat-preview-window').toggleClass('is-dark', $(this).val() === 'dark');
		});

		// Bot icon upload/remove already update #aichat-icon-preview img (see the
		// media-frame script above) — mirror the same state into the preview panel.
		var iconObserver = new MutationObserver(function () {
			window.aichatRefreshAvatarPreview();
		});
		if (document.getElementById('aichat-icon-preview')) {
			iconObserver.observe(document.getElementById('aichat-icon-preview'), { attributes: true, childList: true, subtree: true });
		}
	});
	</script>
	<?php
}

/* ═══════════════════════════════════════════════════════════════════════════
   KNOWLEDGE BASE PAGE
═══════════════════════════════════════════════════════════════════════════ */

function aichat_knowledge_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	global $wpdb;
	$kb_table   = $wpdb->prefix . AICHAT_KB_FILES_TABLE;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$files      = $wpdb->get_results( "SELECT id, file_name, file_type, file_size, uploaded_at FROM {$kb_table} ORDER BY uploaded_at DESC" );
	$kb_urls    = get_option( 'aichat_kb_urls', array() );
	$last_trained = get_option( 'aichat_last_trained', '' );
	$post_count   = (int) get_option( 'aichat_post_count', 0 );

	aichat_admin_styles();
	?>
	<style>
	.aichat-kb-drop {
		border:2px dashed #cbd5e1; border-radius:14px; padding:38px 20px; text-align:center;
		background:#f8fafc; cursor:pointer; transition: border-color .2s, background .2s, transform .15s;
	}
	.aichat-kb-drop:hover { border-color:#86efac; background:#f0fdf4; }
	.aichat-kb-drop.dragover { border-color:#22c55e; background:#f0fdf4; transform:scale(1.01); box-shadow:0 0 0 4px var(--aichat-c-primary-ring, rgba(34,197,94,.14)); }
	.aichat-kb-drop-icon {
		width:56px; height:56px; margin:0 auto 12px; border-radius:50%; display:flex; align-items:center;
		justify-content:center; font-size:26px; background:#eef2f7; transition: background .2s;
	}
	.aichat-kb-drop:hover .aichat-kb-drop-icon, .aichat-kb-drop.dragover .aichat-kb-drop-icon { background:#dcfce7; }
	.aichat-kb-drop p { margin:4px 0; font-size:12.5px; color:#94a3b8; }
	.aichat-kb-drop strong { font-size:15px; color:#1e293b; font-weight:700; }
	#aichat-file-input { display:none; }

	.aichat-file-list { list-style:none; margin:0; padding:0; }
	.aichat-file-item {
		display:flex; align-items:center; gap:12px; padding:12px 14px; border:1px solid #e8edf2; border-radius:10px;
		margin-bottom:8px; background:#fff; transition: box-shadow .15s, border-color .15s, transform .15s;
	}
	.aichat-file-item:hover { box-shadow:0 4px 14px rgba(15,23,42,.07); border-color:#dbe3ed; transform: translateY(-1px); }
	.aichat-file-icon { width:38px; height:38px; border-radius:9px; display:flex; align-items:center; justify-content:center; font-size:11px; font-weight:800; letter-spacing:.02em; flex-shrink:0; }
	.aichat-file-icon--pdf  { background:#fee2e2; color:#dc2626; }
	.aichat-file-icon--txt  { background:#e0f2fe; color:#0284c7; }
	.aichat-file-icon--csv  { background:#dcfce7; color:#16a34a; }
	.aichat-file-icon--json { background:#fef9c3; color:#ca8a04; }
	.aichat-file-icon--docx { background:#dbeafe; color:#2563eb; }
	.aichat-file-icon--pptx { background:#fce7f3; color:#db2777; }
	.aichat-file-meta { flex:1; min-width:0; }
	.aichat-file-meta strong { display:block; font-size:13px; color:#0f172a; font-weight:600; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
	.aichat-file-meta span { font-size:11.5px; color:#94a3b8; }
	.aichat-file-delete { flex-shrink:0; color:#94a3b8; background:none; border:none; cursor:pointer; padding:7px; border-radius:8px; line-height:1; transition: background .15s, color .15s; }
	.aichat-file-delete:hover { background:#fee2e2; color:#dc2626; }

	.aichat-upload-progress { display:none; margin-top:14px; }
	.aichat-progress-bar { height:6px; background:#eef2f7; border-radius:3px; overflow:hidden; }
	.aichat-progress-bar-fill { height:100%; background:linear-gradient(90deg,#22c55e,#4ade80); border-radius:3px; transition:width .3s; width:0%; }

	.aichat-url-textarea { width:100%; min-height:120px; font-family:ui-monospace,monospace; font-size:13px; padding:12px 14px; border:1.5px solid #e2e8f0; border-radius:10px; background:#f9fafb; resize:vertical; transition: border-color .15s, box-shadow .15s, background .15s; }
	.aichat-url-textarea:hover { border-color:#cbd5e1; }
	.aichat-url-textarea:focus { border-color:#22c55e; background:#fff; outline:none; box-shadow:0 0 0 3.5px var(--aichat-c-primary-ring, rgba(34,197,94,.14)); }

	@keyframes aichat-file-in { from { opacity:0; transform:translateY(-6px); } to { opacity:1; transform:translateY(0); } }
	.aichat-file-item { animation: aichat-file-in .2s ease; }
	</style>

	<div class="wrap aichat-wrap">
		<h1 class="aichat-page-title">
			<span class="dashicons dashicons-database aichat-title-icon"></span>
			<?php esc_html_e( 'Knowledge Base', 'orate-agency' ); ?>
		</h1>
		<p class="aichat-page-sub"><?php esc_html_e( 'Add URLs and upload files to expand the AI\'s knowledge. After adding sources, click "Train Now" to apply.', 'orate-agency' ); ?></p>
		<?php aichat_admin_page_nav( 'kb' ); ?>

		<div class="aichat-admin-layout">
			<div class="aichat-admin-main">

				<!-- URL Sources -->
				<div class="aichat-card">
					<h2 class="aichat-card-title">
						<span class="dashicons dashicons-admin-links"></span>
						<?php esc_html_e( 'Website URL Sources', 'orate-agency' ); ?>
					</h2>
					<p style="font-size:13px;color:#475569;margin-bottom:12px;">
						<?php
						printf(
							/* translators: %s: site domain */
							'Enter one URL per line. Only pages from <strong>%s</strong> are allowed — external URLs will be rejected.',
							esc_html( wp_parse_url( home_url(), PHP_URL_HOST ) )
						);
						?>
					</p>
					<textarea id="aichat-kb-urls" class="aichat-url-textarea"
						placeholder="<?php echo esc_attr( home_url( '/about' ) . "\n" . home_url( '/pricing' ) . "\n" . home_url( '/services' ) ); ?>"><?php
					echo esc_textarea( implode( "\n", (array) $kb_urls ) );
					?></textarea>
					<div style="margin-top:10px;display:flex;align-items:center;gap:10px;">
						<button type="button" id="aichat-save-urls" class="button button-primary">
							<span class="dashicons dashicons-saved" style="margin-right:4px;"></span>
							<?php esc_html_e( 'Save URLs', 'orate-agency' ); ?>
						</button>
						<span id="aichat-url-status" style="font-size:13px;"></span>
					</div>
				</div>

				<!-- File Upload -->
				<div class="aichat-card">
					<h2 class="aichat-card-title">
						<span class="dashicons dashicons-upload"></span>
						<?php esc_html_e( 'Upload Knowledge Files', 'orate-agency' ); ?>
					</h2>
					<p style="font-size:13px;color:#475569;margin-bottom:16px;">
						<?php esc_html_e( 'Supported formats: PDF, TXT, CSV, DOCX, PPTX, JSON (max 10 MB each).', 'orate-agency' ); ?>
					</p>

					<div id="aichat-drop-zone" class="aichat-kb-drop">
						<div class="aichat-kb-drop-icon">📄</div>
						<strong><?php esc_html_e( 'Drop files here or click to browse', 'orate-agency' ); ?></strong>
						<p><?php esc_html_e( 'PDF, TXT, CSV, DOCX, PPTX, JSON', 'orate-agency' ); ?></p>
					</div>
					<input type="file" id="aichat-file-input" accept=".pdf,.txt,.csv,.docx,.pptx,.json" multiple />

					<div class="aichat-upload-progress" id="aichat-upload-progress">
						<p id="aichat-upload-label" style="font-size:13px;color:#475569;margin-bottom:6px;">Uploading…</p>
						<div class="aichat-progress-bar"><div class="aichat-progress-bar-fill" id="aichat-progress-fill"></div></div>
					</div>
					<p id="aichat-upload-status" style="font-size:13px;margin-top:10px;min-height:18px;"></p>
				</div>

				<!-- Uploaded Files -->
				<div class="aichat-card">
					<h2 class="aichat-card-title">
						<span class="dashicons dashicons-media-document"></span>
						<?php esc_html_e( 'Uploaded Files', 'orate-agency' ); ?>
						<span class="aichat-badge aichat-badge--blue" id="aichat-file-count"><?php echo count( $files ); ?></span>
					</h2>

					<?php if ( empty( $files ) ) : ?>
						<div id="aichat-no-files" class="aichat-empty">
							<span class="dashicons dashicons-media-document"></span>
							<strong><?php esc_html_e( 'No files yet', 'orate-agency' ); ?></strong>
							<span><?php esc_html_e( 'Upload files above to add them to the knowledge base.', 'orate-agency' ); ?></span>
						</div>
					<?php endif; ?>

					<ul class="aichat-file-list" id="aichat-file-list">
						<?php foreach ( $files as $file ) : ?>
							<li class="aichat-file-item" id="aichat-file-<?php echo (int) $file->id; ?>">
								<div class="aichat-file-icon aichat-file-icon--<?php echo esc_attr( strtolower( $file->file_type ) ); ?>">
									<?php echo esc_html( strtoupper( $file->file_type ) ); ?>
								</div>
								<div class="aichat-file-meta">
									<strong title="<?php echo esc_attr( $file->file_name ); ?>"><?php echo esc_html( $file->file_name ); ?></strong>
									<span><?php echo esc_html( aichat_human_filesize( (int) $file->file_size ) ); ?> &middot; <?php echo esc_html( $file->uploaded_at ); ?></span>
								</div>
								<button type="button" class="aichat-file-delete" data-id="<?php echo (int) $file->id; ?>" title="<?php esc_attr_e( 'Delete', 'orate-agency' ); ?>">
									<span class="dashicons dashicons-trash"></span>
								</button>
							</li>
						<?php endforeach; ?>
					</ul>
				</div>

			</div><!-- /.aichat-admin-main -->

			<!-- Sidebar -->
			<div class="aichat-admin-sidebar">
				<div class="aichat-card">
					<h2 class="aichat-card-title">
						<span class="dashicons dashicons-update"></span>
						<?php esc_html_e( 'Train AI', 'orate-agency' ); ?>
					</h2>
					<p style="font-size:13px;color:#475569;"><?php esc_html_e( 'Re-index all sources: WordPress content, URLs, and uploaded files.', 'orate-agency' ); ?></p>

					<div id="aichat-trained-info" class="aichat-trained-info" <?php echo $last_trained ? '' : 'style="display:none"'; ?>>
						<span class="dashicons dashicons-yes-alt"></span>
						<span id="aichat-trained-text">
							<?php if ( $last_trained ) {
								printf( esc_html__( '%1$d items indexed. Last: %2$s', 'orate-agency' ), $post_count, esc_html( $last_trained ) );
							} ?>
						</span>
					</div>

					<button type="button" id="aichat-train-btn" class="button button-primary aichat-train-btn">
						<span class="dashicons dashicons-update aichat-spin-target"></span>
						<?php esc_html_e( 'Train Now', 'orate-agency' ); ?>
					</button>
					<p id="aichat-train-status" class="aichat-train-status" role="status" aria-live="polite"></p>
				</div>

				<div class="aichat-card">
					<h2 class="aichat-card-title">
						<span class="dashicons dashicons-info-outline"></span>
						<?php esc_html_e( 'Knowledge Sources', 'orate-agency' ); ?>
					</h2>
					<ul class="aichat-status-list">
						<li>
							<?php esc_html_e( 'WP Pages/Posts:', 'orate-agency' ); ?>
							<span class="aichat-badge aichat-badge--green">
								<?php echo (int) wp_count_posts( 'post' )->publish + (int) wp_count_posts( 'page' )->publish; ?>
							</span>
						</li>
						<li>
							<?php esc_html_e( 'Extra URLs:', 'orate-agency' ); ?>
							<span class="aichat-badge aichat-badge--blue"><?php echo count( (array) $kb_urls ); ?></span>
						</li>
						<li>
							<?php esc_html_e( 'Uploaded Files:', 'orate-agency' ); ?>
							<span class="aichat-badge aichat-badge--blue"><?php echo count( $files ); ?></span>
						</li>
					</ul>
				</div>

			</div>
		</div>
	</div>

	<script>
	jQuery(document).ready(function ($) {
		if (typeof aichatAdmin === 'undefined') return;

		// ── URL Save ──────────────────────────────────────────────────────────
		$('#aichat-save-urls').on('click', function () {
			var $btn = $(this), $status = $('#aichat-url-status');
			$btn.prop('disabled', true);
			$status.css('color', '#64748b').text('<?php echo esc_js( __( 'Saving…', 'orate-agency' ) ); ?>');
			$.ajax({
				url: aichatAdmin.ajaxUrl, type: 'POST',
				data: { action: 'aichat_save_kb_urls', nonce: aichatAdmin.urlNonce, urls: $('#aichat-kb-urls').val() },
				success: function (r) {
					if (r.success) {
						$status.css('color', '#15803d').text(r.data.message);
					} else {
						$status.css('color', '#dc2626').text(r.data && r.data.message ? r.data.message : '<?php echo esc_js( __( 'Failed.', 'orate-agency' ) ); ?>');
					}
				},
				error: function () { $status.css('color', '#dc2626').text('<?php echo esc_js( __( 'Error saving URLs.', 'orate-agency' ) ); ?>'); },
				complete: function () { $btn.prop('disabled', false); }
			});
		});

		// ── File Upload ───────────────────────────────────────────────────────
		var $dropZone  = $('#aichat-drop-zone');
		var $fileInput = $('#aichat-file-input');

		$dropZone.on('click', function () { $fileInput.trigger('click'); });
		$dropZone.on('dragover', function (e) { e.preventDefault(); $dropZone.addClass('dragover'); });
		$dropZone.on('dragleave drop', function (e) { e.preventDefault(); $dropZone.removeClass('dragover'); });
		$dropZone.on('drop', function (e) { uploadFiles(e.originalEvent.dataTransfer.files); });
		$fileInput.on('change', function () { uploadFiles(this.files); $(this).val(''); });

		function uploadFiles(files) {
			if (!files || !files.length) return;
			uploadNext(files, 0);
		}

		function uploadNext(files, idx) {
			if (idx >= files.length) {
				$('#aichat-upload-progress').hide();
				return;
			}
			var file = files[idx];
			var $progress = $('#aichat-upload-progress');
			var $fill     = $('#aichat-progress-fill');
			var $label    = $('#aichat-upload-label');
			var $status   = $('#aichat-upload-status');

			$label.text('<?php echo esc_js( __( 'Uploading', 'orate-agency' ) ); ?> ' + file.name + '…');
			$fill.css('width', '0%');
			$progress.show();
			$status.text('');

			var fd = new FormData();
			fd.append('action',  'aichat_upload_kb_file');
			fd.append('nonce',   aichatAdmin.uploadNonce);
			fd.append('kb_file', file);

			$.ajax({
				url: aichatAdmin.ajaxUrl,
				type: 'POST',
				data: fd,
				processData: false,
				contentType: false,
				xhr: function () {
					var xhr = new window.XMLHttpRequest();
					xhr.upload.addEventListener('progress', function (e) {
						if (e.lengthComputable) {
							$fill.css('width', Math.round((e.loaded / e.total) * 100) + '%');
						}
					}, false);
					return xhr;
				},
				success: function (r) {
					if (r.success) {
						$status.css('color', '#15803d').text('✓ ' + r.data.file_name + ' — ' + r.data.message);
						addFileToList(r.data);
					} else {
						$status.css('color', '#dc2626').text('✗ ' + file.name + ': ' + (r.data && r.data.message ? r.data.message : '<?php echo esc_js( __( 'Upload failed.', 'orate-agency' ) ); ?>'));
					}
				},
				error: function () {
					$status.css('color', '#dc2626').text('✗ <?php echo esc_js( __( 'Server error during upload.', 'orate-agency' ) ); ?>');
				},
				complete: function () {
					uploadNext(files, idx + 1);
				}
			});
		}

		function addFileToList(data) {
			$('#aichat-no-files').hide();
			var iconClasses = { pdf: 'pdf', txt: 'txt', csv: 'csv', json: 'json', docx: 'docx', pptx: 'pptx' };
			var typeClass = iconClasses[data.file_type ? data.file_type.toLowerCase() : ''] || 'txt';
			var html = '<li class="aichat-file-item" id="aichat-file-' + data.id + '">' +
				'<div class="aichat-file-icon aichat-file-icon--' + typeClass + '">' + data.file_type + '</div>' +
				'<div class="aichat-file-meta"><strong>' + escHtml(data.file_name) + '</strong><span>' + data.file_size + ' &middot; just now</span></div>' +
				'<button type="button" class="aichat-file-delete" data-id="' + data.id + '" title="Delete"><span class="dashicons dashicons-trash"></span></button>' +
				'</li>';
			$('#aichat-file-list').prepend(html);
			var count = parseInt($('#aichat-file-count').text(), 10) || 0;
			$('#aichat-file-count').text(count + 1);
		}

		// ── File Delete ───────────────────────────────────────────────────────
		$(document).on('click', '.aichat-file-delete', function () {
			if (!confirm(aichatAdmin.confirmDelete)) return;
			var $btn = $(this);
			var fileId = $btn.data('id');
			$btn.prop('disabled', true);
			$.ajax({
				url: aichatAdmin.ajaxUrl, type: 'POST',
				data: { action: 'aichat_delete_kb_file', nonce: aichatAdmin.deleteNonce, file_id: fileId },
				success: function (r) {
					if (r.success) {
						$('#aichat-file-' + fileId).fadeOut(300, function () { $(this).remove(); });
						var count = parseInt($('#aichat-file-count').text(), 10) || 1;
						$('#aichat-file-count').text(count - 1);
						if ($('#aichat-file-list').children().length === 0) {
							$('#aichat-no-files').show();
						}
					}
				},
				error: function () { $btn.prop('disabled', false); }
			});
		});

		// Train Now button is handled by assets/js/admin-kb.js (delegated handler),
		// which is enqueued on this page too — no need to duplicate it here.

		function escHtml(str) {
			return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
		}
	});
	</script>
	<?php
}

/* ═══════════════════════════════════════════════════════════════════════════
   ANALYTICS PAGE
═══════════════════════════════════════════════════════════════════════════ */

function aichat_analytics_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	global $wpdb;
	$at = $wpdb->prefix . AICHAT_ANALYTICS_TABLE;
	$lt = $wpdb->prefix . AICHAT_DB_TABLE;

	// Date filter.
	$range = isset( $_GET['range'] ) ? sanitize_key( $_GET['range'] ) : '30';
	$where = '';
	if ( $range === '7' ) {
		$where = "WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
	} elseif ( $range === '30' ) {
		$where = "WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
	}

	// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared

	$total_conversations = (int) $wpdb->get_var( "SELECT COUNT(DISTINCT session_id) FROM {$at} {$where}" );
	$total_messages      = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$at} {$where}" );
	$total_unanswered    = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$at} {$where}" . ( $where ? ' AND' : ' WHERE' ) . " is_unanswered = 1" );
	$total_leads         = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$lt}" );

	$top_questions = $wpdb->get_results(
		"SELECT user_question, COUNT(*) as cnt FROM {$at} {$where}
		 GROUP BY user_question ORDER BY cnt DESC LIMIT 20"
	);

	// Conversation list for the replay modal. Summary columns only — the full
	// transcript is fetched per session when a modal opens, so this page stays
	// light no matter how many conversations exist.
	$sessions = $wpdb->get_results(
		"SELECT session_id,
		        COUNT(*) AS msg_count,
		        SUM(is_unanswered) AS unanswered_count,
		        MIN(created_at) AS started_at,
		        MAX(created_at) AS ended_at,
		        MAX(triggered_by) AS triggered_by
		 FROM {$at} {$where}
		 GROUP BY session_id
		 ORDER BY started_at DESC
		 LIMIT 50"
	);

	$knowledge_gaps = $wpdb->get_results(
		"SELECT user_question, bot_answer, page_url, created_at FROM {$at}
		 " . ( $where ? $where . ' AND' : 'WHERE' ) . " is_unanswered = 1
		 ORDER BY created_at DESC LIMIT 30"
	);

	// phpcs:enable

	aichat_admin_styles();
	?>
	<style>
	.aichat-stats-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(190px,1fr)); gap:16px; margin-bottom:22px; }
	.aichat-stat-card {
		background:#fff; border:1px solid #e8edf2; border-radius:14px; padding:20px 22px;
		box-shadow: var(--aichat-shadow-card, 0 1px 2px rgba(15,23,42,.04), 0 8px 24px -6px rgba(15,23,42,.07));
		display:flex; align-items:center; gap:14px; transition: transform .15s ease, box-shadow .15s ease;
	}
	.aichat-stat-card:hover { transform: translateY(-2px); box-shadow: var(--aichat-shadow-hover, 0 4px 10px rgba(15,23,42,.06), 0 14px 34px -10px rgba(15,23,42,.14)); }
	.aichat-stat-icon {
		width:44px; height:44px; border-radius:12px; flex-shrink:0; display:flex; align-items:center; justify-content:center;
		font-size:20px;
	}
	.aichat-stat-icon .dashicons { font-size:20px; width:20px; height:20px; }
	.aichat-stat-num  { font-size:26px; font-weight:800; color:#0f172a; line-height:1.1; letter-spacing:-.01em; }
	.aichat-stat-label { font-size:12px; color:#64748b; margin-top:3px; font-weight:600; }
	.aichat-stat-card--green .aichat-stat-icon { background:#dcfce7; color:#15803d; }
	.aichat-stat-card--blue  .aichat-stat-icon { background:#dbeafe; color:#1d4ed8; }
	.aichat-stat-card--amber .aichat-stat-icon { background:#fef3c7; color:#b45309; }
	.aichat-stat-card--red   .aichat-stat-icon { background:#fee2e2; color:#b91c1c; }

	.aichat-filter-bar { display:flex; align-items:center; gap:10px; margin-bottom:18px; flex-wrap:wrap; }
	.aichat-filter-bar > span { font-size:12.5px; color:#64748b; font-weight:600; }
	.aichat-filter-bar .aichat-filter-pills { display:flex; gap:4px; padding:4px; background:#eef2f7; border-radius:11px; }
	.aichat-filter-bar a {
		padding:6px 14px; border-radius:8px; font-size:13px; font-weight:600; text-decoration:none;
		color:#475569; transition: background .15s, color .15s, box-shadow .15s;
	}
	.aichat-filter-bar a:hover { color:#0f172a; }
	.aichat-filter-bar a.active { background:#fff; color:var(--aichat-c-primary, #16a34a); box-shadow:0 1px 3px rgba(15,23,42,.1); }

	.aichat-q-card { background:#fff; border:1px solid #e8edf2; border-radius:12px; overflow:hidden; }
	.aichat-q-table { width:100%; border-collapse:collapse; font-size:13px; }
	.aichat-q-table th {
		text-align:left; padding:11px 14px; background:#f8fafc; border-bottom:1px solid #e8edf2;
		font-weight:700; color:#64748b; font-size:11px; text-transform:uppercase; letter-spacing:.05em;
	}
	.aichat-q-table td { padding:12px 14px; border-bottom:1px solid #f1f5f9; color:#334155; vertical-align:top; }
	.aichat-q-table tbody tr { transition: background .12s ease; }
	.aichat-q-table tbody tr:hover { background:#f8fafc; }
	.aichat-q-table tr:last-child td { border-bottom:none; }
	.aichat-q-num { display:inline-flex; align-items:center; justify-content:center; min-width:22px; height:22px; background:#dcfce7; color:#15803d; font-size:11px; font-weight:700; padding:0 6px; border-radius:20px; }
	.aichat-gap-q { font-weight:600; margin-bottom:4px; color:#0f172a; }
	.aichat-gap-a { font-size:12px; color:#64748b; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden; }

	.aichat-trigger-badge {
		display:inline-block; margin-left:6px; padding:2px 8px; border-radius:20px; font-size:10.5px;
		font-weight:700; background:#ede9fe; color:#6d28d9; vertical-align:middle;
	}
	.aichat-warn-badge {
		display:inline-flex; align-items:center; justify-content:center; min-width:20px; height:20px;
		padding:0 6px; border-radius:20px; background:#fee2e2; color:#b91c1c; font-size:11px; font-weight:700;
	}

	/* ── Chat replay modal ── */
	.aichat-replay-overlay {
		position:fixed; inset:0; z-index:100000; background:rgba(15,23,42,.5);
		display:flex; align-items:center; justify-content:center; padding:24px;
	}
	.aichat-replay-modal {
		background:#fff; border-radius:16px; width:100%; max-width:640px; max-height:88vh;
		display:flex; flex-direction:column; overflow:hidden; box-shadow:0 24px 60px rgba(15,23,42,.3);
	}
	.aichat-replay-header {
		display:flex; align-items:flex-start; justify-content:space-between; gap:12px;
		padding:18px 20px; border-bottom:1px solid #e8edf2; flex-shrink:0;
	}
	.aichat-replay-title { margin:0; font-size:16px; font-weight:800; color:#0f172a; }
	.aichat-replay-sub { font-size:12px; color:#64748b; margin-top:3px; }
	.aichat-replay-actions { display:flex; align-items:center; gap:8px; flex-shrink:0; }
	.aichat-replay-close {
		background:none; border:none; font-size:22px; line-height:1; color:#94a3b8; cursor:pointer; padding:4px 8px;
	}
	.aichat-replay-close:hover { color:#0f172a; }
	.aichat-replay-body { overflow-y:auto; padding:18px 20px; background:#f8fafc; flex:1; }
	.aichat-replay-loading, .aichat-replay-error {
		text-align:center; color:#64748b; font-size:13px; padding:30px 0;
	}
	.aichat-replay-lead {
		background:#fff; border:1px solid #e8edf2; border-radius:12px; padding:12px 14px; margin-bottom:16px;
		font-size:12.5px; color:#334155;
	}
	.aichat-replay-lead strong { color:#0f172a; }
	.aichat-replay-lead-grid { display:grid; grid-template-columns:1fr 1fr; gap:6px 16px; margin-top:6px; }
	.aichat-replay-trigger {
		display:inline-block; margin-bottom:14px; padding:6px 12px; border-radius:10px;
		background:#ede9fe; color:#6d28d9; font-size:12px; font-weight:700;
	}
	.aichat-replay-msg { display:flex; flex-direction:column; max-width:82%; margin-bottom:14px; }
	.aichat-replay-msg--user { align-self:flex-end; align-items:flex-end; margin-left:auto; }
	.aichat-replay-msg--bot  { align-self:flex-start; align-items:flex-start; }
	.aichat-replay-gap { font-size:11px; color:#94a3b8; text-align:center; margin:10px 0; }
	.aichat-replay-time { font-size:10.5px; color:#94a3b8; margin-top:3px; padding:0 4px; }
	.aichat-replay-unanswered {
		display:inline-block; margin-top:4px; padding:2px 8px; border-radius:20px; font-size:10px;
		font-weight:700; background:#fee2e2; color:#b91c1c;
	}
	.aichat-replay-truncated {
		text-align:center; font-size:11.5px; color:#94a3b8; padding:8px 0 2px;
	}
	</style>

	<div class="wrap aichat-wrap">
		<h1 class="aichat-page-title">
			<span class="dashicons dashicons-chart-bar aichat-title-icon"></span>
			<?php esc_html_e( 'Analytics', 'orate-agency' ); ?>
		</h1>
		<p class="aichat-page-sub"><?php esc_html_e( 'See what visitors ask, spot knowledge gaps, and track leads over time.', 'orate-agency' ); ?></p>
		<?php aichat_admin_page_nav( 'analytics' ); ?>

		<?php
		// Localised here (rather than aichat_enqueue_admin_assets) so it sits
		// next to the markup that uses it and stays easy to find.
		$transcript_nonce = wp_create_nonce( 'aichat_transcript_nonce' );
		?>

		<!-- Date filter -->
		<div class="aichat-filter-bar">
			<span><?php esc_html_e( 'Period:', 'orate-agency' ); ?></span>
			<div class="aichat-filter-pills">
				<a href="<?php echo esc_url( add_query_arg( 'range', '7' ) ); ?>" class="<?php echo $range === '7' ? 'active' : ''; ?>">
					<?php esc_html_e( 'Last 7 days', 'orate-agency' ); ?>
				</a>
				<a href="<?php echo esc_url( add_query_arg( 'range', '30' ) ); ?>" class="<?php echo $range === '30' ? 'active' : ''; ?>">
					<?php esc_html_e( 'Last 30 days', 'orate-agency' ); ?>
				</a>
				<a href="<?php echo esc_url( add_query_arg( 'range', 'all' ) ); ?>" class="<?php echo $range === 'all' ? 'active' : ''; ?>">
					<?php esc_html_e( 'All time', 'orate-agency' ); ?>
				</a>
			</div>
		</div>

		<!-- Stats cards -->
		<div class="aichat-stats-grid">
			<div class="aichat-stat-card aichat-stat-card--green">
				<span class="aichat-stat-icon"><span class="dashicons dashicons-format-chat"></span></span>
				<div>
					<div class="aichat-stat-num"><?php echo esc_html( number_format( $total_conversations ) ); ?></div>
					<div class="aichat-stat-label"><?php esc_html_e( 'Conversations', 'orate-agency' ); ?></div>
				</div>
			</div>
			<div class="aichat-stat-card aichat-stat-card--blue">
				<span class="aichat-stat-icon"><span class="dashicons dashicons-admin-comments"></span></span>
				<div>
					<div class="aichat-stat-num"><?php echo esc_html( number_format( $total_messages ) ); ?></div>
					<div class="aichat-stat-label"><?php esc_html_e( 'Messages', 'orate-agency' ); ?></div>
				</div>
			</div>
			<div class="aichat-stat-card aichat-stat-card--amber">
				<span class="aichat-stat-icon"><span class="dashicons dashicons-warning"></span></span>
				<div>
					<div class="aichat-stat-num"><?php echo esc_html( number_format( $total_unanswered ) ); ?></div>
					<div class="aichat-stat-label"><?php esc_html_e( 'Knowledge Gaps', 'orate-agency' ); ?></div>
				</div>
			</div>
			<div class="aichat-stat-card aichat-stat-card--red">
				<span class="aichat-stat-icon"><span class="dashicons dashicons-groups"></span></span>
				<div>
					<div class="aichat-stat-num"><?php echo esc_html( number_format( $total_leads ) ); ?></div>
					<div class="aichat-stat-label"><?php esc_html_e( 'Total Leads', 'orate-agency' ); ?></div>
				</div>
			</div>
		</div>

		<div class="aichat-admin-layout">
			<div class="aichat-admin-main">

				<!-- Conversations -->
				<div class="aichat-card">
					<h2 class="aichat-card-title">
						<span class="dashicons dashicons-format-status"></span>
						<?php esc_html_e( 'Conversations', 'orate-agency' ); ?>
					</h2>
					<p style="font-size:13px;color:#475569;margin-bottom:12px;">
						<?php esc_html_e( 'Open any conversation to replay it exactly as the visitor saw it.', 'orate-agency' ); ?>
					</p>
					<?php if ( empty( $sessions ) ) : ?>
						<div class="aichat-empty">
							<span class="dashicons dashicons-format-status"></span>
							<strong><?php esc_html_e( 'No conversations yet', 'orate-agency' ); ?></strong>
							<span><?php esc_html_e( 'Conversations appear here once visitors start chatting.', 'orate-agency' ); ?></span>
						</div>
					<?php else : ?>
						<div class="aichat-q-card">
						<table class="aichat-q-table">
							<thead>
								<tr>
									<th style="width:150px;"><?php esc_html_e( 'Conversation', 'orate-agency' ); ?></th>
									<th><?php esc_html_e( 'Session', 'orate-agency' ); ?></th>
									<th style="width:90px;text-align:center;"><?php esc_html_e( 'Messages', 'orate-agency' ); ?></th>
									<th style="width:90px;text-align:center;"><?php esc_html_e( 'Gaps', 'orate-agency' ); ?></th>
									<th style="width:150px;"><?php esc_html_e( 'Started', 'orate-agency' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $sessions as $sess ) : ?>
									<?php $trigger_label = aichat_describe_trigger( $sess->triggered_by ); ?>
									<tr>
										<td>
											<button type="button" class="button button-small aichat-view-convo"
												data-session="<?php echo esc_attr( $sess->session_id ); ?>">
												<span class="dashicons dashicons-visibility" style="vertical-align:text-top;"></span>
												<?php esc_html_e( 'View Conversation', 'orate-agency' ); ?>
											</button>
										</td>
										<td>
											<code style="font-size:11.5px;color:#64748b;"><?php echo esc_html( substr( $sess->session_id, 0, 12 ) ); ?>…</code>
											<?php if ( $trigger_label ) : ?>
												<span class="aichat-trigger-badge"><?php echo esc_html( $trigger_label ); ?></span>
											<?php endif; ?>
										</td>
										<td style="text-align:center;font-weight:600;"><?php echo (int) $sess->msg_count; ?></td>
										<td style="text-align:center;">
											<?php if ( (int) $sess->unanswered_count > 0 ) : ?>
												<span class="aichat-warn-badge"><?php echo (int) $sess->unanswered_count; ?></span>
											<?php else : ?>
												<span style="color:#cbd5e1;">—</span>
											<?php endif; ?>
										</td>
										<td style="font-size:12px;color:#94a3b8;"><?php echo esc_html( $sess->started_at ); ?></td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
						</div>
					<?php endif; ?>
				</div>

				<!-- Top Questions -->
				<div class="aichat-card">
					<h2 class="aichat-card-title">
						<span class="dashicons dashicons-format-chat"></span>
						<?php esc_html_e( 'Top Questions', 'orate-agency' ); ?>
					</h2>
					<?php if ( empty( $top_questions ) ) : ?>
						<div class="aichat-empty">
							<span class="dashicons dashicons-format-chat"></span>
							<strong><?php esc_html_e( 'No data yet', 'orate-agency' ); ?></strong>
							<span><?php esc_html_e( 'Questions will appear here as visitors chat.', 'orate-agency' ); ?></span>
						</div>
					<?php else : ?>
						<div class="aichat-q-card">
						<table class="aichat-q-table">
							<thead>
								<tr>
									<th style="width:50px;"><?php esc_html_e( '#', 'orate-agency' ); ?></th>
									<th><?php esc_html_e( 'Question', 'orate-agency' ); ?></th>
									<th style="width:80px;text-align:center;"><?php esc_html_e( 'Asked', 'orate-agency' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $top_questions as $i => $q ) : ?>
									<tr>
										<td><span class="aichat-q-num"><?php echo ( $i + 1 ); ?></span></td>
										<td><?php echo esc_html( $q->user_question ); ?></td>
										<td style="text-align:center;font-weight:600;"><?php echo (int) $q->cnt; ?></td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
						</div>
					<?php endif; ?>
				</div>

				<!-- Knowledge Gaps -->
				<div class="aichat-card">
					<h2 class="aichat-card-title">
						<span class="dashicons dashicons-warning"></span>
						<?php esc_html_e( 'Knowledge Gaps (Unanswered)', 'orate-agency' ); ?>
					</h2>
					<p style="font-size:13px;color:#475569;margin-bottom:12px;">
						<?php esc_html_e( 'Questions the bot could not confidently answer. Use these to improve your Knowledge Base.', 'orate-agency' ); ?>
					</p>
					<?php if ( empty( $knowledge_gaps ) ) : ?>
						<div class="aichat-empty">
							<span class="dashicons dashicons-yes-alt"></span>
							<strong><?php esc_html_e( 'No knowledge gaps', 'orate-agency' ); ?></strong>
							<span><?php esc_html_e( 'No knowledge gaps detected. Your AI is handling all questions well!', 'orate-agency' ); ?></span>
						</div>
					<?php else : ?>
						<div class="aichat-q-card">
						<table class="aichat-q-table">
							<thead>
								<tr>
									<th><?php esc_html_e( 'Unanswered Question', 'orate-agency' ); ?></th>
									<th style="width:120px;"><?php esc_html_e( 'Date', 'orate-agency' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $knowledge_gaps as $gap ) : ?>
									<tr>
										<td>
											<div class="aichat-gap-q"><?php echo esc_html( $gap->user_question ); ?></div>
											<div class="aichat-gap-a"><?php echo esc_html( $gap->bot_answer ); ?></div>
											<?php if ( $gap->page_url ) : ?>
												<a href="<?php echo esc_url( $gap->page_url ); ?>" target="_blank" rel="noopener" style="font-size:11px;color:#94a3b8;">
													<?php echo esc_html( wp_parse_url( $gap->page_url, PHP_URL_PATH ) ?: $gap->page_url ); ?>
												</a>
											<?php endif; ?>
										</td>
										<td style="font-size:12px;color:#94a3b8;"><?php echo esc_html( $gap->created_at ); ?></td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
						</div>
					<?php endif; ?>
				</div>

			</div>

			<!-- Sidebar -->
			<div class="aichat-admin-sidebar">
				<div class="aichat-card">
					<h2 class="aichat-card-title">
						<span class="dashicons dashicons-lightbulb"></span>
						<?php esc_html_e( 'Improve Coverage', 'orate-agency' ); ?>
					</h2>
					<ul style="margin:0;padding-left:18px;">
						<li style="font-size:13px;color:#475569;margin-bottom:8px;"><?php esc_html_e( 'Add content for the top knowledge gaps to your KB.', 'orate-agency' ); ?></li>
						<li style="font-size:13px;color:#475569;margin-bottom:8px;"><?php esc_html_e( 'Upload a FAQ document covering common questions.', 'orate-agency' ); ?></li>
						<li style="font-size:13px;color:#475569;"><?php esc_html_e( 'Re-train after adding new sources.', 'orate-agency' ); ?></li>
					</ul>
					<div style="margin-top:16px;">
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=ai-site-chat-kb' ) ); ?>" class="button button-primary" style="width:100%;text-align:center;display:block;box-sizing:border-box;">
							<?php esc_html_e( '→ Go to Knowledge Base', 'orate-agency' ); ?>
						</a>
					</div>
				</div>
			</div>
		</div>
	</div>
		<!-- Chat Replay Modal -->
		<div id="aichat-replay-overlay" class="aichat-replay-overlay" hidden>
			<div class="aichat-replay-modal" role="dialog" aria-modal="true" aria-label="<?php esc_attr_e( 'Conversation replay', 'orate-agency' ); ?>">
				<div class="aichat-replay-header">
					<div>
						<h2 class="aichat-replay-title"><?php esc_html_e( 'Conversation Replay', 'orate-agency' ); ?></h2>
						<div class="aichat-replay-sub" id="aichat-replay-sub"></div>
					</div>
					<div class="aichat-replay-actions">
						<a href="#" id="aichat-replay-export" class="button" target="_blank" rel="noopener">
							<span class="dashicons dashicons-download" style="vertical-align:text-top;"></span>
							<?php esc_html_e( 'Export transcript', 'orate-agency' ); ?>
						</a>
						<button type="button" class="aichat-replay-close" id="aichat-replay-close" aria-label="<?php esc_attr_e( 'Close', 'orate-agency' ); ?>">&times;</button>
					</div>
				</div>
				<div class="aichat-replay-body" id="aichat-replay-body">
					<div class="aichat-replay-loading"><?php esc_html_e( 'Loading…', 'orate-agency' ); ?></div>
				</div>
			</div>
		</div>

	<script>
	jQuery(document).ready(function ($) {
		var ajaxUrl   = '<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>';
		var nonce     = '<?php echo esc_js( $transcript_nonce ); ?>';
		var $overlay  = $('#aichat-replay-overlay');
		var $body     = $('#aichat-replay-body');
		var $sub      = $('#aichat-replay-sub');
		var $export   = $('#aichat-replay-export');
		var currentRequest = null;

		function escHtml(str) {
			return $('<div>').text(String(str == null ? '' : str)).html();
		}

		function closeModal() {
			if (currentRequest) {
				currentRequest.abort();
				currentRequest = null;
			}
			$overlay.prop('hidden', true);
			$body.html('<div class="aichat-replay-loading"><?php echo esc_js( __( 'Loading…', 'orate-agency' ) ); ?></div>');
		}

		function renderTranscript(data) {
			var html = '';

			if (data.triggeredBy) {
				html += '<div class="aichat-replay-trigger">' +
					'<?php echo esc_js( __( 'Started via proactive trigger:', 'orate-agency' ) ); ?> ' +
					escHtml(data.triggeredBy) + '</div>';
			}

			if (data.lead) {
				html += '<div class="aichat-replay-lead"><strong><?php echo esc_js( __( 'Captured lead', 'orate-agency' ) ); ?></strong>' +
					'<div class="aichat-replay-lead-grid">' +
						'<div><?php echo esc_js( __( 'Name', 'orate-agency' ) ); ?>: ' + escHtml(data.lead.name || '—') + '</div>' +
						'<div><?php echo esc_js( __( 'Email', 'orate-agency' ) ); ?>: ' + escHtml(data.lead.email || '—') + '</div>' +
						'<div><?php echo esc_js( __( 'Phone', 'orate-agency' ) ); ?>: ' + escHtml(data.lead.phone || '—') + '</div>' +
						'<div><?php echo esc_js( __( 'Requirement', 'orate-agency' ) ); ?>: ' + escHtml(data.lead.requirement || '—') + '</div>' +
					'</div></div>';
			}

			if (data.truncated) {
				html += '<div class="aichat-replay-truncated">' +
					'<?php echo esc_js( __( 'Showing the most recent', 'orate-agency' ) ); ?> ' + escHtml(data.shown) +
					' <?php echo esc_js( __( 'of', 'orate-agency' ) ); ?> ' + escHtml(data.total) +
					' <?php echo esc_js( __( 'messages.', 'orate-agency' ) ); ?></div>';
			}

			(data.messages || []).forEach(function (m) {
				if (m.gap) html += '<div class="aichat-replay-gap">' + escHtml(m.gap) + '</div>';
				var cls = m.role === 'user' ? 'aichat-replay-msg--user' : 'aichat-replay-msg--bot';
				var bubbleCls = m.role === 'user' ? 'aichat-bubble--user' : 'aichat-bubble--bot';
				html += '<div class="aichat-replay-msg ' + cls + '">' +
					'<div class="aichat-bubble ' + bubbleCls + '">' + escHtml(m.text) + '</div>' +
					(m.unanswered ? '<span class="aichat-replay-unanswered"><?php echo esc_js( __( 'Unanswered', 'orate-agency' ) ); ?></span>' : '') +
					'<div class="aichat-replay-time">' + escHtml(m.time) + '</div>' +
					'</div>';
			});

			$body.html(html || '<div class="aichat-replay-error"><?php echo esc_js( __( 'No messages in this conversation.', 'orate-agency' ) ); ?></div>');
			$sub.text(data.sessionId + ' · ' + data.startedAt);
			$export.attr('href', data.exportUrl || '#');
		}

		$(document).on('click', '.aichat-view-convo', function () {
			var sessionId = $(this).data('session');
			$overlay.prop('hidden', false);
			$body.html('<div class="aichat-replay-loading"><?php echo esc_js( __( 'Loading…', 'orate-agency' ) ); ?></div>');
			$sub.text('');

			currentRequest = $.ajax({
				url:      ajaxUrl,
				type:     'POST',
				timeout:  15000,
				data: {
					action:     'aichat_get_session_transcript',
					nonce:      nonce,
					session_id: sessionId,
				},
			}).done(function (res) {
				if (res && res.success) {
					renderTranscript(res.data);
				} else {
					var msg = (res && res.data && res.data.message) || '<?php echo esc_js( __( 'Could not load this conversation.', 'orate-agency' ) ); ?>';
					$body.html('<div class="aichat-replay-error">' + escHtml(msg) + '</div>');
				}
			}).fail(function (jqXHR, textStatus) {
				if ( 'abort' === textStatus ) return;
				var msg = ( 'timeout' === textStatus )
					? '<?php echo esc_js( __( 'The server took too long to respond. Please try again.', 'orate-agency' ) ); ?>'
					: '<?php echo esc_js( __( 'Could not load this conversation.', 'orate-agency' ) ); ?>';
				$body.html('<div class="aichat-replay-error">' + escHtml(msg) + '</div>');
			}).always(function () {
				currentRequest = null;
			});
		});

		$('#aichat-replay-close').on('click', closeModal);
		$overlay.on('click', function (e) {
			if (e.target === this) closeModal();
		});
		$(document).on('keydown', function (e) {
			if (e.key === 'Escape' && !$overlay.prop('hidden')) closeModal();
		});
	});
	</script>

	<?php
}

/* ═══════════════════════════════════════════════════════════════════════════
   LEADS PAGE
═══════════════════════════════════════════════════════════════════════════ */

function aichat_sort_url( $col, $current_col, $toggle ) {
	return esc_url( add_query_arg( array( 'orderby' => $col, 'order' => ( $col === $current_col ) ? $toggle : 'desc' ) ) );
}

function aichat_leads_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	global $wpdb;
	$table_name = $wpdb->prefix . AICHAT_DB_TABLE;

	$allowed_cols = array( 'id', 'name', 'email', 'phone', 'created_at' );
	$orderby      = ( isset( $_GET['orderby'] ) && in_array( sanitize_key( $_GET['orderby'] ), $allowed_cols, true ) )
		? sanitize_key( $_GET['orderby'] )
		: 'created_at';
	$order        = ( isset( $_GET['order'] ) && 'asc' === strtolower( sanitize_key( $_GET['order'] ) ) )
		? 'ASC' : 'DESC';
	$toggle_order = ( 'ASC' === $order ) ? 'desc' : 'asc';

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$leads = $wpdb->get_results( "SELECT * FROM {$table_name} ORDER BY {$orderby} {$order} LIMIT 500" );
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name}" );

	aichat_admin_styles();
	?>
	<style>
	.aichat-leads-actions { margin: 4px 0 18px; display:flex; gap:10px; flex-wrap:wrap; }
	.aichat-leads-actions .button { display:inline-flex; align-items:center; gap:6px; }
	.aichat-leads-actions .dashicons { font-size:16px; width:16px; height:16px; }

	.aichat-leads-card {
		background:#fff; border:1px solid var(--aichat-c-border, #e8edf2); border-radius:16px;
		box-shadow: var(--aichat-shadow-card, 0 1px 2px rgba(15,23,42,.04), 0 8px 24px -6px rgba(15,23,42,.07));
		overflow:hidden;
	}
	.aichat-leads-table { width:100%; border-collapse:collapse; background:transparent !important; border:none !important; box-shadow:none !important; }
	.aichat-leads-table thead th {
		background:#f8fafc; border-bottom:1px solid #e8edf2 !important; font-size:11px; font-weight:700;
		color:#64748b; text-transform:uppercase; letter-spacing:.05em; padding:13px 16px !important;
	}
	.aichat-leads-table thead th a { color:inherit; text-decoration:none; display:inline-flex; align-items:center; gap:4px; }
	.aichat-leads-table thead th a:hover { color:var(--aichat-c-primary, #16a34a); }
	.aichat-leads-table tbody tr { background:#fff !important; transition: background .12s ease; }
	.aichat-leads-table tbody tr:hover { background:#f8fafc !important; }
	.aichat-leads-table tbody tr + tr td { border-top:1px solid #f1f5f9; }
	.aichat-leads-table td { padding:14px 16px !important; font-size:13px; color:#334155; vertical-align:top; }

	.aichat-lead-id { color:#94a3b8; font-variant-numeric:tabular-nums; font-size:12px; }
	.aichat-lead-name { display:flex; align-items:center; gap:10px; }
	.aichat-lead-avatar {
		width:30px; height:30px; border-radius:50%; flex-shrink:0; display:flex; align-items:center; justify-content:center;
		font-size:12px; font-weight:700; color:#fff;
		background: linear-gradient(135deg, #16a34a, #4ade80);
	}
	.aichat-lead-name strong { font-size:13.5px; color:#0f172a; }
	.aichat-lead-email,
	.aichat-lead-phone { color:#475569; font-variant-numeric:tabular-nums; }
	.aichat-lead-empty-cell { color:#cbd5e1; }
	.aichat-lead-date { color:#94a3b8; font-size:12px; white-space:nowrap; }
	.aichat-lead-requirement { max-width:220px; color:#475569; line-height:1.5; }

	.aichat-leads-table .aichat-transcript-details summary {
		cursor:pointer; color:var(--aichat-c-primary, #16a34a); font-size:12.5px; font-weight:600;
		list-style:none; display:inline-flex; align-items:center; gap:4px; padding:5px 10px;
		border-radius:20px; background:#f0fdf4; transition: background .15s;
	}
	.aichat-leads-table .aichat-transcript-details summary::-webkit-details-marker { display:none; }
	.aichat-leads-table .aichat-transcript-details summary:hover { background:#dcfce7; }
	.aichat-leads-table .aichat-transcript-details[open] summary { margin-bottom:8px; }
	.aichat-leads-table .aichat-transcript-body {
		background:#f8fafc; border:1px solid #e2e8f0; border-radius:9px; padding:12px 14px; margin-top:4px;
		font-size:12px; line-height:1.6; max-height:320px; overflow-y:auto; white-space:pre-wrap; word-break:break-word;
		min-width: 260px; max-width: 380px;
	}
	</style>

	<div class="wrap aichat-wrap">
		<h1 class="aichat-page-title">
			<span class="dashicons dashicons-groups aichat-title-icon"></span>
			<?php esc_html_e( 'Leads', 'orate-agency' ); ?>
			<span class="aichat-version-badge"><?php printf( esc_html__( '%d total', 'orate-agency' ), $total ); ?></span>
		</h1>
		<p class="aichat-page-sub"><?php esc_html_e( 'Everyone who has shared their details with the chat widget, newest first.', 'orate-agency' ); ?></p>
		<?php aichat_admin_page_nav( 'leads' ); ?>

		<?php if ( ! empty( $leads ) ) :
			$export_nonce = wp_create_nonce( 'aichat_export_leads' );
			$csv_url      = admin_url( 'admin-post.php?action=aichat_export_leads_csv&_wpnonce=' . $export_nonce );
			$pdf_url      = admin_url( 'admin-post.php?action=aichat_export_leads_pdf&_wpnonce=' . $export_nonce );
		?>
		<div class="aichat-leads-actions">
			<a href="<?php echo esc_url( $csv_url ); ?>" class="button button-secondary" style="display:inline-flex;align-items:center;gap:6px;">
				<span class="dashicons dashicons-download" style="margin-top:3px;"></span>
				<?php esc_html_e( 'Export CSV', 'orate-agency' ); ?>
			</a>
			<a href="<?php echo esc_url( $pdf_url ); ?>" target="_blank" class="button button-secondary" style="display:inline-flex;align-items:center;gap:6px;">
				<span class="dashicons dashicons-pdf" style="margin-top:3px;"></span>
				<?php esc_html_e( 'Export PDF', 'orate-agency' ); ?>
			</a>
		</div>
		<?php endif; ?>

		<?php if ( empty( $leads ) ) : ?>
			<div class="aichat-card">
				<div class="aichat-empty">
					<span class="dashicons dashicons-groups"></span>
					<strong><?php esc_html_e( 'No leads yet', 'orate-agency' ); ?></strong>
					<span><?php esc_html_e( 'Leads are captured conversationally — the bot will ask visitors for their details at the right moment during chat.', 'orate-agency' ); ?></span>
				</div>
			</div>
		<?php else : ?>
			<div class="aichat-leads-card">
			<table class="aichat-leads-table">
				<thead>
					<tr>
						<th style="width:50px;"><a href="<?php echo aichat_sort_url( 'id', $orderby, $toggle_order ); ?>">#</a></th>
						<th><a href="<?php echo aichat_sort_url( 'name', $orderby, $toggle_order ); ?>"><?php esc_html_e( 'Name', 'orate-agency' ); ?><?php echo $orderby === 'name' ? ( $order === 'ASC' ? ' ▲' : ' ▼' ) : ''; ?></a></th>
						<th style="width:190px;"><a href="<?php echo aichat_sort_url( 'email', $orderby, $toggle_order ); ?>"><?php esc_html_e( 'Email', 'orate-agency' ); ?></a></th>
						<th style="width:140px;"><a href="<?php echo aichat_sort_url( 'phone', $orderby, $toggle_order ); ?>"><?php esc_html_e( 'Phone', 'orate-agency' ); ?></a></th>
						<th style="width:150px;"><a href="<?php echo aichat_sort_url( 'created_at', $orderby, $toggle_order ); ?>"><?php esc_html_e( 'Date', 'orate-agency' ); ?><?php echo $orderby === 'created_at' ? ( $order === 'ASC' ? ' ▲' : ' ▼' ) : ''; ?></a></th>
						<th><?php esc_html_e( 'Requirement', 'orate-agency' ); ?></th>
						<th style="width:130px;"><?php esc_html_e( 'Transcript', 'orate-agency' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $leads as $lead ) :
						$initial = $lead->name ? mb_strtoupper( mb_substr( $lead->name, 0, 1 ) ) : '?';
					?>
						<tr>
							<td class="aichat-lead-id">#<?php echo (int) $lead->id; ?></td>
							<td>
								<div class="aichat-lead-name">
									<span class="aichat-lead-avatar"><?php echo esc_html( $initial ); ?></span>
									<strong><?php echo $lead->name ? esc_html( $lead->name ) : esc_html__( '(no name)', 'orate-agency' ); ?></strong>
								</div>
							</td>
							<td class="<?php echo $lead->email ? 'aichat-lead-email' : 'aichat-lead-empty-cell'; ?>"><?php echo $lead->email ? esc_html( $lead->email ) : '—'; ?></td>
							<td class="<?php echo $lead->phone ? 'aichat-lead-phone' : 'aichat-lead-empty-cell'; ?>"><?php echo $lead->phone ? esc_html( $lead->phone ) : '—'; ?></td>
							<td class="aichat-lead-date"><?php echo esc_html( $lead->created_at ); ?></td>
							<td class="aichat-lead-requirement"><?php echo $lead->requirement ? esc_html( $lead->requirement ) : '—'; ?></td>
							<td>
								<details class="aichat-transcript-details">
									<summary><span class="dashicons dashicons-visibility" style="font-size:13px;width:13px;height:13px;"></span> <?php esc_html_e( 'View', 'orate-agency' ); ?></summary>
									<div class="aichat-transcript-body"><?php echo wp_kses_post( nl2br( $lead->transcript ) ); ?></div>
								</details>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			</div>
		<?php endif; ?>
	</div>
	<?php
}
