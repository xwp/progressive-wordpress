<?php

namespace nicomartin\ProgressiveWordPress;

class Serviceworker {

	public $capability = '';
	public $sw_path = ABSPATH . 'pwp-serviceworker.js';
	public $sw_url = '/pwp-serviceworker.js';

	public function __construct() {
		$this->capability = pwp_get_instance()->Init->capability;
	}

	public function run() {
		add_action( 'admin_notices', [ $this, 'ssl_error_notice' ] );
		add_action( 'init', [ $this, 'regenerate' ] );
		if ( file_exists( $this->sw_path ) ) {
			add_action( 'wp_head', [ $this, 'add_to_header' ], 1 );
		}
	}

	public function ssl_error_notice() {

		if ( is_ssl() ) {
			return;
		}

		$screen = get_current_screen();
		if ( PWP_SETTINGS_PARENT != $screen->parent_base ) {
			return;
		}

		echo '<div class="notice notice-error">';
		echo '<p>' . __( 'Your site has to be served over https to use progressive web app features.', 'pwp' ) . '</p>';
		echo '</div>';
	}

	public function add_to_header() {
		?>
		<script id="serviceworker">
			if ('serviceWorker' in navigator) {
				window.addEventListener('load', function () {
					navigator.serviceWorker.register('<?php echo $this->sw_url; ?>');
				});
			}
		</script>
		<?php
	}

	/**
	 * Helpers
	 */

	public function regenerate() {

		$sw_option = 'pwp_sw_data';

		$offline_enabled = pwp_get_setting( 'offline-enabled' );
		$offline_page_id = intval( pwp_get_setting( 'offline-page' ) );
		$offline_link    = str_replace( trailingslashit( get_home_url() ), '', get_permalink( $offline_page_id ) );

		$sw_data = [
			'offline'      => $offline_enabled,
			'offline_page' => $offline_link,
		];

		if ( get_option( $sw_option ) == $sw_data ) {
			return;
		}

		$header = '\'use strict\'';
		$header .= "/**\n";
		$header .= " * generated by Progressive WordPress:\n";
		$header .= " * https://wordpress.org/plugins/progressive-wordpress/\n";
		$header .= " * by Nico Martin - https://nicomartin.ch\n";
		$header .= "**/\n";

		$content = 'const version = \'' . time() . '\';';

		$offline_file = plugin_dir_path( pwp_get_instance()->file ) . '/assets/serviceworker/offline.js';
		if ( file_exists( $offline_file ) && $offline_enabled ) {

			$content .= file_get_contents( $offline_file );
		}

		foreach ( $sw_data as $key => $val ) {
			$content = str_replace( "{{{$key}}}", $val, $content );
		}

		$path = plugin_dir_path( pwp_get_instance()->file ) . 'Classes/Libs';
		require_once $path . '/minify/autoload.php';
		require_once $path . '/path-converter/autoload.php';
		$minifier = new \MatthiasMullie\Minify\JS( $content );

		$content = $minifier->minify();

		file_put_contents( $this->sw_path, $header . $content );
		update_option( $sw_option, $sw_data );
	}
}
