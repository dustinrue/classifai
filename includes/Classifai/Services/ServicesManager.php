<?php
/**
 * This class manages the known services the ClassifAI provides
 */

namespace Classifai\Services;

class ServicesManager {

	/**
	 * @var array List of registered services
	 */
	protected $services = [];


	/**
	 * @var array List of class instances being managed.
	 */
	protected $service_classes;

	/**
	 * @var string Page title for the admin page
	 */
	protected $title;

	/**
	 * @var string Menu title of the admin page.
	 */
	protected $menu_title;

	/**
	 * ServicesManager constructor.
	 *
	 * @param array $services The list of services available.
	 */
	public function __construct( $services = [] ) {
		$this->services        = $services;
		$this->service_classes = [];
		$this->get_menu_title();
	}

	/**
	 * The admin_support items require this method.
	 *
	 * @todo remove this requirement.
	 * @return bool
	 */
	public function can_register() {
		return true;
	}

	/**
	 * Register the actions required for the settings page.
	 */
	public function register() {
		foreach ( $this->services as $service ) {
			if ( class_exists( $service ) ) {
				$this->service_classes[] = new $service();
			}
		}

		// Do the settings pages.
		$this->do_settings();

		// Register the functionality
		$this->register_services();
	}

	/**
	 * Get general ClassifAI settings
	 *
	 * @param string $index Optional specific setting to be retrieved.
	 */
	protected function get_settings( $index = false ) {
		$settings = get_option( 'classifai_settings' );

		// Special handling polyfill for pre-1.3 settings which were nested
		if ( ! isset( $settings['email'] ) && isset( $settings['registration']['email'] ) ) {
			$settings['email'] = $settings['registration']['email'];
		}

		if ( ! isset( $settings['license_key'] ) && isset( $settings['registration']['license_key'] ) ) {
			$settings['license_key'] = $settings['registration']['license_key'];
		}

		if ( ! $index ) {
			return $settings;
		} elseif ( ! isset( $settings[ $index ] ) ) {
			return '';
		} else {
			return $settings[ $index ];
		}
	}


	/**
	 * Create the settings pages.
	 *
	 * If there are more than a single service, we'll create a top level admin menu and add subsequent items there.
	 */
	public function do_settings() {
		if ( count( $this->service_classes ) > 1 ) {
			add_action( 'admin_menu', [ $this, 'register_top_level_admin_menu_item' ] );
		} else {
			add_action( 'admin_menu', [ $this, 'register_admin_menu_item' ] );
		}

		add_action( 'admin_init', [ $this, 'register_settings' ] );
		add_action( 'admin_init', [ $this, 'setup_fields_sections' ] );
	}

	/**
	 * Register the settings and sanitization callback method.
	 *
	 * It's very important that the option group matches the page slug.
	 */
	public function register_settings() {
		register_setting( 'classifai_settings', 'classifai_settings', [ $this, 'sanitize_settings' ] );
	}

	/**
	 * Sanitize settings.
	 *
	 * @param array $settings The settings to be sanitized.
	 *
	 * @return mixed
	 */
	public function sanitize_settings( $settings ) {
		$new_settings = [];
		if ( isset( $settings['email'] ) && isset( $settings['license_key'] ) ) {
			if ( $this->check_license_key( $settings['email'], $settings['license_key'] ) ) {
				$new_settings['valid_license'] = true;
				$new_settings['email']         = sanitize_text_field( $settings['email'] );
				$new_settings['license_key']   = sanitize_text_field( $settings['license_key'] );
			} else {
				$new_settings['valid_license'] = false;
				$new_settings['email']         = '';
				$new_settings['license_key']   = '';
				add_settings_error(
					'registration',
					'classifai-registration',
					esc_html__( 'Invalid ClassifAI registration info. Please check and try again.', 'classifai' ),
					'error'
				);
			}
		}

		return $new_settings;
	}

	/**
	 * Setup fields
	 */
	public function setup_fields_sections() {
		add_settings_section( 'classifai_settings', 'Classifai Settings', '', 'classifai_settings' );

		add_settings_field(
			'email',
			esc_html__( 'Registered Email', 'classifai' ),
			[ $this, 'render_email_field' ],
			'classifai_settings',
			'classifai_settings',
			[
				'label_for'    => 'email',
				'option_index' => 'registration',
				'input_type'   => 'text',
			]
		);

		add_settings_field(
			'registration-key',
			esc_html__( 'Registration Key', 'classifai' ),
			[ $this, 'render_password_field' ],
			'classifai_settings',
			'classifai_settings',
			[
				'label_for'    => 'license_key',
				'option_index' => 'registration',
				'input_type'   => 'password',
				'description'  => __( 'Registration is 100% free and provides update notifications and upgrades inside the dashboard.<br /><a href="https://classifaiplugin.com/#cta">Register for your key</a>', 'classifai' ),
			]
		);
	}

	/**
	 * Render the email field
	 */
	public function render_email_field() {
		$email = $this->get_settings( 'email' );
		?>
		<input type="text" name="classifai_settings[email]" class="regular-text" value="<?php echo esc_attr( $email ); ?>"/>
		<?php
	}

	/**
	 * Render the password field
	 */
	public function render_password_field() {
		$license_key = $this->get_settings( 'license_key' );
		?>
		<input type="password" name="classifai_settings[license_key]" class="regular-text" value="<?php echo esc_attr( $license_key ); ?>"/>
		<br /><span class="description"><?php _e( __( 'Registration is 100% free and provides update notifications and upgrades inside the dashboard.<br /><a href="https://classifaiplugin.com/#cta">Register for your key</a>', 'classifai' ) );// @codingStandardsIgnoreLine ?></span>
		<?php
	}

	/**
	 * Initialize the services.
	 */
	protected function register_services() {
		foreach ( $this->service_classes as $service_class ) {
			$service_class->init();
		}
	}


	/**
	 * Helper to return the $menu title
	 */
	protected function get_menu_title() {
		$is_setup         = get_option( 'classifai_configured' );
		$this->title      = esc_html__( 'ClassifAI', 'classifai' );
		$this->menu_title = $this->title;

		if ( ! $is_setup ) {
			/*
			 * Translators: Main title.
			 */
			$this->menu_title = sprintf( __( 'ClassifAI %s', 'classifai' ), '<span class="update-plugins"><span class="update-count">!</span></span>' );
		}
	}

	/**
	 * Return the list of registered services.
	 *
	 * @return array
	 */
	public function get_services() {
		return $this->services;
	}

	/**
	 * Register a sub page.
	 */
	public function register_admin_menu_item() {
		add_submenu_page(
			'options-general.php',
			$this->title,
			$this->menu_title,
			'manage_options',
			'classifai_settings',
			[ $this, 'render_settings_page' ]
		);

		$this->init_services_settings();
	}

	/**
	 * Register a top level page.
	 */
	public function register_top_level_admin_menu_item() {
		add_menu_page(
			$this->title,
			$this->menu_title,
			'manage_options',
			'classifai_settings',
			[ $this, 'render_settings_page' ]
		);

		$this->init_services_settings();
	}

	/**
	 * Registers each of the service pages.
	 */
	public function init_services_settings() {

		foreach ( $this->service_classes as $service_class ) {
			add_submenu_page(
				'classifai_settings',
				$service_class->get_display_name(),
				$service_class->get_display_name(),
				'manage_options',
				$service_class->get_menu_slug(),
				[ $service_class, 'render_settings_page' ]
			);
		}
	}



	/**
	 * Render the main settings page for the Classifai plugin.
	 */
	public function render_settings_page() {

		if ( count( $this->services ) > 1 ) {

			?>
			<div class="wrap">
				<?php settings_errors(); ?>
				<form method="post" action="options.php">
					<?php
					settings_fields( 'classifai_settings' );
					do_settings_sections( 'classifai_settings' );
					submit_button();
					?>
				</form>
			</div>
			<?php
		} else {
			// Render settings page for the first ( and only ) settings page.
			$this->service_classes[0]->render_settings_page();
		}
	}

	/**
	 * Hit license API to see if key/email is valid
	 *
	 * @param  string $email Email address.
	 * @param  string $license_key License key.
	 * @since  1.2
	 * @return bool
	 */
	public function check_license_key( $email, $license_key ) {

		$request = wp_remote_post(
			'https://classifaiplugin.com/wp-json/classifai-theme/v1/validate-license',
			[
				'timeout' => 10,
				'body'    => [
					'license_key' => $license_key,
					'email'       => $email,
				],
			]
		);

		if ( is_wp_error( $request ) ) {
			return false;
		}

		if ( 200 === wp_remote_retrieve_response_code( $request ) ) {
			return true;
		}

		return false;
	}
}
