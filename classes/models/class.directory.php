<?php
/**
 * @package mw-wp-form
 * @author websoudan
 * @license GPL-2.0+
 */

class MW_WP_Form_Directory {

	/**
	 * Return the path to the directory where the files are saved.
	 *
	 * @param boolean $is_create_htaccess Default:true.
	 * @return false|string
	 */
	public static function get( $is_create_htaccess = true ) {
		$upload_dir = wp_get_upload_dir();
		$save_dir   = path_join( $upload_dir['basedir'], MWF_Config::NAME . '_uploads' );

		$is_created = wp_mkdir_p( $save_dir ) ? $save_dir : false;
		if ( $is_created && $is_create_htaccess ) {
			static::_create_htaccess( $save_dir );
		}

		return $is_created;
	}

	/**
	 * Return the path to the user directory.
	 *
	 * @return string
	 * @throws \RuntimeException When directory name is not token value.
	 */
	public static function generate_user_dirpath( $form_id ) {
		$saved_token = MW_WP_Form_Csrf::saved_token();
		$saved_token = $saved_token ? $saved_token : MW_WP_Form_Csrf::token();

		if ( ! preg_match( '|^[a-z0-9]+$|', $saved_token ) ) {
			throw new \RuntimeException( '[MW WP Form] Failed to create user directory.' );
		}

		if ( ! preg_match( '/^\d+$/', (string) $form_id ) ) {
			throw new \RuntimeException( '[MW WP Form] Invalid form ID.' );
		}

		$user_dir = path_join( static::get(), $saved_token );
		$user_dir = path_join( $user_dir, (string) $form_id );

		return $user_dir;
	}

	/**
	 * Return the path to the user directory where the files are saved.
	 *
	 * @param int $form_id The form ID.
	 * @param string $name The name attribute value.
	 * @return string
	 * @throws \RuntimeException When directory name is not token value.
	 */
	public static function generate_user_file_dirpath( $form_id, $name ) {
		if ( ! static::_is_valid_path_segment( $name ) ) {
			throw new \RuntimeException( '[MW WP Form] Invalid file reference requested.' );
		}

		$user_dir      = static::generate_user_dirpath( $form_id );
		$user_file_dir = path_join( $user_dir, $name );

		if ( ! static::_is_within_expected_dir_candidate( $form_id, $user_file_dir ) ) {
			throw new \RuntimeException( '[MW WP Form] Invalid file reference requested.' );
		}

		return $user_file_dir;
	}

	/**
	 * Returns true if the directory is empty.
	 *
	 * @param string $dir Target directory.
	 * @return boolean
	 */
	public static function is_empty( $dir ) {
		$iterator = new \FilesystemIterator( $dir );
		return ! $iterator->valid();
	}

	/**
	 * Empty the directory.
	 *
	 * @param string $dir Target directory.
	 * @param boolean $force Ignore the survival period.
	 * @return boolean
	 */
	public static function do_empty( $dir, $force = false ) {
		if ( false === $dir ) {
			return false;
		}

		return static::_remove_children( $dir, $force );
	}

	/**
	 * Remove child directories.
	 *
	 * @param string  $dir   Target directory.
	 * @param boolean $force Ignore the survival period.
	 * @return boolean
	 * @throws \Exception If deletion of the directory fails.
	 */
	protected static function _remove_children( $dir, $force = false ) {
		$fileinfo = new SplFileInfo( $dir );
		if ( ! $fileinfo->isDir() ) {
			return false;
		}

		$iterator = new FilesystemIterator( $dir );

		try {
			foreach ( $iterator as $fileinfo ) {
				$path = $fileinfo->getPathname();

				if ( $fileinfo->isDir() ) {
					if ( static::_remove_children( $path, $force ) && ( $force || static::_is_removable( $path ) ) ) {
						static::remove( $path );
					}
				} elseif ( $fileinfo->isFile() ) {
					if ( $force || static::_is_removable( $path ) ) {
						static::remove( $path );
					}
				}
			}
		} catch ( \Exception $e ) {
			error_log( $e->getMessage() );
			return false;
		}

		return true;
	}

	/**
	 * Takes a file name and returns the file path.
	 * If the file name is invalid, the file path is not returned.
	 * The presence or absence of files is not determined.
	 *
	 * @param int $form_id The form ID.
	 * @param string $name The name attribute value.
	 * @param string $filename The filename.
	 * @return string|false
	 * @throws \RuntimeException When an invalid file reference is requested.
	 */
	public static function generate_user_filepath( $form_id, $name, $filename ) {
		if ( ! $filename ) {
			return false;
		}

		if ( ! static::_is_valid_path_segment( $filename ) ) {
			throw new \RuntimeException( '[MW WP Form] Invalid file reference requested.' );
		}

		$user_file_dir = static::generate_user_file_dirpath( $form_id, $name );
		if ( ! $user_file_dir || ! is_dir( $user_file_dir ) ) {
			return false;
		}

		$filepath = path_join( $user_file_dir, $filename );
		if ( ! static::_is_within_expected_dir_candidate( $form_id, $filepath ) ) {
			throw new \RuntimeException( '[MW WP Form] Invalid file reference requested.' );
		}

		$filepath      = wp_normalize_path( $filepath );
		$user_file_dir = trailingslashit( wp_normalize_path( $user_file_dir ) );

		if ( 0 !== strpos( $filepath, $user_file_dir ) ) {
			throw new \RuntimeException( '[MW WP Form] Invalid file reference requested.' );
		}

		if ( str_contains( $filepath, '../' ) || str_contains( $filepath, '..' . DIRECTORY_SEPARATOR ) ) {
			throw new \RuntimeException( '[MW WP Form] Invalid file reference requested.' );
		}

		if ( str_contains( $filepath, './' ) || str_contains( $filepath, '.' . DIRECTORY_SEPARATOR ) ) {
			throw new \RuntimeException( '[MW WP Form] Invalid file reference requested.' );
		}

		if ( strstr( $filepath, "\0" ) ) {
			throw new \RuntimeException( '[MW WP Form] Invalid file reference requested.' );
		}

		return $filepath;
	}

	/**
	 * Return true when path segment is valid.
	 *
	 * @param string $value Path segment.
	 * @return boolean
	 */
	protected static function _is_valid_path_segment( $value ) {
		if ( ! is_string( $value ) || '' === $value ) {
			return false;
		}

		$value = wp_normalize_path( $value );

		if ( strstr( $value, "\0" ) ) {
			return false;
		}

		if ( '.' === $value || '..' === $value ) {
			return false;
		}

		if ( path_is_absolute( $value ) ) {
			return false;
		}

		if ( wp_basename( $value ) !== $value ) {
			return false;
		}

		return true;
	}

	/**
	 * Return true when candidate path is inside the current user's temp directory.
	 *
	 * @param int    $form_id Form ID.
	 * @param string $path    Target path.
	 * @return boolean
	 */
	protected static function _is_within_expected_dir_candidate( $form_id, $path ) {
		$path = wp_normalize_path( $path );

		$user_dir = static::_get_expected_user_dir( $form_id, static::get() );
		if ( false === $user_dir ) {
			return false;
		}

		$path           = untrailingslashit( $path );
		$user_dir       = untrailingslashit( $user_dir );
		$user_dir_slash = trailingslashit( $user_dir );

		return $path === $user_dir || 0 === strpos( $path, $user_dir_slash );
	}

	/**
	 * Return the expected user directory path.
	 *
	 * @param int         $form_id  Form ID.
	 * @param string|bool $base_dir Base directory path.
	 * @return string|false
	 */
	protected static function _get_expected_user_dir( $form_id, $base_dir ) {
		$saved_token = MW_WP_Form_Csrf::saved_token();
		$saved_token = $saved_token ? $saved_token : MW_WP_Form_Csrf::token();
		if ( ! preg_match( '|^[a-z0-9]+$|', $saved_token ) ) {
			return false;
		}

		if ( ! preg_match( '/^\d+$/', (string) $form_id ) ) {
			return false;
		}

		if ( ! $base_dir ) {
			return false;
		}

		$base_dir = wp_normalize_path( $base_dir );

		return wp_normalize_path(
			path_join(
				path_join( $base_dir, $saved_token ),
				(string) $form_id
			)
		);
	}

	/**
	 * Returns a list of saved file paths.
	 *
	 * @param int $form_id The form ID.
	 * @param array $file_names The file control names list.
	 * @return array
	 */
	public static function get_saved_files( $form_id, $file_names ) {
		$saved_files = array();

		foreach ( $file_names as $name ) {
			$user_file_dir = static::generate_user_file_dirpath( $form_id, $name );
			if ( ! $user_file_dir || ! is_dir( $user_file_dir ) ) {
				continue;
			}

			$iterator = new FilesystemIterator( $user_file_dir );

			foreach ( $iterator as $file ) {
				$saved_files[ $name ] = $file->getPathname();
			}
		}

		return $saved_files;
	}

	/**
	 * Remove the file.
	 *
	 * @param string $file The file path.
	 * @return boolean
	 * @throws \RuntimeException If the deletion of a file fails.
	 */
	public static function remove( $file ) {
		$fileinfo = new SplFileInfo( $file );

		if ( $fileinfo->isFile() && is_writable( $file ) ) {
			if ( ! unlink( $file ) ) {
				throw new \RuntimeException( sprintf( '[MW WP Form] Can\'t remove file: %1$s.', $file ) );
			}
		} elseif ( $fileinfo->isDir() && is_writable( $file ) ) {
			if ( ! rmdir( $file ) ) {
				throw new \RuntimeException( sprintf( '[MW WP Form] Can\'t remove directory: %1$s.', $file ) );
			}
		}

		return true;
	}

	/**
	 * Return true when file removable.
	 *
	 * @param string $file The file path.
	 * @return boolean
	 */
	protected static function _is_removable( $file ) {
		if ( ! file_exists( $file ) ) {
			return false;
		}

		if ( is_dir( $file ) ) {
			if ( ! static::is_empty( $file ) ) {
				return false;
			}
		}

		$mtime         = filemtime( $file );
		$survival_time = 60 * 15;
		return ! $mtime || time() > $mtime + $survival_time;
	}

	/**
	 * Create .htaccess.
	 *
	 * @param string $save_dir The directory where .htaccess is created.
	 * @return boolean
	 * @throws \RuntimeException If the creation of .htaccess fails.
	 */
	protected static function _create_htaccess( $save_dir ) {
		$htaccess = path_join( $save_dir, '.htaccess' );
		if ( file_exists( $htaccess ) ) {
			return true;
		}

		$handle = fopen( $htaccess, 'w' );
		if ( ! $handle ) {
			throw new \RuntimeException( '[MW WP Form] .htaccess can\'t create.' );
		}

		if ( false === fwrite( $handle, "Deny from all\n" ) ) {
			throw new \RuntimeException( '[MW WP Form] .htaccess can\'t write.' );
		}

		if ( ! fclose( $handle ) ) {
			throw new \RuntimeException( '[MW WP Form] .htaccess can\'t close.' );
		}

		return true;
	}
}
