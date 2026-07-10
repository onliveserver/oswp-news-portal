<?php
/**
 * Post Carousel Block — Registration & Render
 *
 * Supports layouts: card, overlay, ticker, hero.
 * All dynamic styling via CSS custom properties (--oswp-*).
 *
 * @package OSWP\Posts
 */

namespace OSWP\Posts\Blocks;

use OSWP\Posts\Core\Service_Container;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Post_Carousel_Block {

	/** @var Service_Container */
	protected $container;

	public function __construct( Service_Container $container ) {
		$this->container = $container;
	}

	/* ================================================================
	   Hooks
	   ================================================================ */
	public function register_hooks() {
		add_action( 'init', [ $this, 'register_block' ] );
		add_action( 'init', [ $this, 'register_carousel_scripts' ] );
		add_filter( 'block_categories_all', [ $this, 'register_block_category' ], 10, 2 );
	}

	/* ================================================================
	   Register Carousel Scripts & Styles
	   ================================================================ */
	public function register_carousel_scripts() {
		// Register local Slick carousel assets bundled with the plugin.
		wp_register_style(
			'oswp-slick-carousel',
			OSWP_POSTS_PLUGIN_URL . 'blocks/post-carousel/vendor/slick/slick.css',
			[],
			filemtime( OSWP_POSTS_PLUGIN_DIR . 'blocks/post-carousel/vendor/slick/slick.css' )
		);

		wp_register_script(
			'oswp-slick-carousel',
			OSWP_POSTS_PLUGIN_URL . 'blocks/post-carousel/vendor/slick/slick.min.js',
			[ 'jquery' ],
			filemtime( OSWP_POSTS_PLUGIN_DIR . 'blocks/post-carousel/vendor/slick/slick.min.js' ),
			true
		);

		wp_register_script(
			'oswp-carousel-init',
			OSWP_POSTS_PLUGIN_URL . 'blocks/post-carousel/carousel.js',
			[ 'jquery', 'oswp-slick-carousel' ],
			filemtime( OSWP_POSTS_PLUGIN_DIR . 'blocks/post-carousel/carousel.js' ),
			true
		);
	}

	/* ================================================================
	   Enqueue Carousel Scripts (only when a block renders)
	   ================================================================ */
	protected function enqueue_carousel_scripts() {
		if ( is_admin() ) {
			return;
		}

		wp_enqueue_style( 'oswp-slick-carousel' );
		wp_enqueue_style( 'oswp-post-carousel-style' );
		wp_enqueue_script( 'oswp-slick-carousel' );
		wp_enqueue_script( 'oswp-carousel-init' );
	}

	/* ================================================================
	   Block Category — "OSWP News Portal"
	   ================================================================ */
	public function register_block_category( $categories, $context ) {
		foreach ( $categories as $cat ) {
			if ( $cat['slug'] === 'oswp-news-portal' ) {
				return $categories;
			}
		}

		array_unshift( $categories, [
			'slug'  => 'oswp-news-portal',
			'title' => __( 'OSWP News Portal', 'oswp-posts' ),
			'icon'  => 'admin-site-alt3',
		] );

		return $categories;
	}

	/* ================================================================
	   Block Registration
	   ================================================================ */
	public function register_block() {
		$metadata   = json_decode( file_get_contents( OSWP_POSTS_PLUGIN_DIR . 'blocks/post-carousel/block.json' ), true );
		$attributes = $metadata['attributes'] ?? [];

		/* Use auto-generated asset file from wp-scripts build */
		$asset_file = OSWP_POSTS_PLUGIN_DIR . 'blocks/post-carousel/build/index.asset.php';
		$asset      = file_exists( $asset_file )
			? require $asset_file
			: [ 'dependencies' => [ 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n', 'wp-data', 'wp-server-side-render' ], 'version' => filemtime( OSWP_POSTS_PLUGIN_DIR . 'blocks/post-carousel/build/index.js' ) ];

		wp_register_script(
			'oswp-post-carousel-editor',
			OSWP_POSTS_PLUGIN_URL . 'blocks/post-carousel/build/index.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);

		wp_register_style(
			'oswp-post-carousel-editor',
			OSWP_POSTS_PLUGIN_URL . 'blocks/post-carousel/editor.css',
			[ 'wp-edit-blocks' ],
			filemtime( OSWP_POSTS_PLUGIN_DIR . 'blocks/post-carousel/editor.css' )
		);

		wp_register_style(
			'oswp-post-carousel-style',
			OSWP_POSTS_PLUGIN_URL . 'blocks/post-carousel/style.css',
			[ 'oswp-slick-carousel' ],
			filemtime( OSWP_POSTS_PLUGIN_DIR . 'blocks/post-carousel/style.css' )
		);

		register_block_type( 'oswp/post-carousel', [
			'title'           => __( 'Post Carousel Slider', 'oswp-posts' ),
			'description'     => __( 'Display posts in beautiful carousel sliders with multiple layouts', 'oswp-posts' ),
			'category'        => 'oswp-news-portal',
			'icon'            => 'slides',
			'keywords'        => [ 'posts', 'carousel', 'slider', 'news', 'ticker', 'hero' ],
			'attributes'      => $attributes,
			'editor_script'   => 'oswp-post-carousel-editor',
			'editor_style'    => 'oswp-post-carousel-editor',
			'style'           => 'oswp-post-carousel-style',
			'render_callback' => [ $this, 'render_block' ],
		] );
	}

	/* ================================================================
	   Attribute Defaults
	   ================================================================ */
	protected function get_defaults() {
		return [
			'layout'              => 'card',
			'postsToShow'         => 6,
			'categories'          => [],
			'tags'                => [],
			'orderBy'             => 'date',
			'order'               => 'desc',
			'slidesToShow'        => 3,
			'slidesToScroll'      => 1,
			'autoplay'            => true,
			'autoplaySpeed'       => 3000,
			'pauseOnHover'        => true,
			'infinite'            => true,
			'speed'               => 500,
			'fade'                => false,
			'dots'                => true,
			'dotsStyle'           => 'default',
			'dotsColor'           => '#2563eb',
			'dotsSize'            => 12,
			'dotsPosition'        => 'outside',
			'arrows'              => true,
			'arrowStyle'          => 'default',
			'arrowColor'          => '#1e293b',
			'arrowBgColor'        => '#ffffff',
			'arrowSize'           => 40,
			'showImage'           => true,
			'showTitle'           => true,
			'showExcerpt'         => true,
			'excerptLimit'        => 20,
			'showReadMore'        => true,
			'readMoreText'        => 'Read More',
			'showDate'            => true,
			'showAuthor'          => true,
			'showCategory'        => true,
			'overlayColor'        => '#000000',
			'overlayOpacity'      => 50,
			'overlayGradient'     => true,
			'tickerLabel'         => 'Breaking News',
			'tickerLabelBg'       => '#2563eb',
			'tickerLabelColor'    => '#ffffff',
			'tickerSpeed'         => 4000,
			'tickerBgColor'       => '#f8fafc',
			'tickerTextColor'     => '#1e293b',
			'heroLayout'          => 'text-left',
			'showLiveBadge'       => true,
			'liveBadgeText'       => 'LIVE',
			'liveBadgeColor'      => '#dc2626',
			'showTimeAgo'         => true,
			'showImageCounter'    => true,
			'sliderHeight'        => 0,
			'imageHeight'         => 0,
			'columnGap'           => 10,
			'paddingTop'          => 20,
			'paddingRight'        => 20,
			'paddingBottom'       => 20,
			'paddingLeft'         => 20,
			'marginTop'           => 0,
			'marginRight'         => 0,
			'marginBottom'        => 0,
			'marginLeft'          => 0,
			'borderWidth'         => 1,
			'borderColor'         => '#e2e8f0',
			'borderStyle'         => 'solid',
			'borderRadiusTL'      => 12,
			'borderRadiusTR'      => 12,
			'borderRadiusBL'      => 12,
			'borderRadiusBR'      => 12,
			'borderWidth'         => 0,
			'titleFontSize'       => 20,
			'titleColor'          => '#1e293b',
			'excerptFontSize'     => 14,
			'excerptColor'        => '#64748b',
			'metaFontSize'        => 12,
			'metaColor'           => '#94a3b8',
			'backgroundColor'     => '#ffffff',
			'hoverEffect'         => true,
			'cardShadow'          => 'medium',
			'buttonColor'         => '#ffffff',
			'buttonBgColor'       => '#2563eb',
			'buttonBorderRadius'  => 4,
			'buttonFontSize'      => 14,
			'categoryBgColor'     => '#2563eb',
			'categoryColor'       => '#ffffff',
		];
	}

	/* ================================================================
	   CSS Custom Properties  (inline style on wrapper)
	   ================================================================ */
	protected function get_css_variables( $a ) {
		$vars = [
			'--oswp-dot-color'        => $this->hex( $a['dotsColor'] ),
			'--oswp-dot-size'         => (int) $a['dotsSize'] . 'px',
			'--oswp-arrow-color'      => $this->hex( $a['arrowColor'] ),
			'--oswp-arrow-bg'         => $this->hex( $a['arrowBgColor'] ),
			'--oswp-arrow-size'       => (int) $a['arrowSize'] . 'px',
			'--oswp-overlay-color'    => $this->hex( $a['overlayColor'] ),
			'--oswp-overlay-opacity'  => ( (int) $a['overlayOpacity'] ) / 100,
			'--oswp-border-width'     => (int) $a['borderWidth'] === 0 ? '0' : (int) $a['borderWidth'] . 'px',
			'--oswp-border-color'     => $this->hex( $a['borderColor'] ),
			'--oswp-border-style'     => (int) $a['borderWidth'] === 0 ? 'none' : sanitize_text_field( $a['borderStyle'] ),
			'--oswp-radius-tl'        => (int) $a['borderRadiusTL'] . 'px',
			'--oswp-radius-tr'        => (int) $a['borderRadiusTR'] . 'px',
			'--oswp-radius-bl'        => (int) $a['borderRadiusBL'] . 'px',
			'--oswp-radius-br'        => (int) $a['borderRadiusBR'] . 'px',
			'--oswp-pad-top'          => (int) $a['paddingTop'] . 'px',
			'--oswp-pad-right'        => (int) $a['paddingRight'] . 'px',
			'--oswp-pad-bottom'       => (int) $a['paddingBottom'] . 'px',
			'--oswp-pad-left'         => (int) $a['paddingLeft'] . 'px',
			'--oswp-margin-top'       => (int) $a['marginTop'] . 'px',
			'--oswp-margin-right'     => (int) $a['marginRight'] . 'px',
			'--oswp-margin-bottom'    => (int) $a['marginBottom'] . 'px',
			'--oswp-margin-left'      => (int) $a['marginLeft'] . 'px',
			'--oswp-col-gap'          => (int) $a['columnGap'] . 'px',
			'--oswp-slider-height'    => (int) $a['sliderHeight'] ? (int) $a['sliderHeight'] . 'px' : 'auto',
			'--oswp-img-height'       => (int) $a['imageHeight'] ? (int) $a['imageHeight'] . 'px' : 'auto',
			'--oswp-title-size'       => (int) $a['titleFontSize'] . 'px',
			'--oswp-title-color'      => $this->hex( $a['titleColor'] ),
			'--oswp-excerpt-size'     => (int) $a['excerptFontSize'] . 'px',
			'--oswp-excerpt-color'    => $this->hex( $a['excerptColor'] ),
			'--oswp-meta-size'        => (int) $a['metaFontSize'] . 'px',
			'--oswp-meta-color'       => $this->hex( $a['metaColor'] ),
			'--oswp-bg-color'         => $this->hex( $a['backgroundColor'] ),
			'--oswp-btn-color'        => $this->hex( $a['buttonColor'] ),
			'--oswp-btn-bg'           => $this->hex( $a['buttonBgColor'] ),
			'--oswp-btn-radius'       => (int) $a['buttonBorderRadius'] . 'px',
			'--oswp-btn-size'         => (int) $a['buttonFontSize'] . 'px',
			'--oswp-cat-bg'           => $this->hex( $a['categoryBgColor'] ),
			'--oswp-cat-color'        => $this->hex( $a['categoryColor'] ),
			'--oswp-slides-to-show'   => (int) $a['slidesToShow'],
		];

		$parts = [];
		foreach ( $vars as $prop => $val ) {
			if ( $val !== '' && $val !== null ) {
				$parts[] = $prop . ':' . $val;
			}
		}
		return implode( ';', $parts );
	}

	/** Sanitise hex colour, fallback to empty string. */
	protected function hex( $color ) {
		$clean = sanitize_hex_color( $color );
		return $clean ? $clean : $color;
	}

	/* ================================================================
	   Slick Settings JSON  (card / overlay)
	   ================================================================ */
	protected function get_slider_settings( $a ) {
		$arrow_style = sanitize_html_class( $a['arrowStyle'] );
		$prev_svg    = '<svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor"><path d="M15.41 7.41L14 6l-6 6 6 6 1.41-1.41L10.83 12z"/></svg>';
		$next_svg    = '<svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor"><path d="M10 6L8.59 7.41 13.17 12l-4.58 4.59L10 18l6-6z"/></svg>';

		$settings = [
			'slidesToShow'   => (int) $a['slidesToShow'],
			'slidesToScroll' => (int) $a['slidesToScroll'],
			'autoplay'       => (bool) $a['autoplay'],
			'autoplaySpeed'  => (int) $a['autoplaySpeed'],
			'pauseOnHover'   => (bool) $a['pauseOnHover'],
			'infinite'       => (bool) $a['infinite'],
			'speed'          => (int) $a['speed'],
			'dots'           => (bool) $a['dots'],
			'arrows'         => (bool) $a['arrows'],
			'fade'           => (bool) $a['fade'] && (int) $a['slidesToShow'] === 1,
			'adaptiveHeight' => false,
			'responsive'     => [
				[
					'breakpoint' => 1024,
					'settings'   => [
						'slidesToShow'   => max( 1, min( 2, (int) $a['slidesToShow'] ) ),
						'slidesToScroll' => 1,
					],
				],
				[
					'breakpoint' => 640,
					'settings'   => [
						'slidesToShow'   => 1,
						'slidesToScroll' => 1,
					],
				],
			],
		];

		if ( $a['arrows'] ) {
			$settings['prevArrow'] = '<button type="button" class="slick-prev oswp-arrow oswp-arrow--' . $arrow_style . '" aria-label="Previous">' . $prev_svg . '</button>';
			$settings['nextArrow'] = '<button type="button" class="slick-next oswp-arrow oswp-arrow--' . $arrow_style . '" aria-label="Next">' . $next_svg . '</button>';
		}

		return wp_json_encode( $settings );
	}

	/* ================================================================
	   Helpers
	   ================================================================ */
	protected function get_limited_excerpt( $limit ) {
		$excerpt = get_the_excerpt();
		$words   = explode( ' ', $excerpt );
		if ( count( $words ) > $limit ) {
			$excerpt = implode( ' ', array_slice( $words, 0, $limit ) ) . '…';
		}
		return $excerpt;
	}

	protected function get_time_ago() {
		return human_time_diff( get_the_time( 'U' ), current_time( 'timestamp' ) ) . ' ' . __( 'ago', 'oswp-posts' );
	}

	protected function is_editor_preview() {
		return ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || is_admin();
	}

	/* ================================================================
	   Main render dispatch
	   ================================================================ */
	public function render_block( $attributes ) {
		$a = wp_parse_args( $attributes, $this->get_defaults() );

		if ( ! $this->is_editor_preview() ) {
			$this->enqueue_carousel_scripts();
		}

		// Build WP_Query
		$query_args = [
			'post_type'      => 'post',
			'posts_per_page' => $a['postsToShow'],
			'orderby'        => $a['orderBy'],
			'order'          => $a['order'],
			'post_status'    => 'publish',
		];

		if ( ! empty( $a['categories'] ) ) {
			$query_args['category__in'] = $a['categories'];
		}
		if ( ! empty( $a['tags'] ) ) {
			$query_args['tag__in'] = $a['tags'];
		}

		$query = new \WP_Query( $query_args );

		if ( ! $query->have_posts() ) {
			return '<p class="oswp-no-posts">' . esc_html__( 'No posts found.', 'oswp-posts' ) . '</p>';
		}

		$id = 'oswp-carousel-' . wp_unique_id();

		ob_start();

		switch ( $a['layout'] ) {
			case 'overlay':
				$this->render_overlay( $a, $query, $id );
				break;
			case 'ticker':
				$this->render_ticker( $a, $query, $id );
				break;
			case 'hero':
				$this->render_hero( $a, $query, $id );
				break;
			default:
				$this->render_card( $a, $query, $id );
				break;
		}

		wp_reset_postdata();
		return ob_get_clean();
	}

	/* ================================================================
	   CARD Layout
	   ================================================================ */
	protected function render_card( $a, $query, $id ) {
		$css_vars  = $this->get_css_variables( $a );
		$slick     = $this->get_slider_settings( $a );
		$shadow    = 'oswp-shadow--' . sanitize_html_class( $a['cardShadow'] );
		$hover     = $a['hoverEffect'] ? ' oswp-card--hover' : '';
		$editor    = $this->is_editor_preview() ? ' oswp-editor-preview' : '';
		$dots_cls  = ' oswp-dots--' . sanitize_html_class( $a['dotsStyle'] );
		$dots_pos  = ' oswp-dots--' . sanitize_html_class( $a['dotsPosition'] );
		?>
		<div id="<?php echo esc_attr( $id ); ?>"
			 class="oswp-post-carousel-wrapper oswp-layout--card<?php echo esc_attr( $editor . $dots_cls . $dots_pos ); ?>"
			 data-layout="card"
			 data-slick='<?php echo esc_attr( $slick ); ?>'
			 style="<?php echo esc_attr( $css_vars ); ?>">

			<?php while ( $query->have_posts() ) : $query->the_post(); ?>
				<div class="oswp-carousel-slide">
					<article class="oswp-card <?php echo esc_attr( $shadow . $hover ); ?>">
						<?php if ( $a['showImage'] && has_post_thumbnail() ) : ?>
							<div class="oswp-card__image">
								<a href="<?php the_permalink(); ?>">
									<?php the_post_thumbnail( 'medium_large', [ 'class' => 'oswp-card__img' ] ); ?>
								</a>
								<?php $this->render_category_badge( $a, 'oswp-card__badge' ); ?>
							</div>
						<?php endif; ?>

						<div class="oswp-card__body">
							<?php $this->render_title( $a, 'oswp-card__title' ); ?>
							<?php $this->render_meta( $a, 'oswp-card' ); ?>
							<?php $this->render_excerpt( $a, 'oswp-card__excerpt' ); ?>
							<?php $this->render_button( $a, 'oswp-card__btn' ); ?>
						</div>
					</article>
				</div>
			<?php endwhile; ?>
		</div>
		<?php
	}

	/* ================================================================
	   OVERLAY Layout
	   ================================================================ */
	protected function render_overlay( $a, $query, $id ) {
		$css_vars  = $this->get_css_variables( $a );
		$slick     = $this->get_slider_settings( $a );
		$hover     = $a['hoverEffect'] ? ' oswp-card--hover' : '';
		$editor    = $this->is_editor_preview() ? ' oswp-editor-preview' : '';
		$gradient  = $a['overlayGradient'] ? ' oswp-overlay--gradient' : '';
		$dots_cls  = ' oswp-dots--' . sanitize_html_class( $a['dotsStyle'] );
		$dots_pos  = ' oswp-dots--' . sanitize_html_class( $a['dotsPosition'] );
		?>
		<div id="<?php echo esc_attr( $id ); ?>"
			 class="oswp-post-carousel-wrapper oswp-layout--overlay<?php echo esc_attr( $editor . $dots_cls . $dots_pos ); ?>"
			 data-layout="overlay"
			 data-slick='<?php echo esc_attr( $slick ); ?>'
			 style="<?php echo esc_attr( $css_vars ); ?>">

			<?php while ( $query->have_posts() ) : $query->the_post(); ?>
				<div class="oswp-carousel-slide">
					<article class="oswp-overlay<?php echo esc_attr( $hover ); ?>">
						<div class="oswp-overlay__image">
							<?php if ( has_post_thumbnail() ) : ?>
								<?php the_post_thumbnail( 'large', [ 'class' => 'oswp-overlay__img' ] ); ?>
							<?php endif; ?>
							<div class="oswp-overlay__gradient<?php echo esc_attr( $gradient ); ?>"></div>
						</div>

						<div class="oswp-overlay__content">
							<?php $this->render_category_badge( $a, 'oswp-overlay__badge' ); ?>

							<?php if ( $a['showTitle'] ) : ?>
								<h3 class="oswp-overlay__title">
									<a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
								</h3>
							<?php endif; ?>

							<?php if ( $a['showDate'] || $a['showAuthor'] ) : ?>
								<div class="oswp-overlay__meta">
									<?php if ( $a['showAuthor'] ) : ?>
										<span><?php echo esc_html__( 'By', 'oswp-posts' ) . ' '; the_author(); ?></span>
									<?php endif; ?>
									<?php if ( $a['showDate'] ) : ?>
										<span><?php echo esc_html( get_the_date() ); ?></span>
									<?php endif; ?>
								</div>
							<?php endif; ?>

							<?php $this->render_excerpt( $a, 'oswp-overlay__excerpt' ); ?>
							<?php $this->render_button( $a, 'oswp-overlay__btn' ); ?>
						</div>
					</article>
				</div>
			<?php endwhile; ?>
		</div>
		<?php
	}

	/* ================================================================
	   TICKER Layout
	   ================================================================ */
	protected function render_ticker( $a, $query, $id ) {
		$ticker_settings = wp_json_encode( [
			'slidesToShow'   => 1,
			'slidesToScroll' => 1,
			'autoplay'       => (bool) $a['autoplay'],
			'autoplaySpeed'  => (int) $a['tickerSpeed'],
			'pauseOnHover'   => true,
			'infinite'       => true,
			'speed'          => (int) $a['speed'],
			'dots'           => false,
			'arrows'         => false,
			'fade'           => true,
			'adaptiveHeight' => false,
		] );

		$label_style = sprintf(
			'background:%s;color:%s',
			$this->hex( $a['tickerLabelBg'] ),
			$this->hex( $a['tickerLabelColor'] )
		);
		$ticker_style = sprintf(
			'background:%s;color:%s',
			$this->hex( $a['tickerBgColor'] ),
			$this->hex( $a['tickerTextColor'] )
		);
		?>
		<div id="<?php echo esc_attr( $id ); ?>"
			 class="oswp-ticker-wrapper"
			 style="<?php echo esc_attr( $ticker_style ); ?>">

			<div class="oswp-ticker__label" style="<?php echo esc_attr( $label_style ); ?>">
				<span class="oswp-ticker__pulse"></span>
				<?php echo esc_html( $a['tickerLabel'] ); ?>
			</div>

			<div class="oswp-ticker__items" data-slick='<?php echo esc_attr( $ticker_settings ); ?>'>
				<?php while ( $query->have_posts() ) : $query->the_post(); ?>
					<div class="oswp-ticker__item">
						<a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
					</div>
				<?php endwhile; ?>
			</div>

			<div class="oswp-ticker__nav">
				<button type="button" class="oswp-ticker__prev" aria-label="<?php esc_attr_e( 'Previous', 'oswp-posts' ); ?>">
					<svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor"><path d="M15.41 7.41L14 6l-6 6 6 6 1.41-1.41L10.83 12z"/></svg>
				</button>
				<button type="button" class="oswp-ticker__next" aria-label="<?php esc_attr_e( 'Next', 'oswp-posts' ); ?>">
					<svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor"><path d="M10 6L8.59 7.41 13.17 12l-4.58 4.59L10 18l6-6z"/></svg>
				</button>
			</div>
		</div>
		<?php
	}

	/* ================================================================
	   HERO Layout
	   ================================================================ */
	protected function render_hero( $a, $query, $id ) {
		$css_vars  = $this->get_css_variables( $a );
		$editor    = $this->is_editor_preview() ? ' oswp-editor-preview' : '';
		$dir       = $a['heroLayout'] === 'text-right' ? ' oswp-hero--reversed' : '';
		$total     = $query->post_count;

		$arrow_style = sanitize_html_class( $a['arrowStyle'] );
		$prev_svg    = '<svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor"><path d="M15.41 7.41L14 6l-6 6 6 6 1.41-1.41L10.83 12z"/></svg>';
		$next_svg    = '<svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor"><path d="M10 6L8.59 7.41 13.17 12l-4.58 4.59L10 18l6-6z"/></svg>';

		$hero_settings = wp_json_encode( [
			'slidesToShow'   => 1,
			'slidesToScroll' => 1,
			'autoplay'       => (bool) $a['autoplay'],
			'autoplaySpeed'  => (int) $a['autoplaySpeed'],
			'pauseOnHover'   => (bool) $a['pauseOnHover'],
			'infinite'       => (bool) $a['infinite'],
			'speed'          => (int) $a['speed'],
			'dots'           => (bool) $a['dots'],
			'arrows'         => (bool) $a['arrows'],
			'fade'           => true,
			'adaptiveHeight' => false,
			'prevArrow'      => '<button type="button" class="slick-prev oswp-arrow oswp-arrow--' . $arrow_style . '" aria-label="Previous">' . $prev_svg . '</button>',
			'nextArrow'      => '<button type="button" class="slick-next oswp-arrow oswp-arrow--' . $arrow_style . '" aria-label="Next">' . $next_svg . '</button>',
		] );
		?>
		<div id="<?php echo esc_attr( $id ); ?>"
			 class="oswp-hero-wrapper<?php echo esc_attr( $dir . $editor ); ?>"
			 style="<?php echo esc_attr( $css_vars ); ?>">

			<div class="oswp-hero__slider" data-slick='<?php echo esc_attr( $hero_settings ); ?>'>
				<?php while ( $query->have_posts() ) : $query->the_post(); ?>
					<div class="oswp-hero__slide">
						<div class="oswp-hero__text">
							<?php if ( $a['showLiveBadge'] ) : ?>
								<div class="oswp-hero__badge" style="color:<?php echo esc_attr( $this->hex( $a['liveBadgeColor'] ) ); ?>">
									<span class="oswp-hero__badge-dot" style="background:<?php echo esc_attr( $this->hex( $a['liveBadgeColor'] ) ); ?>"></span>
									<?php echo esc_html( $a['liveBadgeText'] ); ?>
								</div>
							<?php endif; ?>

							<?php if ( $a['showTitle'] ) : ?>
								<h2 class="oswp-hero__title">
									<a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
								</h2>
							<?php endif; ?>

							<?php if ( $a['showExcerpt'] ) : ?>
								<div class="oswp-hero__excerpt">
									<?php echo wp_kses_post( $this->get_limited_excerpt( $a['excerptLimit'] ) ); ?>
								</div>
							<?php endif; ?>

							<?php if ( $a['showTimeAgo'] ) : ?>
								<div class="oswp-hero__time"><?php echo esc_html( $this->get_time_ago() ); ?></div>
							<?php endif; ?>

							<?php $this->render_button( $a, 'oswp-hero__btn' ); ?>
						</div>

						<div class="oswp-hero__image">
							<?php if ( has_post_thumbnail() ) : ?>
								<a href="<?php the_permalink(); ?>">
									<?php the_post_thumbnail( 'large', [ 'class' => 'oswp-hero__img' ] ); ?>
								</a>
							<?php endif; ?>

							<?php if ( $a['showCategory'] ) : ?>
								<?php $cats = get_the_category(); if ( ! empty( $cats ) ) : ?>
									<span class="oswp-hero__cat-badge"><?php echo esc_html( $cats[0]->name ); ?></span>
								<?php endif; ?>
							<?php endif; ?>
						</div>
					</div>
				<?php endwhile; ?>
			</div>

			<?php if ( $a['showImageCounter'] ) : ?>
				<div class="oswp-hero__counter">
					<span class="oswp-hero__counter-current">1</span>
					<span class="oswp-hero__counter-sep">/</span>
					<span class="oswp-hero__counter-total"><?php echo esc_html( $total ); ?></span>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/* ================================================================
	   Shared partial renderers
	   ================================================================ */

	/** Category badge */
	protected function render_category_badge( $a, $class ) {
		if ( ! $a['showCategory'] ) {
			return;
		}
		$cats = get_the_category();
		if ( empty( $cats ) ) {
			return;
		}
		?>
		<span class="<?php echo esc_attr( $class ); ?>">
			<a href="<?php echo esc_url( get_category_link( $cats[0]->term_id ) ); ?>">
				<?php echo esc_html( $cats[0]->name ); ?>
			</a>
		</span>
		<?php
	}

	/** Title */
	protected function render_title( $a, $class ) {
		if ( ! $a['showTitle'] ) {
			return;
		}
		?>
		<h3 class="<?php echo esc_attr( $class ); ?>">
			<a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
		</h3>
		<?php
	}

	/** Meta (author + date) */
	protected function render_meta( $a, $prefix ) {
		if ( ! $a['showDate'] && ! $a['showAuthor'] ) {
			return;
		}
		?>
		<div class="<?php echo esc_attr( $prefix ); ?>__meta">
			<?php if ( $a['showAuthor'] ) : ?>
				<span class="<?php echo esc_attr( $prefix ); ?>__author">
					<svg viewBox="0 0 24 24" width="14" height="14" fill="currentColor"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
					<?php echo esc_html__( 'By', 'oswp-posts' ) . ' '; the_author(); ?>
				</span>
			<?php endif; ?>
			<?php if ( $a['showDate'] ) : ?>
				<span class="<?php echo esc_attr( $prefix ); ?>__date">
					<svg viewBox="0 0 24 24" width="14" height="14" fill="currentColor"><path d="M19 3h-1V1h-2v2H8V1H6v2H5c-1.11 0-1.99.9-1.99 2L3 19c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V8h14v11zM9 10H7v2h2v-2zm4 0h-2v2h2v-2zm4 0h-2v2h2v-2z"/></svg>
					<?php echo esc_html( get_the_date() ); ?>
				</span>
			<?php endif; ?>
		</div>
		<?php
	}

	/** Excerpt */
	protected function render_excerpt( $a, $class ) {
		if ( ! $a['showExcerpt'] ) {
			return;
		}
		?>
		<div class="<?php echo esc_attr( $class ); ?>">
			<?php echo wp_kses_post( $this->get_limited_excerpt( $a['excerptLimit'] ) ); ?>
		</div>
		<?php
	}

	/** Read More / Button */
	protected function render_button( $a, $class ) {
		if ( ! $a['showReadMore'] ) {
			return;
		}
		?>
		<a href="<?php the_permalink(); ?>" class="<?php echo esc_attr( $class ); ?>">
			<?php echo esc_html( $a['readMoreText'] ); ?>
			<svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor"><path d="M10 6L8.59 7.41 13.17 12l-4.58 4.59L10 18l6-6z"/></svg>
		</a>
		<?php
	}
}
