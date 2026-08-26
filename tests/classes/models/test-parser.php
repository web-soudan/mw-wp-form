<?php
class MW_WP_Form_Parser_Test extends WP_UnitTestCase {

	protected function _create_form() {
		return $this->factory->post->create(
			array(
				'post_type' => MWF_Config::NAME,
			)
		);
	}

	public function tear_down() {
		parent::tear_down();
		_delete_all_data();
	}

	/**
	 * @test
	 * @group replace_for_mail_destination
	 */
	public function replace_for_mail_destination() {
		$form_id  = $this->_create_form();
		$form_key = MWF_Functions::get_form_key_from_form_id( $form_id );
		$Setting  = new MW_WP_Form_Setting( $form_id );
		$Data     = MW_WP_Form_Data::connect( $form_key );
		$Parser   = new MW_WP_Form_Parser( $Setting );

		$Data->set( 'name-1', 'value-1' );
		$content = 'abcde {name-1} fghijk {name-2} lmnopq';
		$this->assertEquals( 'abcde  fghijk  lmnopq', $Parser->replace_for_mail_destination( $content ) );

		add_filter( 'mwform_custom_mail_tag', function( $value, $name, $saved_mail_id ) {
			if ( 'name-1' === $name ) {
				return 'custom-value-1';
			}
			return $value;
		}, 10, 3 );
		$this->assertEquals( 'abcde custom-value-1 fghijk  lmnopq', $Parser->replace_for_mail_destination( $content ) );
	}

	/**
	 * @test
	 * @group replace_for_mail_content
	 */
	public function replace_for_mail_content() {
		$form_id  = $this->_create_form();
		$form_key = MWF_Functions::get_form_key_from_form_id( $form_id );
		$Setting  = new MW_WP_Form_Setting( $form_id );
		$Data     = MW_WP_Form_Data::connect( $form_key );
		$Parser   = new MW_WP_Form_Parser( $Setting );

		$Data->set( 'name-1', 'value-1' );
		$content = 'abcde {name-1} fghijk {name-2} lmnopq';
		$this->assertEquals( 'abcde value-1 fghijk  lmnopq', $Parser->replace_for_mail_content( $content ) );

		add_filter( 'mwform_custom_mail_tag', function( $value, $name, $saved_mail_id ) {
			if ( 'name-1' === $name ) {
				return 'custom-value-1';
			}
		}, 10, 3 );
		$this->assertEquals( 'abcde custom-value-1 fghijk  lmnopq', $Parser->replace_for_mail_content( $content ) );
	}

	/**
	 * 正常系: 完了画面向けの置換。通常の値はそのまま差し込まれ、
	 * 未入力のタグは空文字になる（replace_for_mail_content と同じ挙動）。
	 *
	 * @test
	 * @group replace_for_complete_page
	 */
	public function replace_for_complete_page__normal() {
		$form_id  = $this->_create_form();
		$form_key = MWF_Functions::get_form_key_from_form_id( $form_id );
		$Setting  = new MW_WP_Form_Setting( $form_id );
		$Data     = MW_WP_Form_Data::connect( $form_key );
		$Parser   = new MW_WP_Form_Parser( $Setting );

		$Data->set( 'name-1', 'value-1' );
		$content = 'abcde {name-1} fghijk {name-2} lmnopq';
		$this->assertEquals( 'abcde value-1 fghijk  lmnopq', $Parser->replace_for_complete_page( $content ) );

		// ブラケットを含まない custom mail tag の値もそのまま反映される。
		add_filter( 'mwform_custom_mail_tag', function( $value, $name, $saved_mail_id ) {
			if ( 'name-1' === $name ) {
				return 'custom-value-1';
			}
			return $value;
		}, 10, 3 );
		$this->assertEquals( 'abcde custom-value-1 fghijk  lmnopq', $Parser->replace_for_complete_page( $content ) );
	}

	/**
	 * 異常系: 送信値にショートコード構文が含まれていても、
	 * 置換後にブラケットが無効化され do_shortcode で実行されないこと。
	 * ラッパー [mwform_complete_message] の閉じタグによるブレイクアウトや、
	 * フォームが宣言していないキー経由の値も同様に無効化される。
	 *
	 * @test
	 * @group replace_for_complete_page
	 */
	public function replace_for_complete_page__neutralizes_shortcode_syntax() {
		$form_id  = $this->_create_form();
		$form_key = MWF_Functions::get_form_key_from_form_id( $form_id );
		$Setting  = new MW_WP_Form_Setting( $form_id );
		$Data     = MW_WP_Form_Data::connect( $form_key );
		$Parser   = new MW_WP_Form_Parser( $Setting );

		// 単純なショートコード構文を含む値。
		$Data->set( 'name-1', '[caption]x[/caption]' );
		$this->assertEquals(
			'Reference: &#91;caption&#93;x&#91;/caption&#93;',
			$Parser->replace_for_complete_page( 'Reference: {name-1}' )
		);

		// ラッパーの閉じタグによるブレイクアウトを狙った値。
		$Data->set( 'name-2', '[/mwform_complete_message][caption caption="XSS"]x[/caption]' );
		$replaced = $Parser->replace_for_complete_page( '{name-2}' );
		$this->assertStringNotContainsString( '[', $replaced );
		$this->assertStringNotContainsString( ']', $replaced );
		$this->assertEquals(
			'&#91;/mwform_complete_message&#93;&#91;caption caption="XSS"&#93;x&#91;/caption&#93;',
			$replaced
		);

		// フォームが宣言していないキー（$_POST 直渡し）経由の値も無効化される。
		$Data->set( 'undeclared', '[caption]y[/caption]' );
		$replaced = $Parser->replace_for_complete_page( '{undeclared}' );
		$this->assertStringNotContainsString( '[', $replaced );
		$this->assertStringNotContainsString( ']', $replaced );

		// custom mail tag 経由でブラケットが返された場合も無効化される。
		add_filter( 'mwform_custom_mail_tag', function( $value, $name, $saved_mail_id ) {
			if ( 'evil' === $name ) {
				return '[caption]z[/caption]';
			}
			return $value;
		}, 10, 3 );
		$replaced = $Parser->replace_for_complete_page( '{evil}' );
		$this->assertStringNotContainsString( '[', $replaced );
		$this->assertStringNotContainsString( ']', $replaced );

		// 実際に do_shortcode を通しても、無効化済みのためショートコードが展開されないこと。
		$Data->set( 'name-3', '[/mwform_complete_message][caption caption="EXECUTED"]x[/caption]' );
		$wrapped = sprintf(
			'[mwform_complete_message]%s[/mwform_complete_message]',
			$Parser->replace_for_complete_page( 'Reference: {name-3}' )
		);
		add_shortcode( 'mwform_complete_message', function( $atts, $content = '' ) {
			return $content;
		} );
		$this->assertStringNotContainsString( 'wp-caption', do_shortcode( $wrapped ) );
		remove_shortcode( 'mwform_complete_message' );
	}

	/**
	 * @test
	 * @group replace_for_page
	 */
	public function replace_for_page() {
		$form_id  = $this->_create_form();
		$form_key = MWF_Functions::get_form_key_from_form_id( $form_id );
		$Setting  = new MW_WP_Form_Setting( $form_id );
		$Data     = MW_WP_Form_Data::connect( $form_key );
		$Parser   = new MW_WP_Form_Parser( $Setting );

		$user      = wp_get_current_user();
		$post_id   = $this->factory->post->create( array( 'post_title' => 'title', 'post_type' => 'post' ) );
		$author_id = $this->factory->user->create( array( 'display_name' => 'user' ) );
		$this->go_to( get_permalink( $post_id ) );
		$Data->set( 'display_name', 'dummy' );
		$this->go_to( get_permalink($post_id) );

		$content = 'abcde {display_name} fghijk {name-1} lmnopq';
		$this->assertEquals(
			'abcde ' . get_the_author_meta( 'display_name', $user ) . ' fghijk  lmnopq',
			$Parser->replace_for_page( $content )
		);

		$content = 'abcde {post_title} fghijk {name-1} lmnopq';
		$this->assertEquals(
			'abcde title fghijk  lmnopq',
			$Parser->replace_for_page( $content )
		);

		$this->go_to( home_url( '?post_id=' . $post_id ) );
		$this->assertEquals(
			'abcde  fghijk  lmnopq',
			$Parser->replace_for_page( $content )
		);

		$Setting->set( 'querystring', 1 );
		$this->go_to( home_url( '?post_id=' . $post_id ) );
		$this->assertEquals(
			'abcde title fghijk  lmnopq',
			$Parser->replace_for_page( $content )
		);
	}

	/**
	 * @test
	 * @group search
	 */
	public function search() {
		$content = 'abcde {name-1} fghijk {name-2} lmnopq';
		$matches = MW_WP_Form_Parser::search( $content );
		$this->assertEquals( '{name-1}', $matches[0][0] );
		$this->assertEquals( '{name-2}', $matches[0][1] );
	}

	/**
	 * @test
	 * @group parse
	 */
	public function parse() {
		$form_id  = $this->_create_form();
		$form_key = MWF_Functions::get_form_key_from_form_id( $form_id );
		$Setting  = new MW_WP_Form_Setting( $form_id );
		$Data     = MW_WP_Form_Data::connect( $form_key );
		$Parser   = new MW_WP_Form_Parser( $Setting );

		// Pattern: Tracking number
		$value = $Parser->parse( MWF_Config::TRACKINGNUMBER );
		$this->assertEquals( 1, $value );

		// Pattern: custom mail tag
		add_filter( 'mwform_custom_mail_tag', function( $value, $name, $saved_mail_id ) {
			if ( 'name-1' === $name ) {
				return 'custom-value-1';
			}
			return $value;
		}, 10, 3 );
		$value = $Parser->parse( 'name-1' );
		$this->assertEquals( 'custom-value-1', $value );

		// Pattern: default
		$Data->set( 'name-1', 'value-1' );
		$Data->set( 'name-2', 'value-2' );
		$value = $Parser->parse( 'name-1' );
		$this->assertEquals( 'custom-value-1', $value );
		$value = $Parser->parse( 'name-2' );
		$this->assertEquals( 'value-2', $value );
	}
}
