<?php
class MW_WP_Form_Session_Test extends WP_UnitTestCase {

	public function tear_down() {
		parent::tear_down();
		_delete_all_data();
	}

	/**
	 * @test
	 * @group save
	 */
	public function save() {
		$Session = new MW_WP_Form_Session( 'session' );
		$Session->save( array(
			'name-1' => 'value-1',
			'name-2' => 'value-2',
		) );
		$this->assertEquals(
			array(
				'name-1' => 'value-1',
				'name-2' => 'value-2',
			),
			$Session->gets()
		);
	}

	/**
	 * @test
	 * @group set
	 */
	public function set() {
		$Session = new MW_WP_Form_Session( 'session' );
		$Session->set( 'name-1', 'value-1' );
		$this->assertEquals( 'value-1', $Session->get( 'name-1' ) );
	}

	/**
	 * @test
	 * @group push
	 */
	public function push() {
		$Session = new MW_WP_Form_Session( 'session' );
		$Session->save( array(
			'name-1' => array( 'value-1-1' ),
			'name-2' => 'value-2-1',
		) );
		$Session->push( 'name-1', 'value-1-2' );
		$Session->push( 'name-2', 'value-2-2' );
		$Session->push( 'name-3', 'value-3-1' );
		$this->assertEquals(
			array(
				'name-1' => array( 'value-1-1', 'value-1-2' ),
				'name-2' => array( 'value-2-1', 'value-2-2' ),
				'name-3' => array( 'value-3-1' ),
			),
			$Session->gets()
		);
	}

	/**
	 * @test
	 * @group get
	 */
	public function get() {
		$Session = new MW_WP_Form_Session( 'session' );
		$Session->set( 'name-1', 'value-1' );
		$this->assertEquals( 'value-1', $Session->get( 'name-1' ) );
	}

	/**
	 * @test
	 * @group gets
	 */
	public function gets() {
		$Session = new MW_WP_Form_Session( 'session' );
		$Session->save( array(
			'name-1' => 'value-1',
			'name-2' => 'value-2',
		) );
		$this->assertEquals(
			array(
				'name-1' => 'value-1',
				'name-2' => 'value-2',
			),
			$Session->gets()
		);
	}

	/**
	 * @test
	 * @group clear_value
	 */
	public function clear_value() {
		$Session = new MW_WP_Form_Session( 'session' );
		$Session->set( 'name-1', 'value-1' );
		$this->assertEquals( 'value-1', $Session->get( 'name-1' ) );
		$Session->clear_value( 'name-1' );
		$this->assertNull( $Session->get( 'name-1' ) );
	}

	/**
	 * @test
	 * @group session_id
	 */
	public function session_id_format() {
		$cookie_name = MWF_Config::NAME . '_session_session';

		$session_ids = array(
			'abc',
			str_repeat( 'A', 40 ),
			sha1( 'test' ) . '0',
			substr( sha1( 'test' ), 0, 39 ),
			sha1( 'test' ) . "\n",
		);

		foreach ( $session_ids as $session_id ) {
			$_COOKIE[ $cookie_name ] = $session_id;
			set_transient( $session_id, array( 'key' => 'value' ) );

			$Session = new MW_WP_Form_Session( 'session' );
			$this->assertSame( array(), $Session->gets() );

			$Session->set( 'name-1', 'value-1' );
			$this->assertEquals(
				array( 'key' => 'value' ),
				get_transient( $session_id )
			);

			$Session->clear_values();
			$this->assertEquals(
				array( 'key' => 'value' ),
				get_transient( $session_id )
			);

			delete_transient( $session_id );
			unset( $_COOKIE[ $cookie_name ] );
		}
	}

	/**
	 * @test
	 * @group session_id
	 */
	public function session_id_prefix() {
		$session_id  = sha1( 'test' );
		$cookie_name = MWF_Config::NAME . '_session_session';

		$_COOKIE[ $cookie_name ] = $session_id;

		$Session = new MW_WP_Form_Session( 'session' );
		$Session->set( 'name-1', 'value-1' );

		$this->assertFalse( get_transient( $session_id ) );
		$this->assertEquals(
			array( 'name-1' => 'value-1' ),
			get_transient( MWF_Config::NAME . '_session_' . $session_id )
		);

		$Session->clear_values();
		unset( $_COOKIE[ $cookie_name ] );
	}

	/**
	 * @test
	 * @group clear_values
	 */
	public function clear_values() {
		$Session = new MW_WP_Form_Session( 'session' );
		$Session->save( array(
			'name-1' => array( 'value-1-1' ),
			'name-2' => array( 'value-2-1' ),
		) );
		$Session->clear_values();
		$this->assertNull( $Session->get( 'name-1' ) );
		$this->assertNull( $Session->get( 'name-2' ) );
		$this->assertSame( array(), $Session->gets() );
	}
}
