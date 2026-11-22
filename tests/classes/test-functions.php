<?php
class MWF_Functions_Test extends WP_UnitTestCase {

	public function tear_down() {
		parent::tear_down();
		_delete_all_data();
	}

	protected function _create_form() {
		return $this->factory->post->create(
			array(
				'post_type' => MWF_Config::NAME,
			)
		);
	}

	/**
	 * @test
	 * @group is_numeric
	 */
	public function is_numeric() {
		$numeric = 1;
		$this->assertFalse( MWF_Functions::is_numeric( $dummy ) );
		$this->assertTrue( MWF_Functions::is_numeric( $numeric ) );
	}

	/**
	 * @test
	 * @group convert_eol
	 */
	public function convert_eol() {
		$this->assertSame( "aaa\nbbb\nccc", MWF_Functions::convert_eol( "aaa\r\nbbb\rccc" ) );
		$this->assertSame( "aaa\nbbb", MWF_Functions::convert_eol( "aaa\nbbb" ) );
		$this->assertSame( '', MWF_Functions::convert_eol( '' ) );
		$this->assertSame( '', MWF_Functions::convert_eol( null ) );
	}

	/**
	 * @test
	 * @group array_clean
	 */
	public function array_clean() {
		$this->assertSame(
			array(),
			MWF_Functions::array_clean( array(
				'name-1' => '',
				'name-2' => '',
				'name-3' => null,
			) )
		);
	}

	/**
	 * @test
	 * @group is_empty
	 */
	public function is_empty() {
		$this->assertTrue( MWF_Functions::is_empty( array() ) );
		$this->assertTrue( MWF_Functions::is_empty( null ) );
		$this->assertTrue( MWF_Functions::is_empty( false ) );
		$this->assertTrue( MWF_Functions::is_empty( '' ) );
		$this->assertFalse( MWF_Functions::is_empty( 0 ) );
	}

	/**
	 * @test
	 * @group move_temp_file_to_upload_dir
	 */
	public function move_temp_file_to_upload_dir() {
		$old_filepath  = '/old/file/path/test.jpg';
		$wp_upload_dir = wp_upload_dir();

		$new_filepath  = MWF_Functions::move_temp_file_to_upload_dir( $old_filepath );
		$this->assertEquals(
			$wp_upload_dir['path'] . '/test.jpg',
			$new_filepath
		);

		$new_filepath  = MWF_Functions::move_temp_file_to_upload_dir( $old_filepath, '/dir' );
		$this->assertEquals(
			$wp_upload_dir['basedir'] . '/dir/test.jpg',
			$new_filepath
		);

		$new_filepath  = MWF_Functions::move_temp_file_to_upload_dir( $old_filepath, '', 'file.jpg' );
		$this->assertEquals(
			$wp_upload_dir['path'] . '/file.jpg',
			$new_filepath
		);

		$new_filepath  = MWF_Functions::move_temp_file_to_upload_dir( $old_filepath, '/dir', 'file.jpg' );
		$this->assertEquals(
			$wp_upload_dir['basedir'] . '/dir/file.jpg',
			$new_filepath
		);
	}

	public function save_attachments_in_media() {}

	/**
	 * Create an attachment with a file.
	 *
	 * @param int $parent_id Parent post ID.
	 * @return array Attachment ID and file path.
	 */
	protected function _create_attachment( $parent_id ) {
		$upload = wp_upload_bits( 'mwform-' . wp_generate_password( 8, false ) . '.txt', null, 'test' );
		$this->assertEmpty( $upload['error'] );

		$attachment_id = wp_insert_attachment(
			array(
				'post_mime_type' => 'text/plain',
				'post_title'     => 'file',
				'post_status'    => 'inherit',
			),
			$upload['file'],
			$parent_id
		);

		return array( $attachment_id, $upload['file'] );
	}

	/**
	 * @test
	 * @group delete_contact_data_attachments
	 */
	public function attachments_are_deleted_with_contact_data() {
		$form_id   = $this->_create_form();
		$post_type = MWF_Functions::get_contact_data_post_type_from_form_id( $form_id );
		$post_id   = $this->factory->post->create( array( 'post_type' => $post_type ) );
		$other_id  = $this->factory->post->create( array( 'post_type' => $post_type ) );

		list( $attachment_id_1, $file_1 ) = $this->_create_attachment( $post_id );
		list( $attachment_id_2, $file_2 ) = $this->_create_attachment( $post_id );
		list( $other_attachment_id, $other_file ) = $this->_create_attachment( $other_id );
		list( $media_id, $media_file ) = $this->_create_attachment( 0 );

		wp_trash_post( $post_id );
		$this->assertNotNull( get_post( $attachment_id_1 ) );
		$this->assertFileExists( $file_1 );

		wp_delete_post( $post_id, true );

		$this->assertNull( get_post( $attachment_id_1 ) );
		$this->assertNull( get_post( $attachment_id_2 ) );
		$this->assertFileDoesNotExist( $file_1 );
		$this->assertFileDoesNotExist( $file_2 );

		$this->assertNotNull( get_post( $other_attachment_id ) );
		$this->assertFileExists( $other_file );
		$this->assertNotNull( get_post( $media_id ) );
		$this->assertFileExists( $media_file );

		wp_delete_attachment( $other_attachment_id, true );
		wp_delete_attachment( $media_id, true );
	}

	/**
	 * @test
	 * @group delete_contact_data_attachments
	 */
	public function attachments_are_not_deleted_with_other_posts() {
		$post_id = $this->factory->post->create();
		list( $attachment_id, $file ) = $this->_create_attachment( $post_id );

		wp_delete_post( $post_id, true );

		$this->assertNotNull( get_post( $attachment_id ) );
		$this->assertFileExists( $file );

		wp_delete_attachment( $attachment_id, true );
	}

	public function check_file_type() {}

	/**
	 * @test
	 * @group get_tracking_number_title
	 */
	public function get_tracking_number_title() {
		$form_id = $this->_create_form();
		$inquiry_data_post_type = MWF_Functions::get_contact_data_post_type_from_form_id( $form_id );
		$this->assertEquals( 'Tracking Number', MWF_Functions::get_tracking_number_title( $inquiry_data_post_type ) );
	}

	/**
	 * @test
	 * @group contact_data_post_type_to_form_key
	 */
	public function contact_data_post_type_to_form_key() {
		$form_id  = $this->_create_form();
		$form_key = MWF_Functions::get_form_key_from_form_id( $form_id );
		$this->assertEquals(
			$form_key,
			MWF_Functions::contact_data_post_type_to_form_key( 'mwf_' . $form_id )
		);

		$this->assertNull( MWF_Functions::contact_data_post_type_to_form_key( 'dummy' ) );
	}

	/**
	 * @test
	 * @group get_form_key_from_form_id
	 */
	public function get_form_key_from_form_id() {
		$form_id = $this->_create_form();
		$this->assertEquals( MWF_Config::NAME . '-' . $form_id, MWF_Functions::get_form_key_from_form_id( $form_id ) );
		$this->assertNull( MWF_Functions::get_form_key_from_form_id( 'dummy' ) );
	}

	/**
	 * @test
	 * @group get_form_id_from_form_key
	 */
	public function get_form_id_from_form_key() {
		$form_id  = $this->_create_form();
		$form_key = MWF_Functions::get_form_key_from_form_id( $form_id );
		$this->assertEquals( $form_id, MWF_Functions::get_form_id_from_form_key( $form_key ) );
		$this->assertNull( MWF_Functions::get_form_id_from_form_key( 'dummy' ) );
	}

	/**
	 * @test
	 * @group get_contact_data_post_type_from_form_id
	 */
	public function get_contact_data_post_type_from_form_id() {
		$form_id  = $this->_create_form();
		$form_key = MWF_Functions::get_form_key_from_form_id( $form_id );
		$this->assertEquals( 'mwf_' . $form_id, MWF_Functions::get_contact_data_post_type_from_form_id( $form_id ) );
		$this->assertNull( MWF_Functions::get_contact_data_post_type_from_form_id( 'dummy' ) );
	}

	/**
	 * @test
	 * @group is_contact_data_post_type
	 */
	public function is_contact_data_post_type() {
		$this->assertFalse( MWF_Functions::is_contact_data_post_type( 'dummy' ) );
		$this->assertTrue( MWF_Functions::is_contact_data_post_type( 'mwf_1' ) );
		$this->assertFalse( MWF_Functions::is_contact_data_post_type( 'mwf_dummy' ) );
	}

	public function get_multimedia_data() {}

	public function mwform_enqueue_scripts() {}

	/**
	 * @test
	 * @group generate_input_attribute
	 */
	public function generate_input_attribute() {
		$this->assertEquals( 'name-1="value-1"', MWF_Functions::generate_input_attribute( 'name-1', 'value-1' ) );
		$this->assertEquals( 'name-1=""', MWF_Functions::generate_input_attribute( 'name-1', '' ) );
		$this->assertNull( MWF_Functions::generate_input_attribute( 'name-1', null ) );
	}
}
