<?php
/**
 * Scheduled activation and deactivation.
 *
 * @since      3.0.11
 * @package    RankMathPro
 * @subpackage RankMathPro\Redirections
 * @author     Rank Math <support@rankmath.com>
 */

namespace RankMathPro\Redirections;

use RankMath\Helper;
use RankMath\Helpers\Param;
use RankMath\Traits\Hooker;
use RankMath\Redirections\DB;

defined( 'ABSPATH' ) || exit;

/**
 * Schedule class.
 */
class Schedule {

	use Hooker;

	/**
	 * Save start and end date.
	 *
	 * @var array
	 */
	private $save_start_end = [];

	/**
	 * Register hooks.
	 */
	public function __construct() {
		$this->action( 'init', 'disallow_scheduled_bulk_status_change', 5 );
		$this->action( 'rank_math/redirection/saved', 'save_start_end_after_add', 10, 2 );
		$this->action( 'rank_math/redirections/scheduled_activate', 'scheduled_activation_event', 10, 1 );
		$this->action( 'rank_math/redirections/scheduled_deactivate', 'scheduled_deactivation_event', 10, 1 );
		$this->action( 'rank_math/redirection/deleted', 'delete_scheduled_event', 10, 1 );

		$this->filter( 'rank_math/redirection/row_classes', 'row_classes', 10, 2 );
		$this->filter( 'rank_math/redirections/table_item', 'add_schedule_time_in_data_attribute' );
	}

	/**
	 * Clear all scheduled activations for a redirection.
	 *
	 * @param int $redirection_id Redirection ID.
	 * @return void
	 */
	public function clear_scheduled_activation( $redirection_id ) {
		as_unschedule_all_actions( 'rank_math/redirections/scheduled_activate', [ (int) $redirection_id ], 'rank-math' );
	}

	/**
	 * Schedule activation for a redirection for the given date.
	 *
	 * @param int    $redirection_id Redirection ID.
	 * @param string $start_date     Date to activate.
	 * @return void
	 */
	public function schedule_activation( $redirection_id, $start_date ) {
		as_schedule_single_action( $start_date, 'rank_math/redirections/scheduled_activate', [ (int) $redirection_id ], 'rank-math' );
	}

	/**
	 * Clear all scheduled deactivations for a redirection.
	 *
	 * @param int $redirection_id Redirection ID.
	 * @return void
	 */
	public function clear_scheduled_deactivation( $redirection_id ) {
		as_unschedule_all_actions( 'rank_math/redirections/scheduled_deactivate', [ (int) $redirection_id ], 'rank-math' );
	}

	/**
	 * Schedule deactivation for a redirection for the given date.
	 *
	 * @param int    $redirection_id Redirection ID.
	 * @param string $end_date       Date to deactivate.
	 * @return void
	 */
	public function schedule_deactivation( $redirection_id, $end_date ) {
		as_schedule_single_action( $end_date, 'rank_math/redirections/scheduled_deactivate', [ (int) $redirection_id ], 'rank-math' );
	}

	/**
	 * Scheduled event callback to activate a redirection.
	 *
	 * @param int $redirection_id Redirection ID.
	 * @return void
	 */
	public function scheduled_activation_event( $redirection_id ) {
		DB::change_status( [ $redirection_id ], 'active' );
	}

	/**
	 * Scheduled event callback to deactivate a redirection.
	 *
	 * @param int $redirection_id Redirection ID.
	 * @return void
	 */
	public function scheduled_deactivation_event( $redirection_id ) {
		DB::change_status( [ $redirection_id ], 'inactive' );
	}

	/**
	 * Get scheduled activation date for a redirection.
	 *
	 * @param int $redirection_id Redirection ID.
	 * @return string
	 */
	public static function get_start_date( $redirection_id ) {
		if ( ! $redirection_id ) {
			return '';
		}

		$timestamp = as_next_scheduled_action( 'rank_math/redirections/scheduled_activate', [ (int) $redirection_id ], 'rank-math' );
		if ( ! $timestamp ) {
			return '';
		}

		return gmdate( 'Y-m-d', $timestamp );
	}

	/**
	 * Get scheduled deactivation date for a redirection.
	 *
	 * @param int $redirection_id Redirection ID.
	 * @return string
	 */
	public static function get_end_date( $redirection_id ) {
		if ( ! $redirection_id ) {
			return '';
		}

		$timestamp = as_next_scheduled_action( 'rank_math/redirections/scheduled_deactivate', [ (int) $redirection_id ], 'rank-math' );
		if ( ! $timestamp ) {
			return '';
		}

		return gmdate( 'Y-m-d', $timestamp );
	}

	/**
	 * Get last completed scheduled activation/deactivation date for a redirection.
	 *
	 * @param int    $redirection_id Redirection ID.
	 * @param string $type           Type of date ("start" or "end").
	 * @return string
	 */
	public function get_past_date( $redirection_id, $type = 'start' ) {
		$hook = 'scheduled_activate';
		if ( 'end' === $type ) {
			$hook = 'scheduled_deactivate';
		}

		$args = [
			'hook'     => "rank_math/redirections/$hook",
			'args'     => [ (int) $redirection_id ],
			'status'   => \ActionScheduler_Store::STATUS_COMPLETE,
			'per_page' => 1,
			'orderby'  => 'action_id',
			'order'    => 'DESC',
		];

		$actions = as_get_scheduled_actions( $args );
		if ( empty( $actions ) ) {
			return '';
		}

		return gmdate( 'Y-m-d', reset( $actions )->get_schedule()->get_date()->getTimestamp() );
	}

	/**
	 * Save scheduled start/end dates for newly created redirections.
	 *
	 * @param object $redirection Redirection object passed to the hook.
	 * @param array  $params      Redirection parameters.
	 */
	public function save_start_end_after_add( $redirection, $params ) {
		$this->save_start_end = [
			'start_date' => isset( $params['start-date'] ) ? $params['start-date'] : '',
			'end_date'   => isset( $params['end-date'] ) ? $params['end-date'] : '',
		];
		$this->save_start_end_dates( $redirection );

		return true;
	}

	/**
	 * Save scheduled start/end dates for a redirection.
	 * The dates were previously added to the $this->save_start_end array.
	 *
	 * @param int $redirection Redirection Object.
	 */
	public function save_start_end_dates( $redirection ) {
		$redirection_id = $redirection->get_id();
		$start_date     = strtotime( $this->save_start_end['start_date'] );
		$this->clear_scheduled_activation( $redirection_id );
		if ( $start_date ) {
			$this->schedule_activation( $redirection_id, $start_date );
		}

		$end_date = strtotime( $this->save_start_end['end_date'] );
		$this->clear_scheduled_deactivation( $redirection_id );
		if ( $end_date ) {
			$this->schedule_deactivation( $redirection_id, $end_date );
		}

		// Set active status.
		$now = time();
		if ( ( $start_date && $start_date > $now ) || ( $end_date && $end_date < $now ) ) {
			$redirection->set_status( 'inactive' );
			$redirection->save();
		} elseif ( $start_date && $start_date <= $now && ( ! $end_date || $end_date > $now ) ) {
			$redirection->set_status( 'active' );
			$redirection->save();
		}
	}

	/**
	 * Add status-locked class to the row if the status is locked.
	 *
	 * @param array $classes The classes for the row.
	 * @param array $item  The object for the row.
	 *
	 * @return array
	 */
	public function row_classes( $classes, $item ) {
		if ( $this->is_status_locked( $item['id'] ) ) {
			$classes .= ' rank-math-redirection-status-locked';
		}

		return $classes;
	}

	/**
	 * Add start & end date to the data attribute used when editing a Redirection rule
	 *
	 * @param array $data Redirection data.
	 * @return array
	 */
	public function add_schedule_time_in_data_attribute( $data ) {
		$data['start-date'] = self::get_start_date( $data['id'] );
		$data['end-date']   = self::get_end_date( $data['id'] );

		return $data;
	}

	/**
	 * Check if the status is locked because of a start/end date in the future.
	 *
	 * @param int $redirection_id Redirection ID.
	 */
	private function is_status_locked( $redirection_id ) {
		$start      = $this->get_start_date( $redirection_id );
		$start_date = new \DateTime( $start );
		$start_date = $start_date->getTimestamp();
		$end        = $this->get_end_date( $redirection_id );
		$end_date   = new \DateTime( $end );
		$end_date   = $end_date->getTimestamp();
		$today      = new \DateTime();
		$today->setTime( 0, 0, 0 );
		$today = $today->getTimestamp();

		if ( $start && $start_date > $today ) {
			return true;
		} elseif ( $end && $end_date > $today && ( ! $start || $start_date >= $today ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Disable bulk status change if the selected item has a locked status.
	 */
	public function disallow_scheduled_bulk_status_change() {
		$action = Helper::get_request_action();
		if ( false === $action || empty( $_REQUEST['redirection'] ) || ! in_array( $action, [ 'activate', 'deactivate' ], true ) ) {
			return;
		}

		$ids = (array) wp_parse_id_list( $_REQUEST['redirection'] );
		if ( empty( $ids ) ) {
			return;
		}

		$could_not_change = [];
		foreach ( $ids as $id ) {
			if ( $this->is_status_locked( $id ) ) {
				$key = array_search( $id, $_REQUEST['redirection'], true );
				if ( false !== $key ) {
					$could_not_change[] = $id;
					unset( $_REQUEST['redirection'][ $key ] );
				}
			}
		}

		if ( ! empty( $could_not_change ) ) {
			$message = __( 'One or more of the selected redirections could not be changed because they are scheduled for future activation/deactivation.', 'rank-math-pro' );
			Helper::add_notification( $message, [ 'type' => 'error' ] );
		}
	}

	/**
	 * Make sure to delete scheduled actions when a redirection is deleted.
	 *
	 * @param array $redirection_ids Redirection IDs.
	 */
	public function delete_scheduled_event( $redirection_ids ) {
		if ( ! is_array( $redirection_ids ) ) {
			$redirection_ids = [ $redirection_ids ];
		}

		foreach ( $redirection_ids as $redirection_id ) {
			$this->clear_scheduled_activation( $redirection_id );
			$this->clear_scheduled_deactivation( $redirection_id );
		}
	}
}
