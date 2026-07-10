<?php
/**
 * Model usage statistics and consumption tracking.
 *
 * @package OSWP\Posts
 */

namespace OSWP\Posts\Content;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles AI model usage tracking and consumption statistics.
 */
class Model_Stats {
	/**
	 * Option key for model stats.
	 *
	 * @var string
	 */
	const STATS_KEY = 'oswp_model_stats';

	/**
	 * Model categories.
	 *
	 * @var array
	 */
	protected $model_categories = [
		'gpt-5'           => 'pro',
		'gpt-5.1'         => 'pro',
		'gpt-5.2'         => 'pro',
		'gpt-5-pro'       => 'pro',
		'gpt-4o'          => 'pro',
		'gpt-4.1-mini'    => 'standard',
		'gpt-5-mini'      => 'standard',
		'gpt-4o-mini'     => 'standard',
		'gpt-5-nano'      => 'other',
		'gpt-4.1-nano'    => 'other',
		'gpt-3.5'         => 'other',
	];

	/**
	 * Track model usage.
	 *
	 * @param string $model Model name.
	 * @param int    $user_id User ID (0 for global stats).
	 * @param array  $metadata Additional metadata to track.
	 * @return bool
	 */
	public function track_usage( $model, $user_id = 0, $metadata = [] ) {
		if ( empty( $model ) ) {
			return false;
		}

		$stats = $this->get_all_stats();
		$model = sanitize_text_field( $model );
		$category = $this->get_model_category( $model );

		// Initialize model stats if not exists
		if ( ! isset( $stats[ $model ] ) ) {
			$stats[ $model ] = [
				'count'         => 0,
				'category'      => $category,
				'first_used'    => current_time( 'mysql' ),
				'last_used'     => current_time( 'mysql' ),
				'total_tokens'  => 0,
				'by_user'       => [],
			];
		}

		// Update global stats
		$stats[ $model ]['count']    += 1;
		$stats[ $model ]['last_used'] = current_time( 'mysql' );

		// Track tokens if provided
		if ( isset( $metadata['tokens'] ) ) {
			$stats[ $model ]['total_tokens'] += absint( $metadata['tokens'] );
		}

		// Track per-user stats if user_id provided
		if ( $user_id > 0 ) {
			if ( ! isset( $stats[ $model ]['by_user'][ $user_id ] ) ) {
				$stats[ $model ]['by_user'][ $user_id ] = 0;
			}
			$stats[ $model ]['by_user'][ $user_id ] += 1;
		}

		return update_option( self::STATS_KEY, $stats );
	}

	/**
	 * Get usage stats for a specific model.
	 *
	 * @param string $model Model name.
	 * @return array
	 */
	public function get_model_stats( $model ) {
		$stats = $this->get_all_stats();
		$model = sanitize_text_field( $model );

		return isset( $stats[ $model ] ) ? $stats[ $model ] : $this->get_default_model_stats( $model );
	}

	/**
	 * Get all model stats.
	 *
	 * @return array
	 */
	public function get_all_stats() {
		$stats = get_option( self::STATS_KEY, [] );
		return is_array( $stats ) ? $stats : [];
	}

	/**
	 * Get stats by category (pro, standard, other).
	 *
	 * @param string $category Category name.
	 * @return array
	 */
	public function get_stats_by_category( $category = '' ) {
		$all_stats = $this->get_all_stats();
		$category = sanitize_text_field( $category );
		$result = [];

		foreach ( $all_stats as $model => $stats ) {
			if ( empty( $category ) || $stats['category'] === $category ) {
				$result[ $model ] = $stats;
			}
		}

		return $result;
	}

	/**
	 * Get consumption summary.
	 *
	 * @return array
	 */
	public function get_consumption_summary() {
		$all_stats = $this->get_all_stats();
		$summary = [
			'total_generations' => 0,
			'total_tokens'      => 0,
			'by_category'       => [
				'pro'      => [
					'count'  => 0,
					'tokens' => 0,
					'models' => [],
				],
				'standard' => [
					'count'  => 0,
					'tokens' => 0,
					'models' => [],
				],
				'other'    => [
					'count'  => 0,
					'tokens' => 0,
					'models' => [],
				],
			],
			'top_models'        => [],
		];

		foreach ( $all_stats as $model => $stats ) {
			$category = $stats['category'] ?? 'other';
			$count = $stats['count'] ?? 0;
			$tokens = $stats['total_tokens'] ?? 0;

			$summary['total_generations'] += $count;
			$summary['total_tokens']      += $tokens;
			$summary['by_category'][ $category ]['count']  += $count;
			$summary['by_category'][ $category ]['tokens'] += $tokens;
			$summary['by_category'][ $category ]['models'][] = $model;

			$summary['top_models'][] = [
				'model'  => $model,
				'count'  => $count,
				'tokens' => $tokens,
			];
		}

		// Sort top models by count (descending)
		usort( $summary['top_models'], function( $a, $b ) {
			return $b['count'] - $a['count'];
		} );

		// Keep only top 5
		$summary['top_models'] = array_slice( $summary['top_models'], 0, 5 );

		return $summary;
	}

	/**
	 * Get category consumption stats.
	 *
	 * @param string $category Category (pro, standard, other).
	 * @return array
	 */
	public function get_category_consumption( $category ) {
		$category = sanitize_text_field( $category );
		$stats = $this->get_stats_by_category( $category );

		if ( empty( $stats ) ) {
			return [
				'category'       => $category,
				'total_count'    => 0,
				'total_tokens'   => 0,
				'models'         => [],
				'average_tokens' => 0,
			];
		}

		$total_count = 0;
		$total_tokens = 0;
		$models = [];

		foreach ( $stats as $model => $data ) {
			$count = $data['count'] ?? 0;
			$tokens = $data['total_tokens'] ?? 0;
			$total_count += $count;
			$total_tokens += $tokens;

			$models[] = [
				'name'   => $model,
				'count'  => $count,
				'tokens' => $tokens,
				'users'  => count( $data['by_user'] ?? [] ),
			];
		}

		return [
			'category'       => $category,
			'total_count'    => $total_count,
			'total_tokens'   => $total_tokens,
			'models'         => $models,
			'average_tokens' => $total_count > 0 ? round( $total_tokens / $total_count, 2 ) : 0,
		];
	}

	/**
	 * Get default model stats structure.
	 *
	 * @param string $model Model name.
	 * @return array
	 */
	protected function get_default_model_stats( $model ) {
		return [
			'count'         => 0,
			'category'      => $this->get_model_category( $model ),
			'first_used'    => null,
			'last_used'     => null,
			'total_tokens'  => 0,
			'by_user'       => [],
		];
	}

	/**
	 * Get model category.
	 *
	 * @param string $model Model name.
	 * @return string
	 */
	public function get_model_category( $model ) {
		$model = sanitize_text_field( $model );
		return $this->model_categories[ $model ] ?? 'other';
	}

	/**
	 * Reset all statistics.
	 *
	 * @return bool
	 */
	public function reset_stats() {
		return delete_option( self::STATS_KEY );
	}

	/**
	 * Reset stats for a specific model.
	 *
	 * @param string $model Model name.
	 * @return bool
	 */
	public function reset_model_stats( $model ) {
		$stats = $this->get_all_stats();
		$model = sanitize_text_field( $model );

		if ( isset( $stats[ $model ] ) ) {
			unset( $stats[ $model ] );
			return update_option( self::STATS_KEY, $stats );
		}

		return false;
	}

	/**
	 * Get user-specific consumption.
	 *
	 * @param int $user_id User ID.
	 * @return array
	 */
	public function get_user_consumption( $user_id ) {
		$user_id = absint( $user_id );
		$all_stats = $this->get_all_stats();
		$user_consumption = [
			'user_id'   => $user_id,
			'total'     => 0,
			'by_model'  => [],
			'by_category' => [
				'pro'      => 0,
				'standard' => 0,
				'other'    => 0,
			],
		];

		foreach ( $all_stats as $model => $stats ) {
			if ( isset( $stats['by_user'][ $user_id ] ) ) {
				$count = $stats['by_user'][ $user_id ];
				$user_consumption['total'] += $count;
				$user_consumption['by_model'][ $model ] = $count;

				$category = $stats['category'] ?? 'other';
				$user_consumption['by_category'][ $category ] += $count;
			}
		}

		return $user_consumption;
	}
}
