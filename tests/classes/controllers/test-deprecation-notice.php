<?php
class MW_WP_Form_Deprecation_Notice_Controller_Test extends WP_UnitTestCase {

	public function tear_down() {
		delete_transient( MW_WP_Form_Deprecation_Notice_Controller::CACHE_KEY );
		parent::tear_down();
		_delete_all_data();
	}

	/**
	 * Create a mw-wp-form post.
	 *
	 * @param string $content Post content.
	 * @param string $status  Post status.
	 * @return int Post ID.
	 */
	protected function _create_form( $content = '', $status = 'publish' ) {
		return $this->factory->post->create(
			array(
				'post_type'    => MWF_Config::NAME,
				'post_status'  => $status,
				'post_content' => $content,
			)
		);
	}

	/**
	 * Set an administrator as the current user so that capability checks pass.
	 */
	protected function _login_as_admin() {
		$user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );
	}

	/**
	 * Capture the output of _notice().
	 *
	 * @return string
	 */
	protected function _capture_notice() {
		$controller = new MW_WP_Form_Deprecation_Notice_Controller();
		ob_start();
		$controller->_notice();
		return (string) ob_get_clean();
	}

	/**
	 * @test
	 * @group deprecation_notice
	 */
	public function notice_is_empty_when_no_forms_exist() {
		$this->_login_as_admin();

		$this->assertSame( '', $this->_capture_notice() );
	}

	/**
	 * @test
	 * @group deprecation_notice
	 */
	public function notice_is_empty_when_forms_do_not_use_target_shortcodes() {
		$this->_login_as_admin();
		$this->_create_form( '[mwform_text name="a"][mwform_email name="b"]' );

		$this->assertSame( '', $this->_capture_notice() );
	}

	/**
	 * @test
	 * @group deprecation_notice
	 */
	public function notice_is_rendered_when_form_uses_mwform_file() {
		$this->_login_as_admin();
		$form_id = $this->_create_form( '[mwform_file name="attachment"]' );
		get_post( $form_id )->post_title = 'File form';
		wp_update_post(
			array(
				'ID'         => $form_id,
				'post_title' => 'File form',
			)
		);

		$output = $this->_capture_notice();

		$this->assertStringContainsString( 'notice-warning', $output );
		$this->assertStringContainsString( 'File form', $output );
	}

	/**
	 * @test
	 * @group deprecation_notice
	 */
	public function notice_is_rendered_when_form_uses_mwform_image() {
		$this->_login_as_admin();
		$this->_create_form( '[mwform_image name="pic"]' );

		$this->assertStringContainsString( 'notice-warning', $this->_capture_notice() );
	}

	/**
	 * @test
	 * @group deprecation_notice
	 */
	public function notice_is_empty_for_unauthorized_user() {
		// No login — unauthenticated visitor.
		$this->_create_form( '[mwform_file name="a"]' );

		$this->assertSame( '', $this->_capture_notice() );
	}

	/**
	 * @test
	 * @group deprecation_notice
	 */
	public function notice_excludes_trashed_forms() {
		$this->_login_as_admin();
		$this->_create_form( '[mwform_file name="a"]', 'trash' );

		$this->assertSame( '', $this->_capture_notice() );
	}

	/**
	 * @test
	 * @group deprecation_notice
	 */
	public function notice_excludes_auto_draft_forms() {
		$this->_login_as_admin();
		$this->_create_form( '[mwform_file name="a"]', 'auto-draft' );

		$this->assertSame( '', $this->_capture_notice() );
	}

	/**
	 * @test
	 * @group deprecation_notice
	 */
	public function notice_excludes_non_mw_wp_form_posts() {
		$this->_login_as_admin();
		$this->factory->post->create(
			array(
				'post_type'    => 'post',
				'post_content' => '[mwform_file name="a"]',
			)
		);

		$this->assertSame( '', $this->_capture_notice() );
	}

	/**
	 * @test
	 * @group deprecation_notice
	 */
	public function notice_does_not_match_partial_shortcode_names() {
		$this->_login_as_admin();
		// Contains `mwform_filepicker` which must not be mistaken for
		// `mwform_file` through LIKE wildcard collapsing.
		$this->_create_form( '[mwform_filepicker name="a"]' );

		$this->assertSame( '', $this->_capture_notice() );
	}

	/**
	 * @test
	 * @group deprecation_notice
	 */
	public function cache_is_populated_after_query() {
		$this->_login_as_admin();
		$this->_create_form( '[mwform_file name="a"]' );

		$this->_capture_notice();

		$cached = get_transient( MW_WP_Form_Deprecation_Notice_Controller::CACHE_KEY );
		$this->assertIsArray( $cached );
		$this->assertCount( 1, $cached );
	}

	/**
	 * @test
	 * @group deprecation_notice
	 */
	public function cache_is_invalidated_for_mw_wp_form_post() {
		$this->_login_as_admin();
		$form_id = $this->_create_form( '[mwform_file name="a"]' );

		$controller = new MW_WP_Form_Deprecation_Notice_Controller();
		set_transient(
			MW_WP_Form_Deprecation_Notice_Controller::CACHE_KEY,
			array( (object) array( 'ID' => $form_id, 'post_title' => 'stale' ) ),
			HOUR_IN_SECONDS
		);

		$controller->_invalidate_cache( $form_id );

		$this->assertFalse( get_transient( MW_WP_Form_Deprecation_Notice_Controller::CACHE_KEY ) );
	}

	/**
	 * @test
	 * @group deprecation_notice
	 */
	public function cache_is_not_invalidated_for_non_mw_wp_form_post() {
		$this->_login_as_admin();
		$other_post_id = $this->factory->post->create(
			array(
				'post_type' => 'post',
			)
		);

		$controller = new MW_WP_Form_Deprecation_Notice_Controller();
		set_transient(
			MW_WP_Form_Deprecation_Notice_Controller::CACHE_KEY,
			array(),
			HOUR_IN_SECONDS
		);

		$controller->_invalidate_cache( $other_post_id );

		$this->assertIsArray( get_transient( MW_WP_Form_Deprecation_Notice_Controller::CACHE_KEY ) );
	}
}
