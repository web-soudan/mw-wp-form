<?php
/**
 * @package mw-wp-form
 * @author websoudan
 * @license GPL-2.0+
 */

/**
 * MW_WP_Form_Upload_Field_Restriction_Controller
 *
 * Since 5.2, the [mwform_file] and [mwform_image] shortcodes can no longer be
 * added to forms. Shortcodes that already exist in a saved form are kept as
 * they are, and any other one is removed when the form is saved.
 */
class MW_WP_Form_Upload_Field_Restriction_Controller {

	/**
	 * Restricted shortcode names.
	 *
	 * @var array
	 */
	const SHORTCODES = array( 'mwform_file', 'mwform_image' );

	/**
	 * Transient key prefix for the notice of removed shortcodes.
	 */
	const NOTICE_KEY = 'mwform_removed_upload_fields_';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_filter( 'wp_insert_post_data', array( $this, '_wp_insert_post_data' ), 10, 2 );
		add_action( 'admin_notices', array( $this, '_notice' ) );
	}

	/**
	 * Remove restricted shortcodes that do not exist in the saved form.
	 *
	 * @param array $data    Slashed, sanitized and processed post data.
	 * @param array $postarr Slashed, sanitized and processed post data before processing.
	 * @return array
	 */
	public function _wp_insert_post_data( $data, $postarr ) {
		if ( ! isset( $data['post_type'], $data['post_content'] ) || MWF_Config::NAME !== $data['post_type'] ) {
			return $data;
		}

		$saved_content = '';
		if ( ! empty( $postarr['ID'] ) && MWF_Config::NAME === get_post_type( $postarr['ID'] ) ) {
			$saved_content = (string) get_post_field( 'post_content', $postarr['ID'], 'raw' );
		}

		$removed = array();
		$content = self::filter_content( wp_unslash( $data['post_content'] ), $saved_content, $removed );

		if ( $removed ) {
			$data['post_content'] = wp_slash( $content );

			$user_id = get_current_user_id();
			if ( $user_id ) {
				set_transient( self::NOTICE_KEY . $user_id, $removed, MINUTE_IN_SECONDS * 5 );
			}
		}

		return $data;
	}

	/**
	 * Return the content without restricted shortcodes that do not exist in the saved content.
	 *
	 * @param string $content       New content.
	 * @param string $saved_content Saved content.
	 * @param array  $removed       Removed shortcodes.
	 * @return string
	 */
	public static function filter_content( $content, $saved_content, &$removed = array() ) {
		$regex = '/' . get_shortcode_regex( self::SHORTCODES ) . '/';

		$permitted = array();
		if ( preg_match_all( $regex, $saved_content, $matches, PREG_SET_ORDER ) ) {
			foreach ( $matches as $match ) {
				if ( ! self::_is_escaped( $match ) ) {
					$permitted[] = $match[0];
				}
			}
		}

		return preg_replace_callback(
			$regex,
			function ( $match ) use ( &$permitted, &$removed ) {
				if ( self::_is_escaped( $match ) ) {
					return $match[0];
				}

				$index = array_search( $match[0], $permitted, true );
				if ( false !== $index ) {
					unset( $permitted[ $index ] );
					return $match[0];
				}

				$removed[] = $match[0];
				return '';
			},
			$content
		);
	}

	/**
	 * Whether the shortcode is escaped like [[mwform_file]].
	 *
	 * @param array $match Matched shortcode.
	 * @return bool
	 */
	protected static function _is_escaped( $match ) {
		return '[' === $match[1] && ']' === $match[6];
	}

	/**
	 * Display the removed shortcodes.
	 */
	public function _notice() {
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return;
		}

		$removed = get_transient( self::NOTICE_KEY . $user_id );
		if ( ! $removed || ! is_array( $removed ) ) {
			return;
		}
		delete_transient( self::NOTICE_KEY . $user_id );
		?>
		<div class="notice notice-error">
			<p>
				<strong><?php esc_html_e( 'MW WP Form: Some form tags were removed', 'mw-wp-form' ); ?></strong><br>
				<?php esc_html_e( 'Since version 5.2, the [mwform_file] and [mwform_image] form tags can no longer be added or changed. The following form tags were removed:', 'mw-wp-form' ); ?>
			</p>
			<ul>
				<?php foreach ( $removed as $shortcode ) : ?>
					<li><code><?php echo esc_html( $shortcode ); ?></code></li>
				<?php endforeach; ?>
			</ul>
		</div>
		<?php
	}
}
