<?php
/**
 * Plugin Name: Bristol Channel Social
 * Description: Queues a post for each new story to Buffer (Facebook, LinkedIn, X) with an approval box on the story edit screen.
 * Version: 1.0.0
 * Author: The Bristol Channel
 * License: GPL-2.0-or-later
 *
 * @package bristol-channel-social
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class BC_Social {

	const API   = 'https://api.buffer.com';
	const DELAY = 90; // Seconds between publishing and sending, so edits to the drafts land first.

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_post_bcsoc_save', array( __CLASS__, 'save_settings' ) );
		add_action( 'admin_post_bcsoc_refresh', array( __CLASS__, 'refresh_channels' ) );
		add_action( 'add_meta_boxes', array( __CLASS__, 'meta_box' ) );
		add_action( 'save_post_post', array( __CLASS__, 'save_post' ), 20, 2 );
		add_action( 'bcsoc_send', array( __CLASS__, 'send' ) );
	}

	/* ------------------------------------------------------------ helpers */

	private static function default_tpl( $service ) {
		if ( 'twitter' === $service ) {
			return "{headline}\n\n{url}";
		}
		if ( 'facebook' === $service || 'linkedin' === $service ) {
			return "{headline}\n\n{standfirst}";
		}
		return '{headline} {url}';
	}

	private static function is_wtf( $post_id ) {
		return has_category( 'wtf', $post_id );
	}

	private static function is_on( $post_id ) {
		$v = get_post_meta( $post_id, '_bcsoc_on', true );
		if ( '1' === $v ) {
			return true;
		}
		if ( '0' === $v ) {
			return false;
		}
		return '1' === get_option( 'bcsoc_default_on', '0' );
	}

	private static function channels() {
		$c = get_option( 'bcsoc_channels', array() );
		return is_array( $c ) ? $c : array();
	}

	private static function log( $post_id, $line ) {
		$log   = get_post_meta( $post_id, '_bcsoc_log', true );
		$log   = is_array( $log ) ? $log : array();
		$log[] = gmdate( 'Y-m-d H:i' ) . ' UTC: ' . $line;
		update_post_meta( $post_id, '_bcsoc_log', array_slice( $log, -10 ) );
	}

	/** Build the text for one service from the template. */
	private static function render( $service, $post ) {
		$tpls = get_option( 'bcsoc_templates', array() );
		$tpl  = ( is_array( $tpls ) && ! empty( $tpls[ $service ] ) ) ? $tpls[ $service ] : self::default_tpl( $service );
		$url  = get_permalink( $post );
		$head = html_entity_decode( wp_strip_all_tags( get_the_title( $post ) ), ENT_QUOTES, 'UTF-8' );
		$stand = html_entity_decode( wp_strip_all_tags( (string) $post->post_excerpt ), ENT_QUOTES, 'UTF-8' );

		$build = function ( $h, $s ) use ( $tpl, $url ) {
			$out = strtr( $tpl, array( '{headline}' => $h, '{standfirst}' => $s, '{url}' => $url ) );
			$out = preg_replace( "/\n{3,}/", "\n\n", $out );
			return trim( $out );
		};
		$out = $build( $head, $stand );

		if ( 'twitter' === $service ) {
			// X counts any link as 23 characters.
			$count = function ( $s ) use ( $url ) {
				return mb_strlen( str_replace( $url, '', $s ) ) + ( false !== strpos( $s, $url ) ? 23 : 0 );
			};
			if ( $count( $out ) > 280 ) {
				$out = $build( $head, '' );
			}
			$over = $count( $out ) - 280;
			if ( $over > 0 && mb_strlen( $head ) > $over + 2 ) {
				$out = $build( rtrim( mb_substr( $head, 0, mb_strlen( $head ) - $over - 1 ) ) . '…', '' );
			}
		}
		return $out;
	}

	/** Call the Buffer GraphQL API. */
	private static function gql( $query, $vars = array() ) {
		$key = get_option( 'bcsoc_api_key' );
		if ( ! $key ) {
			return new WP_Error( 'bcsoc', 'No Buffer API key set.' );
		}
		$r = wp_remote_post(
			self::API,
			array(
				'timeout' => 20,
				'headers' => array(
					'Content-Type'  => 'application/json',
					'Authorization' => 'Bearer ' . $key,
				),
				'body'    => wp_json_encode( array( 'query' => $query, 'variables' => (object) $vars ) ),
			)
		);
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		$code = wp_remote_retrieve_response_code( $r );
		$j    = json_decode( wp_remote_retrieve_body( $r ), true );
		if ( ! is_array( $j ) ) {
			return new WP_Error( 'bcsoc', 'Buffer returned HTTP ' . $code . ' with an unreadable reply.' );
		}
		if ( ! empty( $j['errors'] ) ) {
			$m = isset( $j['errors'][0]['message'] ) ? $j['errors'][0]['message'] : 'Buffer returned an error.';
			return new WP_Error( 'bcsoc', $m );
		}
		if ( empty( $j['data'] ) ) {
			return new WP_Error( 'bcsoc', 'Buffer returned HTTP ' . $code . ' with no data.' );
		}
		return $j['data'];
	}

	/* ---------------------------------------------------------- sending */

	public static function send( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post || 'post' !== $post->post_type || 'publish' !== $post->post_status ) {
			return;
		}
		if ( self::is_wtf( $post_id ) || ! self::is_on( $post_id ) ) {
			return;
		}
		// Stories promoted by default only go out while they are new. An explicit tick always counts.
		if ( '1' !== get_post_meta( $post_id, '_bcsoc_on', true ) && strtotime( $post->post_date_gmt . ' UTC' ) < time() - 3 * DAY_IN_SECONDS ) {
			return;
		}

		$channels = self::channels();
		$sel      = get_post_meta( $post_id, '_bcsoc_channels', true );
		if ( ! is_array( $sel ) ) {
			$sel = array();
			foreach ( $channels as $id => $c ) {
				if ( ! empty( $c['on'] ) ) {
					$sel[] = $id;
				}
			}
		}
		$sent   = get_post_meta( $post_id, '_bcsoc_sent', true );
		$sent   = is_array( $sent ) ? $sent : array();
		$drafts = get_post_meta( $post_id, '_bcsoc_drafts', true );
		$drafts = is_array( $drafts ) ? $drafts : array();
		$url    = get_permalink( $post );

		$mutation = 'mutation($input: CreatePostInput!){ createPost(input:$input){ ... on PostActionSuccess { post { id dueAt } } ... on MutationError { message } } }';

		foreach ( $sel as $id ) {
			if ( ! isset( $channels[ $id ] ) || isset( $sent[ $id ] ) ) {
				continue;
			}
			$service = $channels[ $id ]['service'];
			$text    = ! empty( $drafts[ $id ] ) ? $drafts[ $id ] : self::render( $service, $post );
			$input   = array(
				'channelId'      => $id,
				'text'           => $text,
				'schedulingType' => 'automatic',
				'mode'           => 'addToQueue',
			);
			if ( 'facebook' === $service ) {
				$input['metadata'] = array( 'facebook' => array( 'type' => 'post', 'linkAttachment' => array( 'url' => $url ) ) );
			} elseif ( 'linkedin' === $service ) {
				$input['metadata'] = array( 'linkedin' => array( 'linkAttachment' => array( 'url' => $url ) ) );
			}
			$d = self::gql( $mutation, array( 'input' => $input ) );
			if ( is_wp_error( $d ) ) {
				self::log( $post_id, $channels[ $id ]['name'] . ': ' . $d->get_error_message() );
				continue;
			}
			$res = isset( $d['createPost'] ) ? $d['createPost'] : array();
			if ( ! empty( $res['post']['id'] ) ) {
				$sent[ $id ] = $res['post']['id'];
				update_post_meta( $post_id, '_bcsoc_sent', $sent );
				self::log( $post_id, $channels[ $id ]['name'] . ': queued.' );
			} else {
				self::log( $post_id, $channels[ $id ]['name'] . ': ' . ( isset( $res['message'] ) ? $res['message'] : 'not accepted by Buffer.' ) );
			}
		}
	}

	/* ------------------------------------------------------- post screen */

	public static function meta_box() {
		add_meta_box( 'bcsoc', 'Social posts (Buffer)', array( __CLASS__, 'render_box' ), 'post', 'normal', 'default' );
	}

	public static function render_box( $post ) {
		$channels = self::channels();
		if ( ! $channels ) {
			echo '<p>No social channels yet. Connect them under Settings &gt; Social posting.</p>';
			return;
		}
		wp_nonce_field( 'bcsoc_box', 'bcsoc_nonce' );
		$on     = self::is_on( $post->ID );
		$sel    = get_post_meta( $post->ID, '_bcsoc_channels', true );
		$drafts = get_post_meta( $post->ID, '_bcsoc_drafts', true );
		$sent   = get_post_meta( $post->ID, '_bcsoc_sent', true );
		$log    = get_post_meta( $post->ID, '_bcsoc_log', true );
		$drafts = is_array( $drafts ) ? $drafts : array();
		$sent   = is_array( $sent ) ? $sent : array();

		echo '<p><label><input type="checkbox" name="bcsoc_on" value="1" ' . checked( $on, true, false ) . ' /> <strong>Queue this story to Buffer after publishing</strong></label><br />';
		echo '<span class="description">It goes out about two minutes after you publish or update. Each channel is posted once. WTF posts are never sent.</span></p>';

		foreach ( $channels as $id => $c ) {
			$checked = is_array( $sel ) ? in_array( $id, $sel, true ) : ! empty( $c['on'] );
			$preview = $post->post_title ? self::render( $c['service'], $post ) : '';
			echo '<div style="margin:12px 0;padding-top:10px;border-top:1px solid #dcdcde">';
			echo '<label><input type="checkbox" name="bcsoc_ch[]" value="' . esc_attr( $id ) . '" ' . checked( $checked, true, false ) . ' /> <strong>' . esc_html( $c['name'] ) . '</strong> <em>(' . esc_html( $c['service'] ) . ')</em></label>';
			if ( isset( $sent[ $id ] ) ) {
				echo ' <span style="color:#00a32a">&#10003; queued</span>';
			}
			echo '<textarea name="bcsoc_txt[' . esc_attr( $id ) . ']" rows="4" style="width:100%;margin-top:6px" placeholder="' . esc_attr( $preview ) . '">' . esc_textarea( isset( $drafts[ $id ] ) ? $drafts[ $id ] : '' ) . '</textarea>';
			echo '</div>';
		}
		echo '<p class="description">Leave a box empty to use the template shown in grey. Type your own text to override it. Facebook and LinkedIn add the story link and photo card automatically; X includes the link in the text.</p>';
		if ( is_array( $log ) && $log ) {
			echo '<p><strong>Activity</strong><br />' . implode( '<br />', array_map( 'esc_html', $log ) ) . '</p>';
		}
	}

	public static function save_post( $post_id, $post ) {
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) ) {
			return;
		}
		if ( isset( $_POST['bcsoc_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['bcsoc_nonce'] ) ), 'bcsoc_box' ) && current_user_can( 'edit_post', $post_id ) ) {
			update_post_meta( $post_id, '_bcsoc_on', empty( $_POST['bcsoc_on'] ) ? '0' : '1' );
			$sel = isset( $_POST['bcsoc_ch'] ) ? array_map( 'sanitize_text_field', (array) wp_unslash( $_POST['bcsoc_ch'] ) ) : array();
			update_post_meta( $post_id, '_bcsoc_channels', array_values( $sel ) );
			$drafts = array();
			if ( isset( $_POST['bcsoc_txt'] ) ) {
				foreach ( (array) wp_unslash( $_POST['bcsoc_txt'] ) as $id => $t ) {
					$drafts[ sanitize_text_field( $id ) ] = sanitize_textarea_field( $t );
				}
			}
			update_post_meta( $post_id, '_bcsoc_drafts', $drafts );
		}
		if ( 'publish' === $post->post_status && ! wp_next_scheduled( 'bcsoc_send', array( $post_id ) ) ) {
			wp_schedule_single_event( time() + self::DELAY, 'bcsoc_send', array( $post_id ) );
		}
	}

	/* ----------------------------------------------------------- settings */

	public static function menu() {
		add_options_page( 'Social posting', 'Social posting', 'manage_options', 'bcsoc', array( __CLASS__, 'render_settings' ) );
	}

	private static function back( $msg, $ok = true ) {
		wp_safe_redirect( add_query_arg( array( 'page' => 'bcsoc', 'bcsoc_msg' => rawurlencode( $msg ), 'bcsoc_ok' => $ok ? 1 : 0 ), admin_url( 'options-general.php' ) ) );
		exit;
	}

	public static function save_settings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Not allowed.' );
		}
		check_admin_referer( 'bcsoc_settings' );
		if ( ! empty( $_POST['bcsoc_api_key'] ) ) {
			update_option( 'bcsoc_api_key', trim( sanitize_text_field( wp_unslash( $_POST['bcsoc_api_key'] ) ) ), false );
		}
		update_option( 'bcsoc_default_on', empty( $_POST['bcsoc_default_on'] ) ? '0' : '1' );
		$tpls = array();
		if ( isset( $_POST['bcsoc_tpl'] ) ) {
			foreach ( (array) wp_unslash( $_POST['bcsoc_tpl'] ) as $svc => $t ) {
				$tpls[ sanitize_key( $svc ) ] = sanitize_textarea_field( $t );
			}
		}
		update_option( 'bcsoc_templates', $tpls );
		$on       = isset( $_POST['bcsoc_on_ch'] ) ? array_map( 'sanitize_text_field', (array) wp_unslash( $_POST['bcsoc_on_ch'] ) ) : array();
		$channels = self::channels();
		foreach ( $channels as $id => $c ) {
			$channels[ $id ]['on'] = in_array( $id, $on, true );
		}
		update_option( 'bcsoc_channels', $channels );
		self::back( 'Settings saved.' );
	}

	public static function refresh_channels() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Not allowed.' );
		}
		check_admin_referer( 'bcsoc_refresh' );
		$d = self::gql( 'query { account { organizations { id name } } }' );
		if ( is_wp_error( $d ) ) {
			self::back( $d->get_error_message(), false );
		}
		$org = isset( $d['account']['organizations'][0]['id'] ) ? $d['account']['organizations'][0]['id'] : '';
		if ( ! $org ) {
			self::back( 'No Buffer organization found for this key.', false );
		}
		$d = self::gql( 'query($o: OrganizationId!){ channels(input:{organizationId:$o}){ id service name displayName isDisconnected } }', array( 'o' => $org ) );
		if ( is_wp_error( $d ) ) {
			self::back( $d->get_error_message(), false );
		}
		$old = self::channels();
		$new = array();
		foreach ( (array) $d['channels'] as $c ) {
			if ( ! empty( $c['isDisconnected'] ) ) {
				continue;
			}
			$new[ $c['id'] ] = array(
				'service' => $c['service'],
				'name'    => $c['displayName'] ? $c['displayName'] : $c['name'],
				'on'      => isset( $old[ $c['id'] ] ) ? $old[ $c['id'] ]['on'] : true,
			);
		}
		update_option( 'bcsoc_channels', $new );
		self::back( count( $new ) . ' channel(s) found.' );
	}

	public static function render_settings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$channels = self::channels();
		$tpls     = get_option( 'bcsoc_templates', array() );
		$tpls     = is_array( $tpls ) ? $tpls : array();
		echo '<div class="wrap"><h1>Social posting (Buffer)</h1>';
		if ( isset( $_GET['bcsoc_msg'] ) ) {
			$ok = ! empty( $_GET['bcsoc_ok'] );
			echo '<div class="notice notice-' . ( $ok ? 'success' : 'error' ) . '"><p>' . esc_html( sanitize_text_field( rawurldecode( wp_unslash( $_GET['bcsoc_msg'] ) ) ) ) . '</p></div>';
		}
		echo '<p>When a story is published, a post for each channel below is queued in Buffer. Drafts can be edited on each story before or just after publishing.</p>';

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="bcsoc_save" />';
		wp_nonce_field( 'bcsoc_settings' );
		echo '<table class="form-table" role="presentation">';
		echo '<tr><th>Buffer API key</th><td><input type="password" name="bcsoc_api_key" class="regular-text" autocomplete="off" placeholder="' . ( get_option( 'bcsoc_api_key' ) ? 'Saved. Leave blank to keep it.' : 'Paste your key' ) . '" /><p class="description">Create one at publish.buffer.com &gt; Settings &gt; API. Never share it.</p></td></tr>';
		echo '<tr><th>Promote new stories</th><td><label><input type="checkbox" name="bcsoc_default_on" value="1" ' . checked( get_option( 'bcsoc_default_on', '0' ), '1', false ) . ' /> Queue every new story to Buffer unless it is switched off on that story</label></td></tr>';
		foreach ( array( 'facebook' => 'Facebook', 'linkedin' => 'LinkedIn', 'twitter' => 'X' ) as $svc => $label ) {
			$v = isset( $tpls[ $svc ] ) && '' !== $tpls[ $svc ] ? $tpls[ $svc ] : self::default_tpl( $svc );
			echo '<tr><th>' . esc_html( $label ) . ' template</th><td><textarea name="bcsoc_tpl[' . esc_attr( $svc ) . ']" rows="3" class="large-text">' . esc_textarea( $v ) . '</textarea></td></tr>';
		}
		echo '<tr><th></th><td><span class="description">Placeholders: {headline}, {standfirst} (the excerpt), {url}. X posts are trimmed to fit 280 characters.</span></td></tr>';
		echo '<tr><th>Channels</th><td>';
		if ( $channels ) {
			foreach ( $channels as $id => $c ) {
				echo '<label style="display:block;margin-bottom:4px"><input type="checkbox" name="bcsoc_on_ch[]" value="' . esc_attr( $id ) . '" ' . checked( ! empty( $c['on'] ), true, false ) . ' /> ' . esc_html( $c['name'] ) . ' <em>(' . esc_html( $c['service'] ) . ')</em></label>';
			}
			echo '<p class="description">Ticked channels are used by default for new stories.</p>';
		} else {
			echo '<p>None yet. Save your API key, then click Refresh channels below.</p>';
		}
		echo '</td></tr></table>';
		submit_button( 'Save settings' );
		echo '</form>';

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="bcsoc_refresh" />';
		wp_nonce_field( 'bcsoc_refresh' );
		submit_button( 'Refresh channels from Buffer', 'secondary' );
		echo '</form></div>';
	}
}

BC_Social::init();
