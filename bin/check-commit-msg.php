<?php
/**
 * Script to validate commit message
 *
 * @package   Google\Site_Kit
 * @copyright 2020 Google LLC
 * @license   https://www.apache.org/licenses/LICENSE-2.0 Apache License 2.0
 * @link      https://sitekit.withgoogle.com
 */

/**
 * Determines whether or not the current terminal supports colors.
 * 
 * @since n.e.x.t
 * 
 * @return boolean TRUE if the terminal supports colors, otherwise FALSE.
 */
function has_color_support() {
	if ( isset( $_SERVER['NO_COLOR'] ) || false !== getenv( 'NO_COLOR' ) ) {
		return false;
	}

	if ( 'Hyper' === getenv( 'TERM_PROGRAM' ) ) {
		return true;
	}

	if ( DIRECTORY_SEPARATOR === '\\' ) {
		return ( function_exists( 'sapi_windows_vt100_support' ) && sapi_windows_vt100_support( STDERR ) ) // phpcs:ignore PHPCompatibility.FunctionUse.NewFunctions.sapi_windows_vt100_supportFound
			|| false !== getenv( 'ANSICON' )
			|| 'ON' === getenv( 'ConEmuANSI' )
			|| 'xterm' === getenv( 'TERM' );
	}

	if ( function_exists( 'stream_isatty' ) ) {
		return stream_isatty( STDERR ); // phpcs:ignore PHPCompatibility.FunctionUse.NewFunctions.stream_isattyFound
	}

	return false;
}

/**
 * Writes the message to the STDERR stream if the provided condition is true.
 * 
 * @since n.e.x.t
 * 
 * @param boolean $condition The condition to check.
 * @param string  $message The message to write if condition is met.
 * @return int The error code number.
 */
function echo_error_if( $condition, $message ) {
	static $code = 1;

	$current_code = $code;
	$code         = $code << 1;

	if ( $condition ) {
		$has_color_support = has_color_support();

		$has_color_support && fwrite( STDERR, "\033[31m" ); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_fwrite
		fwrite( STDERR, '- ' . $message ); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_fwrite
		$has_color_support && fwrite( STDERR, "\033[0m" ); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_fwrite
		fwrite( STDERR, PHP_EOL ); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_fwrite

		return $current_code;
	}

	return 0;
}

/**
 * Filters message line and returns FALSE if the line is a comment.
 * 
 * @since n.e.x.t
 * 
 * @param string $line The line to check.
 * @return boolean TRUE if the line is not a comment, otherwise FALSE.
 */
function is_not_comment( $line ) {
	return preg_match( '/^[^#]+/', $line );
}

$error_code = echo_error_if(
	empty( $argv[1] ) || ! file_exists( $argv[1] ),
	'The commit message hasn\'t been found.'
);

// die early if the file with commit message hasn't been found.
if ( $error_code ) {
	exit( $error_code ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}

// read file and prepare commit message.
$message    = explode( PHP_EOL, file_get_contents( $argv[1] ) ); // phpcs:ignore WordPressVIPMinimum.Performance.FetchingRemoteData.FileGetContentsUnknown
$message    = trim( implode( ' ', array_filter( $message, 'is_not_comment' ) ) );
$words      = array_filter( array_map( 'trim', preg_split( '/[^A-Za-z-]+/', $message ) ) );
$first_word = current( $words );

// message starts with a capital letter.
$error_code |= echo_error_if(
	! preg_match( '/^[A-Z][a-z][a-z-]*$/', $first_word ),
	'The commit message must start with a capital letter.'
);

// first word of the message does not end in "ed" or "es".
$error_code |= echo_error_if(
	preg_match( '/(ed|es|-)$/', $first_word ),
	'The commit message must start with a verb in present tense.'
);

// message ends in a dot.
$error_code |= echo_error_if(
	preg_match( '/[^\.]$/', $message ),
	'The commit message must end with a full stop.'
);

// single word commit.
$error_code |= echo_error_if(
	count( $words ) < 2,
	'The commit message cannot be a signle word.'
);

// exit with non-zero code if errors have been found.
if ( $error_code ) {
	exit( $error_code ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}
