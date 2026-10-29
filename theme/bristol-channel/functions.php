<?php
/**
 * The Bristol Channel — theme setup.
 *
 * Keeps theme code small. Anything that should survive a theme change (social feeds,
 * custom post types, ad slots) belongs in a separate plugin, not here.
 *
 * @package bristol-channel
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'BC_VERSION', wp_get_theme()->get( 'Version' ) );

/* ------------------------------------------------------------------ Assets */

add_action(
	'wp_enqueue_scripts',
	function () {
		wp_enqueue_style( 'bristol-channel', get_theme_file_uri( 'assets/css/theme.css' ), array(), BC_VERSION );
	}
);

add_action(
	'after_setup_theme',
	function () {
		add_theme_support( 'post-thumbnails' );
		add_theme_support( 'title-tag' );
		add_theme_support( 'responsive-embeds' );
		add_theme_support( 'wp-block-styles' );
		add_theme_support( 'editor-styles' );
		add_editor_style( 'assets/css/theme.css' );
		add_image_size( 'bc-card', 900, 600, true );
	}
);

/* ------------------------------------------------------ Pattern library
 * Keep the editor's pattern inserter short: only this theme's own patterns (patterns/ folder)
 * are offered. WordPress's built-in patterns and the online pattern directory are switched off.
 * Set BC_LIMIT_PATTERNS to true to switch this on. To keep a particular core pattern, skip its name
 * inside the unregister loop.
 */
define( 'BC_LIMIT_PATTERNS', false ); // true = only this theme's patterns; false = WordPress's built-in and online patterns too.

if ( BC_LIMIT_PATTERNS ) {
	add_filter( 'should_load_remote_block_patterns', '__return_false' );

	add_action(
		'after_setup_theme',
		function () {
			remove_theme_support( 'core-block-patterns' );
		},
		11
	);

	add_action(
		'init',
		function () {
			if ( ! class_exists( 'WP_Block_Patterns_Registry' ) ) {
				return;
			}
			$registry = WP_Block_Patterns_Registry::get_instance();
			foreach ( $registry->get_all_registered() as $pattern ) {
				if ( 0 === strpos( $pattern['name'], 'core/' ) ) {
					unregister_block_pattern( $pattern['name'] );
				}
			}
		},
		99
	);
}

/* ------------------------------------------------------ Breakout quote
 * A block style for Pullquote and Quote: Block settings > Styles > "Breakout quote".
 * Floats right beside the story text on desktop; full width on phones.
 */
add_action(
	'init',
	function () {
		foreach ( array( 'core/pullquote', 'core/quote' ) as $block ) {
			register_block_style(
				$block,
				array(
					'name'  => 'bc-breakout',
					'label' => 'Breakout quote',
				)
			);
		}
	}
);

/* ------------------------------------------------- Front page story slots
 *
 * Lead story   : newest post tagged "featured" (falls back to the newest post).
 * Cards        : every other recent post (up to 60), newest first, three per row, skipping the lead.
 * No story appears twice on the front page.
 * The slots are Query Loop blocks with the queryIds 901 and 902 (see the filter below).
 */

/**
 * "We the Furious" (WTF) posts: any post in the category with slug "wtf" goes into the right-hand
 * column (post content only: no headline, excerpt or tags needed). They are kept out of the lead,
 * the cards, related stories, section pages, search and the feed.
 */
function bc_wtf_term_id() {
	static $id = null;
	if ( null === $id ) {
		$term = get_term_by( 'slug', 'wtf', 'category' );
		$id   = $term ? (int) $term->term_id : 0;
	}
	return $id;
}

function bc_lead_post_id() {
	static $id = null;
	if ( null !== $id ) {
		return $id;
	}
	$base = array(
		'post_type'           => 'post',
		'post_status'         => 'publish',
		'posts_per_page'      => 1,
		'fields'              => 'ids',
		'no_found_rows'       => true,
		'ignore_sticky_posts' => true,
		'category__not_in'    => array( bc_wtf_term_id() ),
	);
	$found = get_posts(
		$base + array(
			'tax_query' => array(
				array(
					'taxonomy' => 'post_tag',
					'field'    => 'slug',
					'terms'    => 'featured',
				),
			),
		)
	);
	if ( ! $found ) {
		$found = get_posts( $base );
	}
	$id = $found ? (int) $found[0] : 0;
	return $id;
}

function bc_card_post_ids() {
	static $ids = null;
	if ( null !== $ids ) {
		return $ids;
	}
	$ids = array_map(
		'intval',
		get_posts(
			array(
				'post_type'           => 'post',
				'post_status'         => 'publish',
				'posts_per_page'      => 60,
				'post__not_in'        => array( bc_lead_post_id() ),
				'category__not_in'    => array( bc_wtf_term_id() ),
				'fields'              => 'ids',
				'no_found_rows'       => true,
				'ignore_sticky_posts' => true,
			)
		)
	);
	return $ids;
}

/**
 * Query Loop blocks are identified by their queryId (set in the templates):
 * 901 lead, 902 cards, 903 latest (not used now), 904 related stories, 905 the WTF column.
 */
add_filter(
	'query_loop_block_query_vars',
	function ( $query, $block ) {
		$qid = isset( $block->context['queryId'] ) ? (int) $block->context['queryId'] : 0;
		if ( $qid < 901 || $qid > 905 ) {
			return $query;
		}
		$query['ignore_sticky_posts'] = true;

		if ( 901 === $qid ) {
			$query['post__in']       = array( bc_lead_post_id() );
			$query['posts_per_page'] = 1;
		} elseif ( 902 === $qid ) {
			$query['post__in']       = bc_card_post_ids() ? bc_card_post_ids() : array( 0 );
			$query['orderby']        = 'post__in';
			$query['posts_per_page'] = 60;
		} elseif ( 903 === $qid ) {
			$query['post__not_in']      = array_merge( array( bc_lead_post_id() ), bc_card_post_ids() );
			$query['posts_per_page']    = 5;
		} elseif ( 905 === $qid ) {
			$query['category_name']  = 'wtf';
			$query['posts_per_page'] = 8;
			$query['orderby']        = 'date';
			$query['order']          = 'DESC';
		} else {
			$post_id = get_queried_object_id();
			$tags    = wp_get_post_terms( $post_id, 'post_tag', array( 'fields' => 'ids' ) );
			$query['post__not_in']     = array( $post_id );
			$query['posts_per_page']   = 3;
			$query['category__not_in'] = array( bc_wtf_term_id() );
			if ( $tags ) {
				$query['tag__in'] = $tags;
			}
		}
		return $query;
	},
	10,
	2
);

/* ------------------------------------------------ Hide internal labels from readers
 *
 * - The "featured" tag only marks the lead story, so readers never see it.
 * - The default "Uncategorized" category is hidden, so a story without a real category shows no label.
 * The small label above a headline is the story's category (set in each template); tags are used for
 * the section pages, the lead story and the "Tagged:" line at the bottom of a story.
 */

add_filter(
	'get_the_terms',
	function ( $terms, $post_id, $taxonomy ) {
		if ( is_admin() || ! is_array( $terms ) ) {
			return $terms;
		}
		$hide = array(
			'post_tag' => 'featured',
			'category' => 'uncategorized',
		);
		if ( ! isset( $hide[ $taxonomy ] ) ) {
			return $terms;
		}
		return array_values(
			array_filter(
				$terms,
				function ( $term ) use ( $hide, $taxonomy ) {
					return $hide[ $taxonomy ] !== $term->slug;
				}
			)
		);
	},
	10,
	3
);

/* WTF posts stay out of the main listings (home, sections, search, feed) and have no page of their own. */

add_action(
	'pre_get_posts',
	function ( $q ) {
		if ( is_admin() || ! $q->is_main_query() || ! bc_wtf_term_id() || $q->is_category( 'wtf' ) ) {
			return;
		}
		if ( $q->is_home() || $q->is_archive() || $q->is_search() || $q->is_feed() ) {
			$q->set( 'category__not_in', array_merge( (array) $q->get( 'category__not_in' ), array( bc_wtf_term_id() ) ) );
		}
	}
);

add_action(
	'template_redirect',
	function () {
		if ( is_singular( 'post' ) && bc_wtf_term_id() && has_category( 'wtf', get_queried_object_id() ) ) {
			wp_safe_redirect( home_url( '/' ), 302 );
			exit;
		}
	}
);

add_action(
	'wp_enqueue_scripts',
	function () {
		wp_add_inline_style( 'bristol-channel', '.bc-rail .wp-block-post-template{list-style:none;margin:0;padding:0}.bc-rail .wp-block-post-template>li{margin:0 0 6px}.bc-rail .wp-block-post-content>*{margin-top:0;margin-bottom:0}.bc-rail .wp-block-embed{margin:0}.bc-rail .twitter-tweet,.bc-rail .twitter-tweet-rendered{margin-top:0!important;margin-bottom:0!important}' );
	},
	20
);

/* ---------------------------------------------------- Block output tweaks
 *
 * Featured Image blocks with class bc-with-caption get the image's caption underneath
 * (the caption and photo credit are typed in the Media Library "Caption" field).
 */

add_filter(
	'render_block',
	function ( $content, $block, $instance ) {
		$name  = isset( $block['blockName'] ) ? $block['blockName'] : '';
		$class = isset( $block['attrs']['className'] ) ? $block['attrs']['className'] : '';
		if ( 'core/post-featured-image' !== $name || false === strpos( $class, 'bc-with-caption' ) ) {
			return $content;
		}
		$post_id = ( $instance && isset( $instance->context['postId'] ) ) ? (int) $instance->context['postId'] : 0;
		$thumb   = $post_id ? get_post_thumbnail_id( $post_id ) : 0;
		$cap     = $thumb ? wp_get_attachment_caption( $thumb ) : '';
		if ( $cap ) {
			return $content . '<p class="bc-caption">' . wp_kses_post( $cap ) . '</p>';
		}
		return $content;
	},
	10,
	3
);
