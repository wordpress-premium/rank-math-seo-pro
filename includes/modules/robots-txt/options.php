<?php
/**
 * The robots.txt settings.
 *
 * @package    RankMath
 * @subpackage RankMath\Settings
 */

defined( 'ABSPATH' ) || exit;

$cmb->add_field(
	[
		'id'      => 'robots_txt_validator',
		'type'    => 'raw',
		'content' => '<div id="rank-math-admin-rtt" class="rank-math-robots-txt-content"></div>',
	]
);
