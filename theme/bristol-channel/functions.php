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

/* ------------------------------------------------- Front page story slots
 *
 * Lead story   : newest post tagged "featured" (falls back to the newest post).
 * Cards        : the next three newest posts, skipping the lead.
 * No story appears twice on the front page.
 * The slots are Query Loop blocks with the queryIds 901 and 902 (see the filter below).
 */

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
				'posts_per_page'      => 3,
				'post__not_in'        => array( bc_lead_post_id() ),
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
 * 901 lead, 902 cards, 903 latest (not used on the front page now), 904 related stories.
 */
add_filter(
	'query_loop_block_query_vars',
	function ( $query, $block ) {
		$qid = isset( $block->context['queryId'] ) ? (int) $block->context['queryId'] : 0;
		if ( $qid < 901 || $qid > 904 ) {
			return $query;
		}
		$query['ignore_sticky_posts'] = true;

		if ( 901 === $qid ) {
			$query['post__in']       = array( bc_lead_post_id() );
			$query['posts_per_page'] = 1;
		} elseif ( 902 === $qid ) {
			$query['post__in']       = bc_card_post_ids() ? bc_card_post_ids() : array( 0 );
			$query['orderby']        = 'post__in';
			$query['posts_per_page'] = 3;
		} elseif ( 903 === $qid ) {
			$query['post__not_in']      = array_merge( array( bc_lead_post_id() ), bc_card_post_ids() );
			$query['posts_per_page']    = 5;
			$GLOBALS['bc_latest_index'] = 0; // Resets the "first item always has a photo" counter.
		} else {
			$post_id = get_queried_object_id();
			$tags    = wp_get_post_terms( $post_id, 'post_tag', array( 'fields' => 'ids' ) );
			$query['post__not_in']   = array( $post_id );
			$query['posts_per_page'] = 3;
			if ( $tags ) {
				$query['tag__in'] = $tags;
			}
		}
		return $query;
	},
	10,
	2
);

/* ------------------------------------------------ Hide the internal "featured" tag from readers */

add_filter(
	'get_the_terms',
	function ( $terms, $post_id, $taxonomy ) {
		if ( 'post_tag' !== $taxonomy || is_admin() || ! is_array( $terms ) ) {
			return $terms;
		}
		$terms = array_values(
			array_filter(
				$terms,
				function ( $term ) {
					return 'featured' !== $term->slug;
				}
			)
		);

		// Kicker above a headline: show only one tag, preferring the section tags in menu order.
		if ( ! empty( $GLOBALS['bc_kicker_render'] ) && count( $terms ) > 1 ) {
			$order = array( 'bristol', 'bath', 'news', 'politics', 'business', 'opinion' );
			usort(
				$terms,
				function ( $a, $b ) use ( $order ) {
					$ia = array_search( $a->slug, $order, true );
					$ib = array_search( $b->slug, $order, true );
					$ia = false === $ia ? 99 : $ia;
					$ib = false === $ib ? 99 : $ib;
					return $ia <=> $ib;
				}
			);
			$terms = array( $terms[0] );
		}
		return $terms;
	},
	10,
	3
);

add_filter(
	'pre_render_block',
	function ( $pre, $block ) {
		if ( 'core/post-terms' === ( $block['blockName'] ?? '' ) && false !== strpos( $block['attrs']['className'] ?? '', 'bc-kicker' ) ) {
			$GLOBALS['bc_kicker_render'] = true;
		}
		return $pre;
	},
	10,
	2
);

add_filter(
	'render_block',
	function ( $content, $block ) {
		if ( 'core/post-terms' === ( $block['blockName'] ?? '' ) ) {
			unset( $GLOBALS['bc_kicker_render'] );
		}
		return $content;
	},
	5,
	2
);

/* ------------------------------------------ "Show photo in secondary slots" checkbox */

add_action(
	'add_meta_boxes',
	function () {
		add_meta_box(
			'bc_secondary_photo',
			__( 'Front page photo', 'bristol-channel' ),
			function ( $post ) {
				wp_nonce_field( 'bc_secondary_photo', 'bc_secondary_photo_nonce' );
				$checked = (bool) get_post_meta( $post->ID, '_bc_show_photo', true );
				echo '<label><input type="checkbox" name="bc_show_photo" value="1" ' . checked( $checked, true, false ) . '> ';
				echo esc_html__( 'Show photo in secondary slots', 'bristol-channel' ) . '</label>';
				echo '<p class="description">' . esc_html__( 'Shows the featured image when this story appears in the Latest list or as a card under the lead. The first item in the Latest list always shows its photo.', 'bristol-channel' ) . '</p>';
			},
			'post',
			'side',
			'default',
			array( '__back_compat_meta_box' => false )
		);
	}
);

add_action(
	'save_post_post',
	function ( $post_id ) {
		if ( ! isset( $_POST['bc_secondary_photo_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['bc_secondary_photo_nonce'] ) ), 'bc_secondary_photo' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		if ( ! empty( $_POST['bc_show_photo'] ) ) {
			update_post_meta( $post_id, '_bc_show_photo', 1 );
		} else {
			delete_post_meta( $post_id, '_bc_show_photo' );
		}
	}
);

/* ---------------------------------------------------- Block output tweaks
 *
 * 1. Post Title blocks with class bc-photo-slot get the story's photo above them,
 *    when the "Show photo" box is ticked (or when it is the first Latest item).
 * 2. Featured Image blocks with class bc-with-caption get the image's caption underneath
 *    (the caption and photo credit are typed in the Media Library "Caption" field).
 */

add_filter(
	'render_block',
	function ( $content, $block, $instance ) {
		$name  = isset( $block['blockName'] ) ? $block['blockName'] : '';
		$class = isset( $block['attrs']['className'] ) ? $block['attrs']['className'] : '';
		if ( ! $class ) {
			return $content;
		}
		$post_id = ( $instance && isset( $instance->context['postId'] ) ) ? (int) $instance->context['postId'] : 0;
		if ( ! $post_id ) {
			return $content;
		}

		if ( in_array( $name, array( 'core/post-title', 'core/post-terms' ), true ) && false !== strpos( $class, 'bc-photo-slot' ) ) {
			$show = (bool) get_post_meta( $post_id, '_bc_show_photo', true );
			if ( false !== strpos( $class, 'bc-slot-latest' ) ) {
				$GLOBALS['bc_latest_index'] = isset( $GLOBALS['bc_latest_index'] ) ? $GLOBALS['bc_latest_index'] + 1 : 1;
				if ( 1 === $GLOBALS['bc_latest_index'] ) {
					$show = true;
				}
			}
			if ( $show && has_post_thumbnail( $post_id ) ) {
				$img   = get_the_post_thumbnail( $post_id, 'bc-card', array( 'loading' => 'lazy' ) );
				$photo = '<figure class="bc-photo"><a href="' . esc_url( get_permalink( $post_id ) ) . '" tabindex="-1" aria-hidden="true">' . $img . '</a></figure>';
				return $photo . $content;
			}
			return $content;
		}

		if ( 'core/post-featured-image' === $name && false !== strpos( $class, 'bc-with-caption' ) ) {
			$thumb = get_post_thumbnail_id( $post_id );
			$cap   = $thumb ? wp_get_attachment_caption( $thumb ) : '';
			if ( $cap ) {
				return $content . '<p class="bc-caption">' . wp_kses_post( $cap ) . '</p>';
			}
		}
		return $content;
	},
	10,
	3
);
