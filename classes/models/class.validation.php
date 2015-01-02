<?php
/**
 * Name       : MW WP Form Validation
 * Description: 与えられたデータに対してバリデーションエラーがあるかチェックする
 * Version    : 1.8.0
 * Author     : Takashi Kitajima
 * Author URI : http://2inc.org
 * Created    : July 20, 2012
 * Modified   : December 31, 2014
 * License    : GPLv2
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 */
class MW_WP_Form_Validation {

	/**
	 * $Error
	 * @var MW_WP_Form_Error
	 */
	protected $Error;

	/**
	 * $validate
	 * バリデートをかける項目（name属性）と、それにかけるバリデーションの配列
	 * @var array
	 */
	public $validate = array();

	/**
	 * $validation_rules
	 * バリデーションルールの配列
	 * @var array
	 */
	protected $validation_rules = array();

	/**
	 * __construct
	 * @param MW_WP_Form_Error $Error
	 */
	public function __construct( MW_WP_Form_Error $Error ) {
		$this->Error = $Error;
	}

	/**
	 * set_validation_rules
	 * 各バリデーションルールクラスのインスタンスをセット
	 * @param array $validation_rules
	 */
	public function set_validation_rules( array $validation_rules ) {
		foreach ( $validation_rules as $validation_name => $instance ) {
			if ( is_callable( array( $instance, 'rule' ) ) ) {
				$this->validation_rules[$instance->getName()] = $instance;
			}
		}
	}

	/**
	 * is_valid
	 * バリデートが通っているかチェック
	 * @return bool
	 */
	protected function is_valid() {
		$errors = $this->Error->get_errors();
		if ( empty( $errors ) ) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * set_rules
	 * @param array $rules
	 */
	public function set_rules( array $rules ) {
		foreach ( $rules as $target => $rule ) {
			$this->set_rule( $target, $rule['rule'], $rule['options'] );
		}
	}

	/**
	 * set_rule
	 * @param string ターゲットのname属性
	 * @param string バリデーションルール名
	 * @param array オプション
	 * @return bool
	 */
	protected function set_rule( $key, $rule, array $options = array() ) {
		$rules = array(
			'rule'    => strtolower( $rule ),
			'options' => $options
		);
		$this->validate[$key][] = $rules;
		return $this;
	}

	/**
	 * check
	 * validate実行
	 * @return bool エラーがなければ true
	 */
	public function check() {
		foreach ( $this->validate as $key => $rules ) {
			$this->_check( $key, $rules );
		}
		return $this->is_valid();
	}

	/**
	 * single_check
	 * 特定の項目のvalidate実行
	 * @param string $key
	 * @return bool エラーがなければ true
	 */
	public function single_check( $key ) {
		$rules = array();
		if ( is_array( $this->validate ) && isset( $this->validate[$key] ) ) {
			$rules = $this->validate[$key];
			if ( $this->_check( $key, $rules ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * _check
	 * validate実行の実体
	 * @param string $key
	 * @param array $rules
	 * @return bool エラーがあれば true
	 */
	protected function _check( $key, array $rules ) {
		foreach ( $rules as $rule_set ) {
			if ( !isset( $rule_set['rule'] ) ) {
				continue;
			}
			$rule = $rule_set['rule'];
			if ( !isset( $this->validation_rules[$rule] ) ) {
				continue;
			}
			$options = array();
			if ( isset( $rule_set['options'] ) ) {
				$options = $rule_set['options'];
			}
			$validation_rule = $this->validation_rules[$rule];
			if ( is_callable( array( $validation_rule, 'rule' ) ) ) {
				$message = $validation_rule->rule( $key, $options );
				if ( !empty( $message ) ) {
					$this->Error->set_error( $key, $rule, $message );
					return true;
				}
			}
		}
	}
}
