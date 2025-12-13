<?php
/**
 * WP Media Plugin Family integration bootstrapper.
 *
 * @package    RankMathPro
 * @subpackage RankMathPro\ThirdParty
 */

namespace RankMathPro\ThirdParty;

use RankMath\Traits\Hooker;
use WPMedia\PluginFamily\Controller\PluginFamily;

defined( 'ABSPATH' ) || exit;

/**
 * Boots the wp-media/plugin-family controller and wires its hooks we rely on.
 */
class Plugin_Family {

	use Hooker;

	/**
	 * Controller instance.
	 *
	 * @var PluginFamily
	 */
	private $controller;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->action( 'init', 'init' );
	}

	/**
	 * Initializes the controller with our config and registers the hooks we need.
	 *
	 * @return void
	 */
	public function init() {
		$this->controller = new PluginFamily( [ 'post.php', 'post-new.php', 'upload.php', 'rank-math_page_rank-math-options-general' ], __( 'Rank Math recommends optimizing your images to improve Core Web Vitals and search rankings in Google using Imagify.', 'rank-math-pro' ) );
		$this->hooks();
	}

	/**
	 * Registers the hooks we need from the controller.
	 *
	 * @return void
	 */
	public function hooks() {
		// Register only the hooks we need for Imagify installation via AJAX and assets.
		add_action( 'wp_ajax_install_imagify', [ $this->controller, 'install_imagify' ] );
		add_action( 'wp_ajax_dismiss_promote_imagify', [ $this->controller, 'dismiss_promote_imagify' ] );
		add_action( 'admin_notices', [ $this->controller, 'display_error_notice' ] );

		add_action( 'admin_enqueue_scripts', [ $this->controller, 'enqueue_admin_assets' ] );
		add_action( 'enqueue_block_editor_assets', [ $this->controller, 'enqueue_assets' ] );
		add_action( 'admin_footer', [ $this->controller, 'insert_footer_templates' ] );

		// Track Imagify installation/activation via new action from the library.
		add_action( 'wpmedia_plugin_family_imagify_installed', [ $this, 'track_imagify_activation' ] );
	}

	/**
	 * Track Imagify activation event.
	 */
	public function track_imagify_activation() {
		update_option( 'imagifyp_id', 'rankmathpro', false );

		// Early bailout if tracking is not opted-in.
		if ( ! rank_math()->tracking->is_opted_in() ) {
			return;
		}

		rank_math()->tracking->track_event(
			'Button Clicked',
			[
				'brand'       => 'rankmath',
				'application' => 'rankmath pro',
				'plugin'      => rank_math()->tracking->get_plugin_label(),
				'path'        => rank_math()->tracking->get_current_path_with_query(),
			]
		);
	}
}
