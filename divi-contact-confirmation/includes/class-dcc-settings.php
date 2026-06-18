<?php
/**
 * Admin UI — tabbed page with Email Settings, Security, and Logs.
 *
 * @author  Mohammad Babaei <https://adschi.com>
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DCC_Settings {

	const MENU_SLUG = 'dcc-settings';

	public static function init() {
		add_action( 'admin_menu',            array( __CLASS__, 'add_menu' ) );
		add_action( 'admin_init',            array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_post_dcc_clear_logs', array( __CLASS__, 'handle_clear_logs' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_styles' ) );
	}

	// -------------------------------------------------------------------------
	// Menu
	// -------------------------------------------------------------------------

	public static function add_menu() {
		add_options_page(
			__( 'Divi Confirmation Email', 'divi-contact-confirmation' ),
			__( 'Divi Confirmation', 'divi-contact-confirmation' ),
			'manage_options',
			self::MENU_SLUG,
			array( __CLASS__, 'render_page' )
		);
	}

	// -------------------------------------------------------------------------
	// Settings registration
	// -------------------------------------------------------------------------

	public static function register_settings() {
		// --- Email settings ---
		$email_fields = array(
			array( 'id' => 'dcc_subject',    'label' => __( 'Email Subject', 'divi-contact-confirmation' ),      'type' => 'text' ),
			array(
				'id'    => 'dcc_body',
				'label' => __( 'Email Body', 'divi-contact-confirmation' ),
				'type'  => 'textarea',
				'desc'  => __( 'Placeholders: {name}, {email}, {site_name}, {site_url}, {date}, {time} — and any form field ID wrapped in braces.', 'divi-contact-confirmation' ),
			),
			array( 'id' => 'dcc_from_name',  'label' => __( 'From Name', 'divi-contact-confirmation' ),          'type' => 'text' ),
			array( 'id' => 'dcc_from_email', 'label' => __( 'From Email Address', 'divi-contact-confirmation' ),  'type' => 'email' ),
		);

		add_settings_section( 'dcc_email_section', '', null, 'dcc-settings-email' );

		foreach ( $email_fields as $field ) {
			register_setting( 'dcc_email_options', $field['id'], array( 'sanitize_callback' => 'sanitize_textarea_field' ) );
			add_settings_field( $field['id'], $field['label'], array( __CLASS__, 'render_field' ), 'dcc-settings-email', 'dcc_email_section', $field );
		}

		// --- Security settings ---
		$sec_fields = array(
			array(
				'id'    => 'dcc_sec_enabled',
				'label' => __( 'Enable confirmation emails', 'divi-contact-confirmation' ),
				'type'  => 'checkbox',
				'desc'  => __( 'Uncheck to disable all confirmation emails without deactivating the plugin.', 'divi-contact-confirmation' ),
			),
			array(
				'id'    => 'dcc_sec_rate_limit',
				'label' => __( 'Rate limit (per IP / hour)', 'divi-contact-confirmation' ),
				'type'  => 'number',
				'desc'  => __( 'Maximum confirmation emails sent to the same IP address per hour. Set to 0 to disable.', 'divi-contact-confirmation' ),
				'attrs' => 'min="0" max="999" style="width:80px"',
			),
			array(
				'id'    => 'dcc_sec_blocked_domains',
				'label' => __( 'Blocked email domains', 'divi-contact-confirmation' ),
				'type'  => 'text',
				'desc'  => __( 'Comma-separated. Submissions from these domains will be silently ignored. Example: tempmail.com, mailinator.com', 'divi-contact-confirmation' ),
			),
			array(
				'id'    => 'dcc_sec_blocked_keywords',
				'label' => __( 'Blocked keywords', 'divi-contact-confirmation' ),
				'type'  => 'text',
				'desc'  => __( 'Comma-separated. If any submitted field contains one of these words, the confirmation email is suppressed. Example: casino, viagra', 'divi-contact-confirmation' ),
			),
			array(
				'id'    => 'dcc_sec_check_mx',
				'label' => __( 'Require valid MX record', 'divi-contact-confirmation' ),
				'type'  => 'checkbox',
				'desc'  => __( "Only send if the recipient's domain has a valid DNS MX record. May slow down form submissions on some hosts.", 'divi-contact-confirmation' ),
			),
			array(
				'id'    => 'dcc_sec_log_blocked',
				'label' => __( 'Log blocked attempts', 'divi-contact-confirmation' ),
				'type'  => 'checkbox',
				'desc'  => __( 'Write a log entry (status = Blocked) when a submission is suppressed by a security rule.', 'divi-contact-confirmation' ),
			),
		);

		add_settings_section( 'dcc_sec_section', '', null, 'dcc-settings-security' );

		foreach ( $sec_fields as $field ) {
			register_setting(
				'dcc_security_options',
				$field['id'],
				array( 'sanitize_callback' => array( __CLASS__, 'sanitize_security_field' ) )
			);
			add_settings_field( $field['id'], $field['label'], array( __CLASS__, 'render_field' ), 'dcc-settings-security', 'dcc_sec_section', $field );
		}
	}

	public static function sanitize_security_field( $value ) {
		// Checkboxes post '1' when checked, nothing when unchecked
		return sanitize_text_field( $value );
	}

	public static function render_field( $args ) {
		$id    = esc_attr( $args['id'] );
		$value = get_option( $id, '' );
		$attrs = isset( $args['attrs'] ) ? $args['attrs'] : '';

		switch ( $args['type'] ) {
			case 'textarea':
				printf(
					'<textarea id="%1$s" name="%1$s" rows="9" class="large-text code">%2$s</textarea>',
					$id,
					esc_textarea( $value )
				);
				break;

			case 'checkbox':
				printf(
					'<input type="checkbox" id="%1$s" name="%1$s" value="1" %2$s />',
					$id,
					checked( '1', $value, false )
				);
				break;

			case 'number':
				printf(
					'<input type="number" id="%1$s" name="%1$s" value="%2$s" %3$s />',
					$id,
					esc_attr( $value ),
					$attrs // already escaped literals
				);
				break;

			default:
				printf(
					'<input type="%1$s" id="%2$s" name="%2$s" value="%3$s" class="regular-text" />',
					esc_attr( $args['type'] ),
					$id,
					esc_attr( $value )
				);
		}

		if ( ! empty( $args['desc'] ) ) {
			printf( '<p class="description">%s</p>', esc_html( $args['desc'] ) );
		}
	}

	// -------------------------------------------------------------------------
	// Clear-logs action
	// -------------------------------------------------------------------------

	public static function handle_clear_logs() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'divi-contact-confirmation' ) );
		}
		check_admin_referer( 'dcc_clear_logs' );
		DCC_Logger::clear();
		wp_safe_redirect( add_query_arg( array( 'page' => self::MENU_SLUG, 'tab' => 'logs', 'cleared' => '1' ), admin_url( 'options-general.php' ) ) );
		exit;
	}

	// -------------------------------------------------------------------------
	// Styles
	// -------------------------------------------------------------------------

	public static function enqueue_styles( $hook ) {
		if ( 'settings_page_' . self::MENU_SLUG !== $hook ) {
			return;
		}
		wp_add_inline_style( 'wp-admin', self::inline_css() );
	}

	private static function inline_css() {
		return '
			.dcc-tabs { margin-top: 1rem; }
			.dcc-tabs .nav-tab-wrapper { margin-bottom: 0; }
			.dcc-tab-panel { background: #fff; border: 1px solid #c3c4c7; border-top: none; padding: 1.5rem 1.5rem 0.5rem; }
			.dcc-log-table { border-collapse: collapse; width: 100%; }
			.dcc-log-table th, .dcc-log-table td { padding: 8px 10px; border-bottom: 1px solid #f0f0f1; font-size: 13px; }
			.dcc-log-table thead th { background: #f6f7f7; text-align: left; }
			.dcc-log-table tr:hover td { background: #f6f7f7; }
			.dcc-status-sent    { color: #00a32a; font-weight: 600; }
			.dcc-status-failed  { color: #d63638; font-weight: 600; }
			.dcc-status-blocked { color: #996800; font-weight: 600; }
			.dcc-empty { color: #777; font-style: italic; padding: 1.5rem 0; }
			.dcc-pagination { margin-top: 1rem; }
			.dcc-footer { margin-top: 1rem; padding: 0.6rem 0; border-top: 1px solid #c3c4c7; color: #777; font-size: 12px; }
			.dcc-footer a { color: #2271b1; text-decoration: none; }
			.dcc-footer a:hover { text-decoration: underline; }
		';
	}

	// -------------------------------------------------------------------------
	// Main page renderer
	// -------------------------------------------------------------------------

	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification
		$raw_tab    = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'settings';
		$valid_tabs = array( 'settings', 'security', 'logs' );
		$active_tab = in_array( $raw_tab, $valid_tabs, true ) ? $raw_tab : 'settings';
		?>
		<div class="wrap dcc-tabs">
			<h1><?php esc_html_e( 'Divi Contact Form — Confirmation Email', 'divi-contact-confirmation' ); ?></h1>

			<nav class="nav-tab-wrapper">
				<?php
				$tabs = array(
					'settings' => __( 'Email Settings', 'divi-contact-confirmation' ),
					'security' => __( 'Security', 'divi-contact-confirmation' ),
					'logs'     => __( 'Logs', 'divi-contact-confirmation' ),
				);
				foreach ( $tabs as $slug => $label ) {
					printf(
						'<a href="%s" class="nav-tab%s">%s</a>',
						esc_url( self::tab_url( $slug ) ),
						$active_tab === $slug ? ' nav-tab-active' : '',
						esc_html( $label )
					);
				}
				?>
			</nav>

			<div class="dcc-tab-panel">
				<?php
				if ( 'settings' === $active_tab ) {
					self::render_settings_tab();
				} elseif ( 'security' === $active_tab ) {
					self::render_security_tab();
				} else {
					self::render_logs_tab();
				}
				?>
			</div>

			<p class="dcc-footer">
				<?php
				printf(
					/* translators: 1: plugin version, 2: author link */
					esc_html__( 'Version %1$s &mdash; Developed by %2$s', 'divi-contact-confirmation' ),
					esc_html( DCC_VERSION ),
					'<a href="https://adschi.com" target="_blank" rel="noopener noreferrer">Mohammad Babaei</a>'
				);
				?>
			</p>
		</div>
		<?php
	}

	// -------------------------------------------------------------------------
	// Email Settings tab
	// -------------------------------------------------------------------------

	private static function render_settings_tab() {
		settings_errors( 'dcc_email_options' );
		?>
		<form method="post" action="options.php">
			<?php
			settings_fields( 'dcc_email_options' );
			do_settings_sections( 'dcc-settings-email' );
			submit_button( __( 'Save Email Settings', 'divi-contact-confirmation' ) );
			?>
		</form>

		<hr>
		<h3><?php esc_html_e( 'Available placeholders', 'divi-contact-confirmation' ); ?></h3>
		<table class="widefat striped" style="max-width:600px">
			<thead><tr>
				<th><?php esc_html_e( 'Placeholder', 'divi-contact-confirmation' ); ?></th>
				<th><?php esc_html_e( 'Replaced with', 'divi-contact-confirmation' ); ?></th>
			</tr></thead>
			<tbody>
				<?php
				$ph = array(
					'{name}'      => __( "Submitter's name (falls back to email)", 'divi-contact-confirmation' ),
					'{email}'     => __( "Submitter's email address", 'divi-contact-confirmation' ),
					'{site_name}' => __( 'Your site name', 'divi-contact-confirmation' ),
					'{site_url}'  => __( 'Your site URL', 'divi-contact-confirmation' ),
					'{date}'      => __( 'Submission date', 'divi-contact-confirmation' ),
					'{time}'      => __( 'Submission time', 'divi-contact-confirmation' ),
					'{field_id}'  => __( 'Any Divi form field — wrap its ID in braces', 'divi-contact-confirmation' ),
				);
				foreach ( $ph as $key => $desc ) {
					printf(
						'<tr><td><code>%s</code></td><td>%s</td></tr>',
						esc_html( $key ),
						esc_html( $desc )
					);
				}
				?>
			</tbody>
		</table>
		<br>
		<?php
	}

	// -------------------------------------------------------------------------
	// Security tab
	// -------------------------------------------------------------------------

	private static function render_security_tab() {
		settings_errors( 'dcc_security_options' );
		?>
		<form method="post" action="options.php">
			<?php
			settings_fields( 'dcc_security_options' );
			do_settings_sections( 'dcc-settings-security' );
			submit_button( __( 'Save Security Settings', 'divi-contact-confirmation' ) );
			?>
		</form>
		<br>
		<?php
	}

	// -------------------------------------------------------------------------
	// Logs tab
	// -------------------------------------------------------------------------

	private static function render_logs_tab() {
		// phpcs:ignore WordPress.Security.NonceVerification
		$page     = isset( $_GET['log_page'] ) ? max( 1, (int) $_GET['log_page'] ) : 1;
		$per_page = 25;
		$result   = DCC_Logger::get_rows( $per_page, $page );
		$rows     = $result['rows'];
		$total    = $result['total'];
		$pages    = (int) ceil( $total / $per_page );

		// phpcs:ignore WordPress.Security.NonceVerification
		if ( ! empty( $_GET['cleared'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Log cleared.', 'divi-contact-confirmation' ) . '</p></div>';
		}

		$status_labels = array(
			'sent'    => __( 'Sent', 'divi-contact-confirmation' ),
			'failed'  => __( 'Failed', 'divi-contact-confirmation' ),
			'blocked' => __( 'Blocked', 'divi-contact-confirmation' ),
		);
		?>

		<p style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:.5rem">
			<span>
				<?php
				printf(
					/* translators: %d = number of entries */
					esc_html__( '%d log entries total', 'divi-contact-confirmation' ),
					$total
				);
				?>
			</span>
			<?php if ( $total > 0 ) : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
				      onsubmit="return confirm('<?php esc_attr_e( 'Delete all log entries? This cannot be undone.', 'divi-contact-confirmation' ); ?>')">
					<input type="hidden" name="action" value="dcc_clear_logs">
					<?php wp_nonce_field( 'dcc_clear_logs' ); ?>
					<?php submit_button( __( 'Clear all logs', 'divi-contact-confirmation' ), 'delete', 'submit', false ); ?>
				</form>
			<?php endif; ?>
		</p>

		<?php if ( empty( $rows ) ) : ?>
			<p class="dcc-empty"><?php esc_html_e( 'No emails have been sent yet.', 'divi-contact-confirmation' ); ?></p>
		<?php else : ?>
			<table class="dcc-log-table widefat">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Date / Time', 'divi-contact-confirmation' ); ?></th>
						<th><?php esc_html_e( 'Recipient', 'divi-contact-confirmation' ); ?></th>
						<th><?php esc_html_e( 'Subject', 'divi-contact-confirmation' ); ?></th>
						<th><?php esc_html_e( 'Status', 'divi-contact-confirmation' ); ?></th>
						<th><?php esc_html_e( 'Note', 'divi-contact-confirmation' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $rows as $row ) : ?>
						<tr>
							<td><?php echo esc_html( get_date_from_gmt( $row->sent_at, 'Y-m-d H:i:s' ) ); ?></td>
							<td>
								<?php if ( $row->recipient_name ) : ?>
									<?php echo esc_html( $row->recipient_name ); ?><br>
								<?php endif; ?>
								<a href="mailto:<?php echo esc_attr( $row->recipient_email ); ?>">
									<?php echo esc_html( $row->recipient_email ); ?>
								</a>
							</td>
							<td><?php echo esc_html( $row->subject ); ?></td>
							<td class="dcc-status-<?php echo esc_attr( $row->status ); ?>">
								<?php echo esc_html( $status_labels[ $row->status ] ?? $row->status ); ?>
							</td>
							<td><?php echo esc_html( $row->error_message ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<?php if ( $pages > 1 ) : ?>
				<div class="dcc-pagination tablenav">
					<div class="tablenav-pages">
						<?php
						echo paginate_links( array(
							'base'    => add_query_arg( 'log_page', '%#%' ),
							'format'  => '',
							'current' => $page,
							'total'   => $pages,
						) );
						?>
					</div>
				</div>
			<?php endif; ?>
		<?php endif; ?>
		<br>
		<?php
	}

	// -------------------------------------------------------------------------
	// Helper
	// -------------------------------------------------------------------------

	private static function tab_url( $tab ) {
		return add_query_arg( array( 'page' => self::MENU_SLUG, 'tab' => $tab ), admin_url( 'options-general.php' ) );
	}
}
