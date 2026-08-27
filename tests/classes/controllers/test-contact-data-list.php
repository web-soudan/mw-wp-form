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
	 * 一覧に組み込まれる列のうち、プラグイン固有の組み込み列を除いた
	 * 動的列（メタキー由来）の識別子を返すヘルパ。
	 */
	protected function _dynamic_column_ids( array $columns ) {
		$builtin = array( 'cb', 'title', 'date', 'post_date', 'admin_mail_to', 'response_status' );
		return array_values( array_diff( array_keys( $columns ), $builtin ) );
	}

	/**
	 * 異常系: エントリのメタキー（メール本文のタグ由来）に混入したHTMLやクォートが、
	 * 列識別子として使われないこと。WordPress は列識別子を id / class 属性に
	 * そのまま出力するため、識別子自体が属性を破れない文字列である必要がある。
	 *
	 * @test
	 * @group contact_data_list
	 */
	public function column_identifier__is_safe_for_attributes() {
		$evil_key = 'x\'><img src=x onerror=alert(/XSS/)>';
		list( $post_type, $entry_id ) = $this->_create_form_and_entry(
			array( $evil_key => '' )
		);

		$controller       = $this->_controller( $post_type );
		$GLOBALS['posts'] = array( get_post( $entry_id ) );
		$columns          = $controller->_add_form_columns_name( array() );

		// 生の危険なキーが列識別子として使われていないこと。
		$this->assertArrayNotHasKey( $evil_key, $columns );

		// 動的に追加された識別子はすべて属性に安全な文字だけであること。
		$dynamic_ids = $this->_dynamic_column_ids( $columns );
		$this->assertNotEmpty( $dynamic_ids );
		foreach ( $dynamic_ids as $id ) {
			$this->assertMatchesRegularExpression(
				'/\A[A-Za-z0-9_-]+\z/',
				(string) $id,
				"Column id must be attribute-safe: {$id}"
			);
		}

		// 表示ラベル自体もエスケープされていること。
		foreach ( $dynamic_ids as $id ) {
			$this->assertStringNotContainsString( '<img', $columns[ $id ] );
		}
	}

	/**
	 * 正常系（逆引き）: 危険なキーが安全な識別子に置き換わっても、
	 * その識別子でセルを描画すると元のメタ値が（エスケープされて）取得できること。
	 *
	 * @test
	 * @group contact_data_list
	 */
	public function column_value__renders_via_safe_identifier() {
		$evil_key = 'x\'><img src=x onerror=alert(/XSS/)>';
		list( $post_type, $entry_id ) = $this->_create_form_and_entry(
			array( $evil_key => 'the-meta-value' )
		);

		$controller       = $this->_controller( $post_type );
		$GLOBALS['posts'] = array( get_post( $entry_id ) );
		$columns          = $controller->_add_form_columns_name( array() );

		$dynamic_ids = $this->_dynamic_column_ids( $columns );
		$this->assertCount( 1, $dynamic_ids );
		$safe_id = $dynamic_ids[0];

		// 安全な識別子から元メタキーへ逆引きして値を取得できること。
		$html = $this->_render_column( $controller, $safe_id, $entry_id );
		$this->assertEquals( 'the-meta-value', $html );
	}

	/**
	 * 正常系: 安全なスラッグ（[a-z0-9_-]のみ）の通常キーは識別子が変更されず、
	 * 既存の表示項目設定や他プラグインの列が壊れないこと。
	 *
	 * @test
	 * @group contact_data_list
	 */
	public function safe_key__is_preserved_as_identifier() {
		list( $post_type, $entry_id ) = $this->_create_form_and_entry(
			array( 'your-name' => 'Taro' )
		);

		$controller       = $this->_controller( $post_type );
		$GLOBALS['posts'] = array( get_post( $entry_id ) );
		$columns          = $controller->_add_form_columns_name( array() );

		$this->assertArrayHasKey( 'your-name', $columns );
		$this->assertEquals( 'Taro', $this->_render_column( $controller, 'your-name', $entry_id ) );
	}

	/**
	 * 正常系: 属性に安全な文字だけなら大文字を含むキーも識別子を維持すること
	 * （sanitize_key の小文字化で不要に remap しない）。
	 *
	 * @test
	 * @group contact_data_list
	 */
	public function mixed_case_safe_key__is_preserved() {
		list( $post_type, $entry_id ) = $this->_create_form_and_entry(
			array( 'Your-Name' => 'Taro' )
		);

		$controller       = $this->_controller( $post_type );
		$GLOBALS['posts'] = array( get_post( $entry_id ) );
		$columns          = $controller->_add_form_columns_name( array() );

		$this->assertArrayHasKey( 'Your-Name', $columns );
		$this->assertEquals( 'Taro', $this->_render_column( $controller, 'Your-Name', $entry_id ) );
	}

	/**
	 * 異常系（衝突）: 生成される安全IDと衝突しうる2つのキーがあっても、
	 * どちらの列も失われず、それぞれ正しいメタ値を描画できること。
	 *
	 * @test
	 * @group contact_data_list
	 */
	public function colliding_keys__do_not_overwrite_each_other() {
		// '[x]' はベース経路で 'mwf-x' に、'mwf-x' はそのままだと 'mwf-x' になり衝突しうる。
		list( $post_type, $entry_id ) = $this->_create_form_and_entry(
			array(
				'[x]'   => 'value-a',
				'mwf-x' => 'value-b',
			)
		);

		$controller       = $this->_controller( $post_type );
		$GLOBALS['posts'] = array( get_post( $entry_id ) );
		$columns          = $controller->_add_form_columns_name( array() );

		// 動的列は2つとも残っていること。
		$dynamic_ids = $this->_dynamic_column_ids( $columns );
		$this->assertCount( 2, $dynamic_ids );

		// それぞれの識別子から描画すると、対応する正しい値が取得できること。
		$rendered = array();
		foreach ( $dynamic_ids as $id ) {
			$this->assertMatchesRegularExpression( '/\A[A-Za-z0-9_-]+\z/', (string) $id );
			$rendered[] = $this->_render_column( $controller, $id, $entry_id );
		}
		sort( $rendered );
		$this->assertEquals( array( 'value-a', 'value-b' ), $rendered );
	}
}
