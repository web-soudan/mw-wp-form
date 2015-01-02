<?php
/**
 * Name       : MW WP Form Admin
 * Description: 管理画面クラス
 * Version    : 2.0.0
 * Author     : Takashi Kitajima
 * Author URI : http://2inc.org
 * Created    : February 21, 2013
 * Modified   : January 1, 2015
 * License    : GPLv2
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 */
class MW_WP_Form_Admin {

	/**
	 * save_post
	 * @param int $post_id
	 */
	public function save_post( $post_id ) {
		if ( !( isset( $_POST['post_type'] ) && $_POST['post_type'] === MWF_Config::NAME ) )
			return $post_id;
		if ( !isset( $_POST[MWF_Config::NAME . '_nonce'] ) )
			return $post_id;
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )
			return $post_id;
		if ( !wp_verify_nonce( $_POST[MWF_Config::NAME . '_nonce'], MWF_Config::NAME ) )
			return $post_id;
		if ( !current_user_can( MWF_Config::CAPABILITY ) )
			return $post_id;

		$data = $_POST[MWF_Config::NAME];
		if ( !empty( $data['validation'] ) && is_array( $data['validation'] ) ) {
			$validation = array();
			foreach ( $data['validation'] as $_validation ) {
				if ( empty( $_validation['target'] ) ) continue;
				foreach ( $_validation as $key => $value ) {
					// between min, max
					if ( $key == 'between' ) {
						if ( !MWF_Functions::is_numeric( $value['min'] ) ) {
							unset( $_validation[$key]['min'] );
						}
						if ( !MWF_Functions::is_numeric( $value['max'] ) ) {
							unset( $_validation[$key]['max'] );
						}
					}
					// minlength min
					elseif ( $key == 'minlength' && !MWF_Functions::is_numeric( $value['min'] ) ) {
						unset( $_validation[$key] );
					}
					// fileType types
					elseif ( $key == 'fileType' && isset( $value['types'] ) && !preg_match( '/^[0-9A-Za-z,]+$/', $value['types'] ) ) {
						unset( $_validation[$key] );
					}
					// fileSize bytes
					elseif ( $key == 'fileSize' && !MWF_Functions::is_numeric( $value['bytes'] ) ) {
						unset( $_validation[$key] );
					}

					// 要素が空のときは削除
					// 単一項目のとき
					if ( empty( $value ) ) {
						unset( $_validation[$key] );
					}
					// 配列のとき
					elseif ( is_array( $value ) && !array_diff( $value, array( '' ) ) ) {
						unset( $_validation[$key] );
					}
				}
				$validation[] = $_validation;
			}
			$data['validation'] = $validation;
		}
		$Setting = new MW_WP_Form_Setting( $post_id );
		$Setting->sets( $data );
		$Setting->save();
	}

	/**
	 * get_forms
	 * @return array フォーム（WP_Post）の配列
	 */
	public function get_forms() {
		$forms = get_posts( array(
			'post_type'      => MWF_Config::NAME,
			'posts_per_page' => -1,
		) );
		return $forms;
	}
}
