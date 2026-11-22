<?php
class MW_WP_Form_Upload_Field_Restriction_Controller_Test extends WP_UnitTestCase {

	public function tear_down() {
		parent::tear_down();
		_delete_all_data();
	}

	/**
	 * Create a mw-wp-form post saved before 5.2.
	 *
	 * @param string $content Post content.
	 * @return int Post ID.
	 */
	protected function _create_saved_form( $content ) {
		global $wpdb;

		$post_id = $this->factory->post->create( array( 'post_type' => MWF_Config::NAME ) );
		$wpdb->update( $wpdb->posts, array( 'post_content' => $content ), array( 'ID' => $post_id ) );
		clean_post_cache( $post_id );

		return $post_id;
	}

	/**
	 * @test
	 * @group upload_field_restriction
	 */
	public function new_form_can_not_have_upload_fields() {
		$post_id = $this->factory->post->create(
			array(
				'post_type'    => MWF_Config::NAME,
				'post_content' => '[mwform_text name="a"][mwform_file name="b"][mwform_image name="c"]',
			)
		);

		$this->assertSame( '[mwform_text name="a"]', get_post_field( 'post_content', $post_id ) );
	}

	/**
	 * @test
	 * @group upload_field_restriction
	 */
	public function saved_upload_fields_are_kept() {
		$post_id = $this->_create_saved_form( '[mwform_file name="a"]<p>[mwform_image name="b"]</p>' );

		wp_update_post(
			array(
				'ID'           => $post_id,
				'post_content' => '[mwform_text name="x"][mwform_file name="a"]<p>[mwform_image name="b"]</p>',
			)
		);

		$this->assertSame(
			'[mwform_text name="x"][mwform_file name="a"]<p>[mwform_image name="b"]</p>',
			get_post_field( 'post_content', $post_id )
		);
	}

	/**
	 * @test
	 * @group upload_field_restriction
	 */
	public function upload_fields_can_not_be_added_or_changed() {
		$post_id = $this->_create_saved_form( '[mwform_file name="a"]' );

		wp_update_post(
			array(
				'ID'           => $post_id,
				'post_content' => '[mwform_file name="a"][mwform_file name="a"][mwform_file name="b"][mwform_image name="a" show_error="false"]',
			)
		);

		$this->assertSame( '[mwform_file name="a"]', get_post_field( 'post_content', $post_id ) );
	}

	/**
	 * @test
	 * @group upload_field_restriction
	 */
	public function upload_fields_can_be_removed() {
		$post_id = $this->_create_saved_form( '[mwform_file name="a"][mwform_image name="b"]' );

		wp_update_post(
			array(
				'ID'           => $post_id,
				'post_content' => '[mwform_image name="b"]',
			)
		);
		$this->assertSame( '[mwform_image name="b"]', get_post_field( 'post_content', $post_id ) );

		wp_update_post(
			array(
				'ID'           => $post_id,
				'post_content' => '[mwform_image name="b"][mwform_file name="a"]',
			)
		);
		$this->assertSame( '[mwform_image name="b"]', get_post_field( 'post_content', $post_id ) );
	}

	/**
	 * @test
	 * @group upload_field_restriction
	 */
	public function other_content_is_not_changed() {
		$content = '[[mwform_file name="a"]][mwform_filepicker name="b"]<p>"quote" \\backslash</p>';
		$post_id = $this->factory->post->create(
			array(
				'post_type'    => MWF_Config::NAME,
				'post_content' => wp_slash( $content ),
			)
		);
		$this->assertSame( $content, get_post_field( 'post_content', $post_id ) );

		$post_id = $this->factory->post->create(
			array(
				'post_type'    => MWF_Config::NAME,
				'post_content' => wp_slash( $content . '[mwform_file name="c"]' ),
			)
		);
		$this->assertSame( $content, get_post_field( 'post_content', $post_id ) );

		$post_id = $this->factory->post->create(
			array(
				'post_type'    => 'post',
				'post_content' => '[mwform_file name="a"]',
			)
		);
		$this->assertSame( '[mwform_file name="a"]', get_post_field( 'post_content', $post_id ) );
	}

	/**
	 * @test
	 * @group upload_field_restriction
	 */
	public function removed_fields_are_noticed_once() {
		$user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		$this->factory->post->create(
			array(
				'post_type'    => MWF_Config::NAME,
				'post_content' => '[mwform_file name="b"]',
			)
		);

		$controller = new MW_WP_Form_Upload_Field_Restriction_Controller();

		ob_start();
		$controller->_notice();
		$output = (string) ob_get_clean();
		$this->assertStringContainsString( 'notice-error', $output );
		$this->assertStringContainsString( esc_html( '[mwform_file name="b"]' ), $output );

		ob_start();
		$controller->_notice();
		$this->assertSame( '', (string) ob_get_clean() );
	}

	/**
	 * @test
	 * @group upload_field_restriction
	 */
	public function upload_fields_are_not_in_tag_generator() {
		$file  = new MW_WP_Form_Field_File();
		$image = new MW_WP_Form_Field_Image();
		$text  = new MW_WP_Form_Field_Text();

		$file->add_tag_generator();
		$image->add_tag_generator();
		$text->add_tag_generator();

		$this->assertFalse( has_action( 'mwform_tag_generator_input_option', array( $file, '_mwform_tag_generator_option' ) ) );
		$this->assertFalse( has_action( 'mwform_tag_generator_input_option', array( $image, '_mwform_tag_generator_option' ) ) );
		$this->assertFalse( has_action( 'mwform_tag_generator_dialog', array( $file, '_mwform_tag_generator_dialog' ) ) );
		$this->assertNotFalse( has_action( 'mwform_tag_generator_input_option', array( $text, '_mwform_tag_generator_option' ) ) );
	}
}
