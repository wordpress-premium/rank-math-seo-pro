<?php
/**
 * The robots.txt editor module.
 *
 * @since      3.0.92
 * @package    RankMath
 * @subpackage RankMath
 * @author     Rank Math <support@rankmath.com>
 */

namespace RankMathPro;

use RankMath\Helper;
use RankMath\Traits\Hooker;

defined( 'ABSPATH' ) || exit;

/**
 * Robots_Txt class.
 */
class Robots_Txt {

	use Hooker;

	/**
	 * Class constructor.
	 */
	public function __construct() {
		$this->action( 'rank_math/admin/settings/robots', 'add_options' );
		$this->action( 'admin_enqueue_scripts', 'enqueue', 9 );
	}

	/**
	 * Enqueue robots.txt scripts.
	 *
	 * @return void
	 */
	public function enqueue() {
		Helper::add_json( 'siteUrl', home_url( '/' ) );
	}

	/**
	 * Add options to Image SEO module.
	 *
	 * @param object $cmb CMB object.
	 */
	public function add_options( $cmb ) {
		$cmb->remove_field( 'robots_tester' );
		include_once __DIR__ . '/options.php';
	}
}
