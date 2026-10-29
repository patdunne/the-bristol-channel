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

/* ------------------------------------------------------------ Share bar
 *
 * A server-rendered block, <!-- wp:bristol-channel/share /-->, used in templates/single.html.
 * Plain share links (no plugin, no tracking scripts) plus a Copy link button. To change the networks,
 * edit the $networks list below; LinkedIn is included but switched off.
 * Icons are from Tabler Icons (MIT licence).
 */

function bc_share_icon( $name ) {
	$paths = array(
		'x'        => '<path d="M4 4l11.733 16h4.267l-11.733 -16l-4.267 0"/><path d="M4 20l6.768 -6.768m2.46 -2.46l6.772 -6.772"/>',
		'facebook' => '<path d="M7 10v4h3v7h4v-7h3l1 -4h-4v-2a1 1 0 0 1 1 -1h3v-4h-3a5 5 0 0 0 -5 5v2h-3"/>',
		'bluesky'  => '<path d="M6.335 5.144c-1.654 -1.199 -4.335 -2.127 -4.335 .826c0 .59 .35 4.953 .556 5.661c.713 2.463 3.13 2.75 5.444 2.369c-4.045 .665 -4.889 3.208 -2.667 5.41c1.03 1.018 1.913 1.59 2.667 1.59c2 0 3.134 -2.769 3.5 -3.5c.333 -.667 .5 -1.167 .5 -1.5c0 .333 .167 .833 .5 1.5c.366 .731 1.5 3.5 3.5 3.5c.754 0 1.637 -.571 2.667 -1.59c2.222 -2.203 1.378 -4.746 -2.667 -5.41c2.314 .38 4.73 .094 5.444 -2.369c.206 -.708 .556 -5.072 .556 -5.661c0 -2.953 -2.68 -2.025 -4.335 -.826c-2.293 1.662 -4.76 5.048 -5.665 6.856c-.905 -1.808 -3.372 -5.194 -5.665 -6.856"/>',
		'whatsapp' => '<path d="M3 21l1.65 -3.8a9 9 0 1 1 3.4 2.9l-5.05 .9"/><path d="M9 10a.5 .5 0 0 0 1 0v-1a.5 .5 0 0 0 -1 0v1a5 5 0 0 0 5 5h1a.5 .5 0 0 0 0 -1h-1a.5 .5 0 0 0 0 1"/>',
		'linkedin' => '<path d="M8 11v5"/><path d="M8 8v.01"/><path d="M12 16v-5"/><path d="M16 16v-3a2 2 0 1 0 -4 0"/><path d="M3 7a4 4 0 0 1 4 -4h10a4 4 0 0 1 4 4v10a4 4 0 0 1 -4 4h-10a4 4 0 0 1 -4 -4l0 -10"/>',
		'mail'     => '<path d="M3 7a2 2 0 0 1 2 -2h14a2 2 0 0 1 2 2v10a2 2 0 0 1 -2 2h-14a2 2 0 0 1 -2 -2v-10"/><path d="M3 7l9 6l9 -6"/>',
		'link'     => '<path d="M9 15l6 -6"/><path d="M11 6l.463 -.536a5 5 0 0 1 7.071 7.072l-.534 .464"/><path d="M13 18l-.397 .534a5.068 5.068 0 0 1 -7.127 0a4.972 4.972 0 0 1 0 -7.071l.524 -.463"/>',
	);
	$inner = isset( $paths[ $name ] ) ? $paths[ $name ] : '';
	return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $inner . '</svg>';
}

add_action(
	'init',
	function () {
		register_block_type(
			'bristol-channel/share',
			array(
				'api_version'     => 3,
				'uses_context'    => array( 'postId' ),
				'render_callback' => function ( $attributes, $content, $block ) {
					$post_id = ( $block && ! empty( $block->context['postId'] ) ) ? (int) $block->context['postId'] : (int) get_the_ID();
					if ( ! $post_id ) {
						return '';
					}
					$url   = rawurlencode( get_permalink( $post_id ) );
					$title = rawurlencode( html_entity_decode( wp_strip_all_tags( get_the_title( $post_id ) ), ENT_QUOTES, 'UTF-8' ) );

					// slug => array( label, link, enabled ).
					$networks = array(
						'x'        => array( 'Share on X', 'https://x.com/intent/post?url=' . $url . '&text=' . $title, true ),
						'facebook' => array( 'Share on Facebook', 'https://www.facebook.com/sharer/sharer.php?u=' . $url, true ),
						'bluesky'  => array( 'Share on Bluesky', 'https://bsky.app/intent/compose?text=' . $title . '%20' . $url, true ),
						'whatsapp' => array( 'Share on WhatsApp', 'https://wa.me/?text=' . $title . '%20' . $url, true ),
						'linkedin' => array( 'Share on LinkedIn', 'https://www.linkedin.com/sharing/share-offsite/?url=' . $url, false ),
						'mail'     => array( 'Share by email', 'mailto:?subject=' . $title . '&body=' . $url, true ),
					);

					static $script_added = false;
					if ( ! $script_added ) {
						$script_added = true;
						add_action(
							'wp_footer',
							function () {
								echo '<script>(function(){document.addEventListener("click",function(e){var b=e.target.closest("[data-bc-copy]");if(!b){return;}var s=b.parentNode.querySelector(".bc-share__status");var done=function(){if(s){s.textContent="Link copied";setTimeout(function(){s.textContent="";},2500);}};var u=b.getAttribute("data-bc-copy");if(navigator.clipboard&&navigator.clipboard.writeText){navigator.clipboard.writeText(u).then(done,function(){window.prompt("Copy this link",u);});}else{window.prompt("Copy this link",u);}});})();</script>';
							},
							99
						);
					}

					$out  = '<div class="bc-share"><span class="bc-share__label">Share</span>';
					foreach ( $networks as $slug => $n ) {
						if ( ! $n[2] ) {
							continue;
						}
						$external = ( 0 !== strpos( $n[1], 'mailto:' ) );
						$out     .= '<a class="bc-share__btn bc-share__btn--' . esc_attr( $slug ) . '" href="' . esc_url( $n[1] ) . '"' . ( $external ? ' target="_blank" rel="noopener noreferrer"' : '' ) . ' aria-label="' . esc_attr( $n[0] ) . '" title="' . esc_attr( $n[0] ) . '">' . bc_share_icon( $slug ) . '</a>';
					}
					$out .= '<button type="button" class="bc-share__btn bc-share__btn--copy" data-bc-copy="' . esc_url( get_permalink( $post_id ) ) . '" aria-label="Copy link" title="Copy link">' . bc_share_icon( 'link' ) . '</button>';
					$out .= '<span class="bc-share__status" role="status" aria-live="polite"></span></div>';
					return $out;
				},
			)
		);
	}
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
