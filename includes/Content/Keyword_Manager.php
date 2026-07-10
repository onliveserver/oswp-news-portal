<?php
/**
 * Keyword Manager - Manage blocked keywords in content and titles
 *
 * @package OSWP\Posts\Content
 */

namespace OSWP\Posts\Content;

if ( ! defined( 'ABSPATH' ) ) {
exit;
}

/**
 * Manages blocked keywords for posts.
 */
class Keyword_Manager {

	const OPTION_KEY = 'oswp_blocked_keywords';

	/**
	 * Get all blocked keywords.
	 *
	 * @return array
	 */
	public static function get_blocked_keywords() {
		$data = get_option( self::OPTION_KEY, '{}' );
		if ( is_array( $data ) ) {
			$keywords_data = $data;
		} else {
			$keywords_data = json_decode( $data, true );
		}

		if ( ! is_array( $keywords_data ) ) {
			$keywords_data = [];
		}

		// Return just the keywords for backward compatibility
		return array_keys( $keywords_data );
	}

	/**
	 * Get keywords with metadata.
	 *
	 * @return array
	 */
	public static function get_keywords_data() {
		$data = get_option( self::OPTION_KEY, '{}' );
		if ( is_array( $data ) ) {
			$keywords_data = $data;
		} else {
			$keywords_data = json_decode( $data, true );
		}

		if ( ! is_array( $keywords_data ) ) {
			$keywords_data = [];
		}

		return $keywords_data;
	}

	/**
	 * Save keywords data.
	 *
	 * @param array $keywords_data Keywords with metadata.
	 * @return bool
	 */
	private static function save_keywords_data( $keywords_data ) {
		return update_option( self::OPTION_KEY, wp_json_encode( $keywords_data ) );
	}

/**
 * Add a new blocked keyword.
 *
 * @param string $keyword Keyword to block.
 * @return bool
 */
public static function add_keyword( $keyword ) {
$keyword = sanitize_text_field( $keyword );

if ( empty( $keyword ) ) {
return false;
}

		$keywords_data = self::get_keywords_data();

		if ( isset( $keywords_data[ $keyword ] ) ) {
			return false; // Already exists
		}

		$keywords_data[ $keyword ] = [
			'added'     => current_time( 'timestamp' ),
			'added_by'  => get_current_user_id(),
		];

		return self::save_keywords_data( $keywords_data );
	}

	/**
	 * Add multiple keywords at once.
	 *
	 * @param array $keywords Array of keywords to add.
	 * @return array Results with success/failure for each keyword.
	 */
	public static function add_keywords( $keywords ) {
		if ( ! is_array( $keywords ) ) {
			return [ 'error' => 'Keywords must be an array' ];
		}

		$keywords_data = self::get_keywords_data();
		$results = [ 'added' => [], 'skipped' => [] ];

		foreach ( $keywords as $keyword ) {
			$keyword = sanitize_text_field( $keyword );

			if ( empty( $keyword ) ) {
				continue;
			}

			if ( isset( $keywords_data[ $keyword ] ) ) {
				$results['skipped'][] = $keyword;
				continue;
			}

			$keywords_data[ $keyword ] = [
				'added'     => current_time( 'timestamp' ),
				'added_by'  => get_current_user_id(),
			];

			$results['added'][] = $keyword;
		}

		if ( ! empty( $results['added'] ) ) {
			self::save_keywords_data( $keywords_data );
		}

		return $results;
	}

	/**
	 * Remove a blocked keyword.
	 *
	 * @param string $keyword Keyword to remove.
	 * @return bool
	 */
	public static function remove_keyword( $keyword ) {
		$keyword = sanitize_text_field( $keyword );
		$keywords_data = self::get_keywords_data();

		if ( ! isset( $keywords_data[ $keyword ] ) ) {
			return false;
		}

		unset( $keywords_data[ $keyword ] );
		return self::save_keywords_data( $keywords_data );
	}

	/**
	 * Remove multiple keywords at once.
	 *
	 * @param array $keywords Array of keywords to remove.
	 * @return array Results with success/failure for each keyword.
	 */
	public static function remove_keywords( $keywords ) {
		if ( ! is_array( $keywords ) ) {
			return [ 'error' => 'Keywords must be an array' ];
		}

		$keywords_data = self::get_keywords_data();
		$results = [ 'removed' => [], 'not_found' => [] ];

		foreach ( $keywords as $keyword ) {
			$keyword = sanitize_text_field( $keyword );

			if ( isset( $keywords_data[ $keyword ] ) ) {
				unset( $keywords_data[ $keyword ] );
				$results['removed'][] = $keyword;
			} else {
				$results['not_found'][] = $keyword;
			}
		}

		if ( ! empty( $results['removed'] ) ) {
			self::save_keywords_data( $keywords_data );
		}

		return $results;
	}

	/**
	 * Get sample keywords for demonstration.
	 *
	 * @return array
	 */
	public static function get_sample_keywords() {
		return [
			'spam',
			'advertisement',
			'marketing',
			'promotion',
			'click here',
			'buy now',
			'free offer',
			'limited time',
			'urgent',
			'important',
			'warning',
			'attention',
			'notice',
			'update',
			'news',
			'breaking',
			'exclusive',
			'special',
			'deal',
			'discount',
		];
	}

	/**
	 * Get default keywords (pornography, gambling, drugs, and illegal activities related).
	 *
	 * @return array
	 */
	public static function get_default_keywords() {
		return [
			// Pornography related
			'pornography', 'adult content', 'explicit content', 'nsfw', 'xxx', 'nude', 'naked', 'sex', 'cam', 'escort', 'prostitute', 'viagra', 'cialis', 'onlyfans',
			// Gambling related
			'casino', 'gambling', 'poker', 'blackjack', 'roulette', 'slots', 'betting', 'bet online', 'sports betting', 'online casino', 'slot machine', 'jackpot',
			// Drug related
			'cocaine', 'heroin', 'methamphetamine', 'crystal meth', 'fentanyl', 'opioid', 'ketamine', 'ecstasy', 'mdma', 'lsd', 'psilocybin', 'buy drugs', 'buy weed', 'drug dealer', 'darknet', 'drug trafficking', 'narcotic',
			// Illegal activities
			'counterfeiting', 'money laundering', 'human trafficking', 'child abuse', 'child exploitation', 'terrorism', 'hacking', 'fraud', 'phishing', 'identity theft', 'scam', 'piracy', 'illegal download',
		];
	}

	/**
	 * Get comprehensive SEO blocked keywords list.
	 * Based on Google AdSense and SEO policies.
	 *
	 * @return array
	 */
	public static function get_seo_blocked_keywords() {
		return [
			'$100 free bets', 'Adult chat', 'Adult chat rooms', 'Adult dating', 'Adult movie', 'Adult videos', 'Anal sex', 'Ass', 'Baccarat', 'BDSM', 'Best casino bonuses', 'Best online casino bonuses', 'Bestiality', 'Bet', 'Bet free', 'Bet on games', 'Bet with no risk', 'Betting', 'Betting odds', 'Betting scams', 'Betting sites', 'Bingo', 'Bingo sites', 'Black market betting', 'Black market casinos', 'Blackjack', 'Blowjob', 'Bomb-making kits', 'Boobs', 'Bookie', 'Bookmaker', 'Buy guns without license', 'Buy illegal drugs', 'buy now', 'Cam girls', 'Cam site', 'Casino', 'Casino bonuses', 'Casino rewards', 'Child pornography', 'Claim your winnings', 'Cocaine', 'Cock', 'Cougar', 'Counterfeit drugs', 'Counterfeit goods', 'Counterfeit weapons', 'Crack password', 'Craps', 'Craps betting', 'Credit card fraud', 'Cryptocurrency scams', 'Data breach', 'Dating for adults', 'Dick', 'Download cracked apps', 'Drug trafficking', 'Ecstasy', 'Erotic', 'Erotic novels', 'Erotic services', 'Escort girls', 'Escort services', 'Exploitation', 'Fake bank accounts', 'Fake betting sites', 'Fake business leads', 'Fake currency', 'Fake escort services', 'Fake giveaways', 'Fake IDs', 'Fake job offers', 'Fake lottery', 'Fake products', 'Fake products for sale', 'Fake reviews', 'Fake scholarships', 'Fake sportsbook', 'Fantasy sports gambling', 'Fetish', 'Fraudulent business opportunities', 'Fraudulent schemes', 'Free bets', 'Free casino chips', 'Free chips', 'Free spins', 'Gamble for real money', 'Gambling', 'Gambling addiction', 'Gambling apps', 'Gambling sites', 'Gambling websites', 'Gambling without a license', 'Gangbang', 'Gay porn', 'Hacking tools', 'Hacking tutorials', 'Handjob', 'Hardcore', 'heroin', 'Horse racing betting', 'Hot videos', 'Identity theft', 'Illegal betting', 'Illegal downloads', 'Illegal drugs', 'Illegal gun deals', 'Illegal streaming', 'Illegal transactions', 'Illegal weapons', 'Incest', 'Instant casino winnings', 'Instant withdrawal casinos', 'Jackpot', 'Keno', 'Lesbian porn', 'Live casino', 'Lottery', 'LSD for sale', 'Masturbation', 'meth', 'MILF', 'Money laundering', 'Movie piracy', 'Naked', 'No deposit bonus', 'No wager bonus', 'Nude', 'Nude models', 'Offshore casinos', 'Offshore gambling', 'Offshore sports betting', 'Online betting', 'Online casino', 'Online dating for sex', 'Online gambling', 'Online gambling (unregulated)', 'Online poker rooms', 'Orgasm', 'Pai Gow Poker', 'Phishing scams', 'Phishing tools', 'Pirated software', 'Poker', 'Poker chips', 'Ponzi schemes', 'Porn', 'Porn star', 'Pornographic', 'Pornographic images', 'Pornography', 'Prescription drug abuse', 'Private videos', 'Prostitution', 'Pussy', 'Pyramid scheme', 'Rape', 'Rape fantasies', 'Real money gambling', 'Real money no deposit bonus', 'Red light district', 'Rigged games', 'Roulette', 'Scam investment', 'Scam websites', 'Seduction', 'Sex', 'Sex cams', 'Sex services', 'Sex toys', 'Sex trafficking', 'Sex work', 'Sexual', 'Sexual abuse', 'Sexy lingerie', 'Sexy photos', 'Shemale', 'Slot machines', 'Slots', 'Software piracy', 'Sports betting', 'Sportsbook', 'Swinger', 'Texas Hold\'em (Poker)', 'Threesome', 'Tits', 'Torrent download', 'Torrent sites', 'Unauthorized financial services', 'Underage', 'Unethical fetishes', 'Unlicensed casinos', 'Unlicensed firearms', 'Unlicensed gambling', 'Unregistered trademarks', 'Unregulated gambling sites', 'Unregulated insurance', 'Video poker', 'Virtual sex', 'Virtual sports betting', 'Virus download', 'Wagering', 'Webcam girls', 'Win money now', 'XXX',
		];
	}

	/**
	 * Initialize SEO blocked keywords.
	 *
	 * @return bool
	 */
	public static function initialize_seo_blocked_keywords() {
		try {
			$seo_keywords = self::get_seo_blocked_keywords();
			$results = self::add_keywords( $seo_keywords );
			return ! isset( $results['error'] ) && ( ! empty( $results['added'] ) || ! empty( $results['skipped'] ) );
		} catch ( \Exception $e ) {
			return false;
		}
	}

	/**
	 * Initialize default keywords if none exist.
	 *
	 * @return bool
	 */
	public static function initialize_default_keywords() {
		$keywords = self::get_blocked_keywords();
		
		// Only initialize if no keywords exist yet
		if ( ! empty( $keywords ) ) {
			return false;
		}

		$default_keywords = self::get_default_keywords();
		$results = self::add_keywords( $default_keywords );

		return ! isset( $results['error'] ) && ! empty( $results['added'] );
	}

	/**
	 * Add sample keywords.
	 *
	 * @return array Results of adding sample keywords.
	 */
	public static function add_sample_keywords() {
		$sample_keywords = self::get_sample_keywords();
		return self::add_keywords( $sample_keywords );
	}

	/**
	 * Check if content contains blocked keywords.
	 *
	 * @param string $content Content to check.
	 * @return array Found keywords or empty array.
	 */
	public static function find_blocked_keywords( $content ) {
		$keywords = self::get_blocked_keywords();

		if ( empty( $keywords ) ) {
			return [];
		}

		$found = [];
		$content_lower = mb_strtolower( $content );

		foreach ( $keywords as $keyword ) {
			$keyword = trim( $keyword );
			if ( '' === $keyword ) {
				continue;
			}

			// Use word boundaries so that "bet" doesn't match "better" or "between".
			$keyword_lower = mb_strtolower( $keyword );
			$pattern = '/\b' . preg_quote( $keyword_lower, '/' ) . '\b/iu';

			if ( preg_match( $pattern, $content_lower ) ) {
				$found[] = $keyword;
			}
		}

		return array_unique( $found );
	}

/**
 * Validate post title and content for blocked keywords.
 *
 * @param string $title Post title.
 * @param string $content Post content.
 * @return array|bool True if valid, array of found keywords if invalid.
 */
public static function validate_post( $title, $content ) {
// Check title
$title_keywords = self::find_blocked_keywords( $title );

// Check content
$content_keywords = self::find_blocked_keywords( $content );

// Merge and return unique keywords
$all_found = array_unique( array_merge( $title_keywords, $content_keywords ) );

return empty( $all_found ) ? true : $all_found;
}

/**
 * Register hooks.
 */
public static function register_hooks() {
	// Check before saving post
	add_filter( 'wp_insert_post_data', [ __CLASS__, 'filter_post_data' ], 10, 2 );

	// AJAX handler for adding keywords
	add_action( 'wp_ajax_oswp_add_blocked_keyword', [ __CLASS__, 'ajax_add_keyword' ] );

	// AJAX handler for removing keywords
	add_action( 'wp_ajax_oswp_remove_blocked_keyword', [ __CLASS__, 'ajax_remove_keyword' ] );
	// AJAX handler for bulk adding keywords
	add_action( 'wp_ajax_oswp_add_blocked_keywords', [ __CLASS__, 'ajax_add_keywords' ] );

	// AJAX handler for bulk removing keywords
	add_action( 'wp_ajax_oswp_remove_blocked_keywords', [ __CLASS__, 'ajax_remove_keywords' ] );

	// AJAX handler for bulk updating keywords (replace all)
	add_action( 'wp_ajax_oswp_bulk_update_keywords', [ __CLASS__, 'ajax_bulk_update_keywords' ] );

	// AJAX handler for adding sample keywords
	add_action( 'wp_ajax_oswp_add_sample_keywords', [ __CLASS__, 'ajax_add_sample_keywords' ] );
}

/**
 * Filter post data before saving.
 *
 * @param array $data Post data array.
 * @param array $postarr Raw post data.
 * @return array
 */
public static function filter_post_data( $data, $postarr ) {
// Skip for non-post types
if ( empty( $postarr['post_type'] ) || 'os_post' !== $postarr['post_type'] ) {
return $data;
}

// Check for blocked keywords
$validation = self::validate_post( $data['post_title'], $data['post_content'] );

if ( true !== $validation ) {
// Add error to prevent saving
wp_die(
wp_kses_post(
'<p>' . __( 'Your post contains blocked keywords:', 'oswp-news-portal' ) . '</p>' .
'<ul><li>' . implode( '</li><li>', array_map( 'esc_html', $validation ) ) . '</li></ul>' .
'<p>' . __( 'Please remove these words and try again.', 'oswp-news-portal' ) . '</p>'
),
403
);
}

return $data;
}

/**
 * AJAX handler to add blocked keyword.
 */
public static function ajax_add_keyword() {
check_ajax_referer( 'oswp_keyword_nonce' );

if ( ! current_user_can( 'manage_options' ) ) {
wp_send_json_error( [ 'message' => __( 'Insufficient permissions.', 'oswp-news-portal' ) ] );
}

$keyword = isset( $_POST['keyword'] ) ? sanitize_text_field( $_POST['keyword'] ) : '';

if ( empty( $keyword ) ) {
wp_send_json_error( [ 'message' => __( 'Keyword cannot be empty.', 'oswp-news-portal' ) ] );
}

if ( self::add_keyword( $keyword ) ) {
wp_send_json_success( [ 'message' => __( 'Keyword added successfully.', 'oswp-news-portal' ) ] );
} else {
wp_send_json_error( [ 'message' => __( 'Keyword already exists or could not be added.', 'oswp-news-portal' ) ] );
}
}

/**
 * AJAX handler to remove blocked keyword.
 */
public static function ajax_remove_keyword() {
check_ajax_referer( 'oswp_keyword_nonce' );

if ( ! current_user_can( 'manage_options' ) ) {
wp_send_json_error( [ 'message' => __( 'Insufficient permissions.', 'oswp-news-portal' ) ] );
}

$keyword = isset( $_POST['keyword'] ) ? sanitize_text_field( $_POST['keyword'] ) : '';

if ( empty( $keyword ) ) {
wp_send_json_error( [ 'message' => __( 'Keyword cannot be empty.', 'oswp-news-portal' ) ] );
}

if ( self::remove_keyword( $keyword ) ) {
wp_send_json_success( [ 'message' => __( 'Keyword removed successfully.', 'oswp-news-portal' ) ] );
} else {
wp_send_json_error( [ 'message' => __( 'Could not remove keyword.', 'oswp-news-portal' ) ] );
}
}

	/**
	 * AJAX handler to add multiple blocked keywords.
	 */
	public static function ajax_add_keywords() {
		check_ajax_referer( 'oswp_keyword_nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Insufficient permissions.', 'oswp-news-portal' ) ] );
		}

		$keywords = isset( $_POST['keywords'] ) ? $_POST['keywords'] : '';

		if ( empty( $keywords ) || ! is_array( $keywords ) ) {
			wp_send_json_error( [ 'message' => __( 'Keywords must be an array.', 'oswp-news-portal' ) ] );
		}

		$results = self::add_keywords( $keywords );

		if ( isset( $results['error'] ) ) {
			wp_send_json_error( [ 'message' => $results['error'] ] );
		}

		$message = sprintf(
			__( 'Added %d keywords, skipped %d existing.', 'oswp-news-portal' ),
			count( $results['added'] ),
			count( $results['skipped'] )
		);

		wp_send_json_success( [
			'message' => $message,
			'results' => $results
		] );
	}

	/**
	 * AJAX handler to remove multiple blocked keywords.
	 */
	public static function ajax_remove_keywords() {
		check_ajax_referer( 'oswp_keyword_nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Insufficient permissions.', 'oswp-news-portal' ) ] );
		}

		$keywords = isset( $_POST['keywords'] ) ? $_POST['keywords'] : '';

		if ( empty( $keywords ) || ! is_array( $keywords ) ) {
			wp_send_json_error( [ 'message' => __( 'Keywords must be an array.', 'oswp-news-portal' ) ] );
		}

		$results = self::remove_keywords( $keywords );

		if ( isset( $results['error'] ) ) {
			wp_send_json_error( [ 'message' => $results['error'] ] );
		}

		$message = sprintf(
			__( 'Removed %d keywords.', 'oswp-news-portal' ),
			count( $results['removed'] )
		);

		wp_send_json_success( [
			'message' => $message,
			'results' => $results
		] );
	}

	/**
	 * AJAX handler to add sample keywords.
	 */
	public static function ajax_add_sample_keywords() {
		check_ajax_referer( 'oswp_keyword_nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Insufficient permissions.', 'oswp-news-portal' ) ] );
		}

		$results = self::add_sample_keywords();

		if ( isset( $results['error'] ) ) {
			wp_send_json_error( [ 'message' => $results['error'] ] );
		}

		$message = sprintf(
			__( 'Added %d sample keywords, skipped %d existing.', 'oswp-news-portal' ),
			count( $results['added'] ),
			count( $results['skipped'] )
		);

		wp_send_json_success( [
			'message' => $message,
			'results' => $results
		] );
	}

	/**
	 * Bulk update keywords - replace all with new list.
	 *
	 * @param array $keywords Keywords to set.
	 * @return array Results.
	 */
	public static function bulk_update_keywords( $keywords ) {
		if ( ! is_array( $keywords ) ) {
			return [ 'error' => 'Keywords must be an array' ];
		}

		// Clear all existing keywords
		delete_option( self::OPTION_KEY );

		// Add new keywords
		$keywords_data = [];
		$results = [ 'added' => [], 'skipped' => [] ];

		foreach ( $keywords as $keyword ) {
			$keyword = sanitize_text_field( $keyword );

			if ( empty( $keyword ) ) {
				continue;
			}

			if ( isset( $keywords_data[ $keyword ] ) ) {
				$results['skipped'][] = $keyword;
				continue;
			}

			$keywords_data[ $keyword ] = [
				'added'     => current_time( 'timestamp' ),
				'added_by'  => get_current_user_id(),
			];

			$results['added'][] = $keyword;
		}

		if ( ! empty( $keywords_data ) ) {
			update_option( self::OPTION_KEY, wp_json_encode( $keywords_data ) );
		}

		return $results;
	}

	/**
	 * AJAX handler to bulk update keywords (replace all).
	 */
	public static function ajax_bulk_update_keywords() {
		check_ajax_referer( 'oswp_keyword_nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Insufficient permissions.', 'oswp-news-portal' ) ] );
		}

		$keywords = isset( $_POST['keywords'] ) ? $_POST['keywords'] : [];

		if ( ! is_array( $keywords ) ) {
			wp_send_json_error( [ 'message' => __( 'Keywords must be an array.', 'oswp-news-portal' ) ] );
		}

		// Sanitize each keyword
		$sanitized_keywords = array_map( 'sanitize_text_field', $keywords );
		$sanitized_keywords = array_filter( $sanitized_keywords ); // Remove empty strings

		$results = self::bulk_update_keywords( $sanitized_keywords );

		if ( isset( $results['error'] ) ) {
			wp_send_json_error( [ 'message' => $results['error'] ] );
		}

		$message = sprintf(
			__( 'Keywords updated successfully. Added %d keywords.', 'oswp-news-portal' ),
			count( $results['added'] )
		);

		wp_send_json_success( [
			'message' => $message,
			'results' => $results
		] );
	}

}
