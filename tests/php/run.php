<?php
/**
 * Minimal PHP test runner for atomy-core unit tests.
 */

declare( strict_types=1 );

$passed = 0;
$failed = 0;

function assert_true( bool $condition, string $message = '' ): void {
	global $passed, $failed;
	if ( $condition ) {
		++$passed;
		return;
	}
	++$failed;
	$label = $message !== '' ? $message : 'assertion failed';
	fwrite( STDERR, "FAIL: {$label}\n" );
}

function test_summary(): void {
	global $passed, $failed;
	if ( $failed > 0 ) {
		fwrite( STDERR, "FAILED: {$failed} test(s), passed {$passed}\n" );
		exit( 1 );
	}
	echo "ALL TESTS PASSED ({$passed})\n";
	exit( 0 );
}
