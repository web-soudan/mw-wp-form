<?php
/**
 * お問い合わせデータ一覧画面（一覧テーブル）の出力エスケープに関するテスト。
 */
class MW_WP_Form_Contact_Data_List_Controller_Test extends WP_UnitTestCase {

	public function tear_down() {
		unset( $_GET['post_type'] );
		unset( $GLOBALS['posts'] );
		parent::tear_down();
		_delete_all_data();
	}

	/**
	 * usedb 有効のフォームと、その保存エントリを1件作成する。
	 *
	 * @param array $entry_meta エントリに付与するポストメタ（列見出しの検証用）。
	 * @return array [ $post_type, $entry_id ]
	 */
	protected function _create_form_and_entry( array $entry_meta = array() ) {
		$form_id = $this->factory->post->create(
			array(
				'post_type' => MWF_Config::NAME,
			)
		);
		update_post_meta( $form_id, MWF_Config::NAME, array( 'usedb' => 1 ) );

		$post_type = MWF_Functions::get_contact_data_post_type_from_form_id( $form_id );
		$entry_id  = $this->factory->post->create(
			array(
				'post_type' => $post_type,
			)
		);

		foreach ( $entry_meta as $key => $value ) {
			update_post_meta( $entry_id, $key, $value );
		}

		return array( $post_type, $entry_id );
	}

	/**
	 * コントローラをインスタンス化する（コンストラクタの exit を回避するため $_GET を設定）。
	 *
	 * @param string $post_type 対象のお問い合わせデータ用ポストタイプ。
	 * @return MW_WP_Form_Contact_Data_List_Controller
	 */
	protected function _controller( $post_type ) {
		$_GET['post_type'] = $post_type;
		return new MW_WP_Form_Contact_Data_List_Controller();
	}

	/**
	 * 列（セル）の出力を取得する。
	 */
	protected function _render_column( $controller, $column, $post_id ) {
		ob_start();
		$controller->_add_form_columns( $column, $post_id );
		return trim( ob_get_clean() );
	}

	/**
	 * 正常系: 通常の管理者宛先はそのまま表示され、列見出しも欠落しないこと。
	 *
	 * @test
	 * @group contact_data_list
	 */
	public function columns__normal() {
		list( $post_type, $entry_id ) = $this->_create_form_and_entry(
			array( 'your-name' => 'Taro' )
		);
		update_post_meta(
			$entry_id,
			MWF_Config::INQUIRY_DATA_NAME,
			array( 'admin_mail_to' => 'admin@example.com' )
		);

		$controller = $this->_controller( $post_type );

		$this->assertEquals( 'admin@example.com', $this->_render_column( $controller, 'admin_mail_to', $entry_id ) );

		$GLOBALS['posts'] = array( get_post( $entry_id ) );
		$columns          = $controller->_add_form_columns_name( array() );
		$this->assertArrayHasKey( 'your-name', $columns );
		$this->assertEquals( 'your-name', $columns['your-name'] );
	}

	/**
	 * 異常系: 管理者宛先に混入したスクリプトがそのまま出力されず、エスケープされること。
	 *
	 * @test
	 * @group contact_data_list
	 */
	public function admin_mail_to__is_escaped() {
		list( $post_type, $entry_id ) = $this->_create_form_and_entry();
		update_post_meta(
			$entry_id,
			MWF_Config::INQUIRY_DATA_NAME,
			array( 'admin_mail_to' => 'admin@example.com,<script>alert(/XSS/)</script>' )
		);

		$controller = $this->_controller( $post_type );
		$html       = $this->_render_column( $controller, 'admin_mail_to', $entry_id );

		$this->assertStringNotContainsString( '<script>', $html );
		$this->assertStringContainsString( '&lt;script&gt;', $html );
	}

	/**
	 * 異常系: エントリのメタキー（メール本文のタグ由来）に混入したHTMLが、
	 * 列見出しとしてそのまま出力されず、エスケープされること。
	 *
	 * @test
	 * @group contact_data_list
	 */
	public function column_heading__is_escaped() {
		$evil_key = '<img src=x onerror=alert(/XSS/)>';
		list( $post_type, $entry_id ) = $this->_create_form_and_entry(
			array( $evil_key => '' )
		);

		$controller       = $this->_controller( $post_type );
		$GLOBALS['posts'] = array( get_post( $entry_id ) );
		$columns          = $controller->_add_form_columns_name( array() );

		$this->assertArrayHasKey( $evil_key, $columns );
		$this->assertStringNotContainsString( '<img', $columns[ $evil_key ] );
		$this->assertEquals( esc_html( $evil_key ), $columns[ $evil_key ] );
	}
}
