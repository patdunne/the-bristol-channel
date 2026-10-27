<?php
/**
 * Plugin Name: Bristol Channel SEO
 * Description: Lightweight SEO for The Bristol Channel: meta descriptions, Open Graph / X cards, NewsArticle structured data, a Google News sitemap, and noindex rules. No dashboard, no upsells.
 * Version: 1.0.0
 * Author: Patrick Dunne
 * License: GPL-2.0-or-later
 * Text Domain: bristol-channel-seo
 *
 * @package bristol-channel-seo
 */

defined( 'ABSPATH' ) || exit;

final class BC_SEO {

	const VER = '1.0.0';

	public static function init() {
		add_action( 'init', array( __CLASS__, 'rewrite' ) );
		add_filter( 'query_vars', array( __CLASS__, 'query_vars' ) );
		add_action( 'template_redirect', array( __CLASS__, 'news_sitemap' ), 1 );
		add_filter( 'robots_txt', array( __CLASS__, 'robots_txt' ), 10, 2 );
		add_filter( 'wp_robots', array( __CLASS__, 'robots' ) );
		add_filter( 'document_title_parts', array( __CLASS__, 'title_parts' ) );
		add_action( 'wp_head', array( __CLASS__, 'head' ), 5 );
		add_action( 'add_meta_boxes', array( __CLASS__, 'meta_box' ) );
		add_action( 'save_post_post', array( __CLASS__, 'save' ) );
		add_filter( 'wp_sitemaps_posts_query_args', array( __CLASS__, 'sitemap_posts' ), 10, 2 );
		add_filter( 'wp_sitemaps_taxonomies_query_args', array( __CLASS__, 'sitemap_terms' ), 10, 2 );
		register_activation_hook( __FILE__, array( __CLASS__, 'activate' ) );
	}

	/* ------------------------------------------------------------ helpers */

	/** Term id of the WTF (We the Furious) category: those posts are never indexed. */
	private static function wtf_id() {
		$term = get_term_by( 'slug', 'wtf', 'category' );
		return $term ? (int) $term->term_id : 0;
	}

	private static function is_wtf( $post_id ) {
		return self::wtf_id() && has_category( 'wtf', $post_id );
	}

	private static function trim( $text, $max ) {
		$text = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( $text ) ) );
		if ( mb_strlen( $text ) <= $max ) {
			return $text;
		}
		return rtrim( mb_substr( $text, 0, $max - 1 ), " ,.;:-" ) . '…';
	}

	private static function post_description( $post ) {
		$custom = get_post_meta( $post->ID, '_bcseo_description', true );
		if ( $custom ) {
			return self::trim( $custom, 200 );
		}
		if ( has_excerpt( $post ) ) {
			return self::trim( $post->post_excerpt, 200 );
		}
		$body = wp_strip_all_tags( excerpt_remove_blocks( strip_shortcodes( $post->post_content ) ) );
		return self::trim( $body, 200 );
	}

	private static function post_image( $post_id ) {
		$thumb = get_post_thumbnail_id( $post_id );
		if ( ! $thumb ) {
			return null;
		}
		$src = wp_get_attachment_image_src( $thumb, 'full' );
		if ( ! $src ) {
			return null;
		}
		return array(
			'url'    => $src[0],
			'width'  => (int) $src[1],
			'height' => (int) $src[2],
			'alt'    => trim( (string) get_post_meta( $thumb, '_wp_attachment_image_alt', true ) ),
		);
	}

	private static function section_name( $post_id ) {
		$terms = get_the_terms( $post_id, 'category' );
		if ( is_array( $terms ) ) {
			foreach ( $terms as $t ) {
				if ( 'wtf' !== $t->slug && 'uncategorized' !== $t->slug ) {
					return $t->name;
				}
			}
		}
		return '';
	}

	private static function keywords( $post_id ) {
		$out   = array();
		$terms = get_the_terms( $post_id, 'post_tag' );
		if ( is_array( $terms ) ) {
			foreach ( $terms as $t ) {
				if ( 'featured' !== $t->slug ) {
					$out[] = $t->name;
				}
			}
		}
		return $out;
	}

	/* ----------------------------------------------------------- rewrite */

	public static function activate() {
		self::rewrite();
		flush_rewrite_rules( false );
		update_option( 'bcseo_ver', self::VER );
	}

	public static function rewrite() {
		add_rewrite_rule( '^news-sitemap\.xml$', 'index.php?bcseo_news=1', 'top' );
		if ( get_option( 'bcseo_ver' ) !== self::VER ) {
			flush_rewrite_rules( false );
			update_option( 'bcseo_ver', self::VER );
		}
	}

	public static function query_vars( $vars ) {
		$vars[] = 'bcseo_news';
		return $vars;
	}

	/* ------------------------------------------------------ Google News sitemap */

	public static function news_sitemap() {
		if ( ! get_query_var( 'bcseo_news' ) ) {
			return;
		}
		$args = array(
			'post_type'           => 'post',
			'post_status'         => 'publish',
			'posts_per_page'      => 1000,
			'orderby'             => 'date',
			'order'               => 'DESC',
			'no_found_rows'       => true,
			'ignore_sticky_posts' => true,
			'date_query'          => array( array( 'after' => '2 days ago' ) ),
		);
		if ( self::wtf_id() ) {
			$args['category__not_in'] = array( self::wtf_id() );
		}
		$posts = get_posts( $args );

		status_header( 200 );
		header( 'Content-Type: application/xml; charset=UTF-8' );
		header( 'X-Robots-Tag: noindex' );
		header( 'Cache-Control: public, max-age=300' );
		echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:news="http://www.google.com/schemas/sitemap-news/0.9">' . "\n";
		foreach ( $posts as $p ) {
			echo "\t<url>\n";
			echo "\t\t<loc>" . esc_url( get_permalink( $p ) ) . "</loc>\n";
			echo "\t\t<news:news>\n";
			echo "\t\t\t<news:publication><news:name>" . esc_xml( get_bloginfo( 'name' ) ) . "</news:name><news:language>en</news:language></news:publication>\n";
			echo "\t\t\t<news:publication_date>" . esc_xml( get_post_time( 'Y-m-d\TH:i:s\Z', true, $p ) ) . "</news:publication_date>\n";
			echo "\t\t\t<news:title>" . esc_xml( wp_strip_all_tags( get_the_title( $p ) ) ) . "</news:title>\n";
			echo "\t\t</news:news>\n";
			echo "\t</url>\n";
		}
		echo "</urlset>\n";
		exit;
	}

	public static function robots_txt( $output, $public ) {
		if ( $public ) {
			$output .= "\nSitemap: " . home_url( '/news-sitemap.xml' ) . "\n";
		}
		return $output;
	}

	/* ----------------------------------------- core sitemap + robots rules */

	public static function sitemap_posts( $args, $post_type ) {
		if ( 'post' === $post_type && self::wtf_id() ) {
			$args['category__not_in'] = array_merge( (array) ( $args['category__not_in'] ?? array() ), array( self::wtf_id() ) );
		}
		return $args;
	}

	public static function sitemap_terms( $args, $taxonomy ) {
		if ( 'category' === $taxonomy && self::wtf_id() ) {
			$args['exclude'] = array_merge( (array) ( $args['exclude'] ?? array() ), array( self::wtf_id() ) );
		}
		return $args;
	}

	public static function robots( $robots ) {
		if ( is_search() || is_404() || ( is_singular( 'post' ) && self::is_wtf( get_queried_object_id() ) ) || ( is_category( 'wtf' ) ) ) {
			$robots['noindex'] = true;
			$robots['follow']  = true;
			unset( $robots['index'] );
		}
		return $robots;
	}

	/* -------------------------------------------------------------- title */

	public static function title_parts( $parts ) {
		if ( is_singular( 'post' ) ) {
			$custom = get_post_meta( get_queried_object_id(), '_bcseo_title', true );
			if ( $custom ) {
				$parts['title'] = $custom;
			}
		}
		return $parts;
	}

	/* ------------------------------------------------------------- <head> */

	public static function head() {
		$site = get_bloginfo( 'name' );
		$tags = array();
		$ld   = array();

		if ( is_singular( 'post' ) ) {
			$post  = get_queried_object();
			$title = get_post_meta( $post->ID, '_bcseo_title', true );
			$title = $title ? $title : get_the_title( $post );
			$desc  = self::post_description( $post );
			$url   = get_permalink( $post );
			$img   = self::post_image( $post->ID );
			$sect  = self::section_name( $post->ID );

			$tags[] = array( 'name', 'description', $desc );
			$tags[] = array( 'property', 'og:type', 'article' );
			$tags[] = array( 'property', 'og:title', $title );
			$tags[] = array( 'property', 'og:description', $desc );
			$tags[] = array( 'property', 'og:url', $url );
			$tags[] = array( 'property', 'article:published_time', get_post_time( 'c', true, $post ) );
			$tags[] = array( 'property', 'article:modified_time', get_post_modified_time( 'c', true, $post ) );
			$tags[] = array( 'property', 'article:author', get_the_author_meta( 'display_name', $post->post_author ) );
			if ( $sect ) {
				$tags[] = array( 'property', 'article:section', $sect );
			}
			foreach ( self::keywords( $post->ID ) as $kw ) {
				$tags[] = array( 'property', 'article:tag', $kw );
			}
			if ( $img ) {
				$tags[] = array( 'property', 'og:image', $img['url'] );
				$tags[] = array( 'property', 'og:image:width', $img['width'] );
				$tags[] = array( 'property', 'og:image:height', $img['height'] );
				if ( $img['alt'] ) {
					$tags[] = array( 'property', 'og:image:alt', $img['alt'] );
				}
				$tags[] = array( 'name', 'twitter:image', $img['url'] );
			}
			$tags[] = array( 'name', 'twitter:card', $img ? 'summary_large_image' : 'summary' );
			$tags[] = array( 'name', 'twitter:title', $title );
			$tags[] = array( 'name', 'twitter:description', $desc );

			$article = array(
				'@context'         => 'https://schema.org',
				'@type'            => 'NewsArticle',
				'mainEntityOfPage' => array( '@type' => 'WebPage', '@id' => $url ),
				'headline'         => self::trim( $title, 110 ),
				'description'      => $desc,
				'datePublished'    => get_post_time( 'c', true, $post ),
				'dateModified'     => get_post_modified_time( 'c', true, $post ),
				'author'           => array(
					'@type' => 'Person',
					'name'  => get_the_author_meta( 'display_name', $post->post_author ),
					'url'   => get_author_posts_url( $post->post_author ),
				),
				'publisher'        => self::publisher(),
				'inLanguage'       => 'en-GB',
			);
			if ( $img ) {
				$article['image'] = array( $img['url'] );
			}
			if ( $sect ) {
				$article['articleSection'] = $sect;
			}
			$kws = self::keywords( $post->ID );
			if ( $kws ) {
				$article['keywords'] = implode( ', ', $kws );
			}
			$ld[] = $article;
		} elseif ( is_front_page() || is_home() ) {
			$desc   = get_bloginfo( 'description' );
			$tags[] = array( 'name', 'description', $desc );
			$tags[] = array( 'property', 'og:type', 'website' );
			$tags[] = array( 'property', 'og:title', $site );
			$tags[] = array( 'property', 'og:description', $desc );
			$tags[] = array( 'property', 'og:url', home_url( '/' ) );
			$tags[] = array( 'name', 'twitter:card', 'summary' );
			$tags[] = array( 'name', 'twitter:title', $site );
			$tags[] = array( 'name', 'twitter:description', $desc );
			$ld[]   = array(
				'@context'  => 'https://schema.org',
				'@type'     => 'WebSite',
				'name'      => $site,
				'url'       => home_url( '/' ),
				'publisher' => self::publisher(),
			);
		} elseif ( is_category() || is_tag() ) {
			$term   = get_queried_object();
			$desc   = $term->description ? self::trim( $term->description, 200 ) : sprintf( 'The latest %s stories from %s.', $term->name, $site );
			$tags[] = array( 'name', 'description', $desc );
			$tags[] = array( 'property', 'og:type', 'website' );
			$tags[] = array( 'property', 'og:title', $term->name . ' – ' . $site );
			$tags[] = array( 'property', 'og:description', $desc );
			$tags[] = array( 'property', 'og:url', get_term_link( $term ) );
		}

		if ( $tags ) {
			$tags[] = array( 'property', 'og:site_name', $site );
			$tags[] = array( 'property', 'og:locale', 'en_GB' );
			foreach ( $tags as $t ) {
				printf( "<meta %s=\"%s\" content=\"%s\" />\n", esc_attr( $t[0] ), esc_attr( $t[1] ), esc_attr( $t[2] ) );
			}
		}
		foreach ( $ld as $obj ) {
			echo '<script type="application/ld+json">' . wp_json_encode( $obj, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG ) . "</script>\n";
		}
	}

	private static function publisher() {
		$pub = array(
			'@type' => 'NewsMediaOrganization',
			'name'  => get_bloginfo( 'name' ),
			'url'   => home_url( '/' ),
		);
		$icon = get_site_icon_url( 512 );
		if ( $icon ) {
			$pub['logo'] = array( '@type' => 'ImageObject', 'url' => $icon );
		}
		return $pub;
	}

	/* ------------------------------------------------------------ meta box */

	public static function meta_box() {
		add_meta_box( 'bcseo', __( 'Search & sharing (optional)', 'bristol-channel-seo' ), array( __CLASS__, 'render_box' ), 'post', 'normal', 'default' );
	}

	public static function render_box( $post ) {
		wp_nonce_field( 'bcseo_save', 'bcseo_nonce' );
		$title = get_post_meta( $post->ID, '_bcseo_title', true );
		$desc  = get_post_meta( $post->ID, '_bcseo_description', true );
		echo '<p class="description">' . esc_html__( 'Leave blank to use the headline and the excerpt. Fill in only to override what Google and social sites show.', 'bristol-channel-seo' ) . '</p>';
		echo '<p><label for="bcseo_title"><strong>' . esc_html__( 'Search title', 'bristol-channel-seo' ) . '</strong></label><br>';
		echo '<input type="text" id="bcseo_title" name="bcseo_title" class="widefat" value="' . esc_attr( $title ) . '" placeholder="' . esc_attr__( 'Defaults to the headline', 'bristol-channel-seo' ) . '"></p>';
		echo '<p><label for="bcseo_description"><strong>' . esc_html__( 'Search description', 'bristol-channel-seo' ) . '</strong> <span id="bcseo_count" style="color:#757575"></span></label><br>';
		echo '<textarea id="bcseo_description" name="bcseo_description" class="widefat" rows="3" placeholder="' . esc_attr__( 'Defaults to the excerpt. Aim for 150 to 160 characters.', 'bristol-channel-seo' ) . '">' . esc_textarea( $desc ) . '</textarea></p>';
		echo '<script>(function(){var t=document.getElementById("bcseo_description"),c=document.getElementById("bcseo_count");if(!t||!c)return;function u(){c.textContent=t.value.length?"("+t.value.length+" characters)":"";}t.addEventListener("input",u);u();})();</script>';
	}

	public static function save( $post_id ) {
		if ( ! isset( $_POST['bcseo_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['bcseo_nonce'] ) ), 'bcseo_save' ) ) {
			return;
		}
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$fields = array(
			'_bcseo_title'       => 'bcseo_title',
			'_bcseo_description' => 'bcseo_description',
		);
		foreach ( $fields as $meta => $key ) {
			$val = isset( $_POST[ $key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) : '';
			if ( '' === $val ) {
				delete_post_meta( $post_id, $meta );
			} else {
				update_post_meta( $post_id, $meta, $val );
			}
		}
	}
}

BC_SEO::init();
