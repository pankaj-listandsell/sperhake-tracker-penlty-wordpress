<?php
/**
 * Regenerate sperhake-tracker.pot by scanning the plugin source with the PHP
 * tokenizer. CLI-only. Run with: php languages/build-pot.php
 */

declare(strict_types=1);

if ( 'cli' !== PHP_SAPI ) {
	exit;
}

$root   = dirname( __DIR__ );
$domain = 'sperhake-tracker';

/*
 * Gettext functions we recognise, with the argument index of each part.
 * idx => zero-based position of the string literal in the call.
 */
$functions = [
	'__'            => [ 'msgid' => 0, 'domain' => 1 ],
	'_e'            => [ 'msgid' => 0, 'domain' => 1 ],
	'esc_html__'    => [ 'msgid' => 0, 'domain' => 1 ],
	'esc_html_e'    => [ 'msgid' => 0, 'domain' => 1 ],
	'esc_attr__'    => [ 'msgid' => 0, 'domain' => 1 ],
	'esc_attr_e'    => [ 'msgid' => 0, 'domain' => 1 ],
	'_x'            => [ 'msgid' => 0, 'context' => 1, 'domain' => 2 ],
	'_ex'           => [ 'msgid' => 0, 'context' => 1, 'domain' => 2 ],
	'esc_html_x'    => [ 'msgid' => 0, 'context' => 1, 'domain' => 2 ],
	'esc_attr_x'    => [ 'msgid' => 0, 'context' => 1, 'domain' => 2 ],
	'_n'            => [ 'msgid' => 0, 'plural' => 1, 'domain' => 3 ],
	'_nx'           => [ 'msgid' => 0, 'plural' => 1, 'context' => 3, 'domain' => 4 ],
	'_n_noop'       => [ 'msgid' => 0, 'plural' => 1, 'domain' => 2 ],
	'_nx_noop'      => [ 'msgid' => 0, 'plural' => 1, 'context' => 2, 'domain' => 3 ],
];

/** Recursively collect plugin PHP files (skipping vendor, languages, node_modules). */
function php_files( string $dir ): array {
	$out = [];
	$it  = new RecursiveIteratorIterator(
		new RecursiveCallbackFilterIterator(
			new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ),
			static function ( SplFileInfo $f ): bool {
				$name = $f->getFilename();
				if ( $f->isDir() ) {
					return ! in_array( $name, [ 'vendor', 'node_modules', 'languages', '.git' ], true );
				}
				return str_ends_with( $name, '.php' );
			}
		)
	);
	foreach ( $it as $f ) {
		$out[] = $f->getPathname();
	}
	sort( $out );
	return $out;
}

/** Decode a PHP quoted string literal token into its runtime value. */
function decode_literal( string $token ): ?string {
	$q = $token[0];
	$inner = substr( $token, 1, -1 );
	if ( "'" === $q ) {
		return strtr( $inner, [ "\\'" => "'", '\\\\' => '\\' ] );
	}
	if ( '"' === $q ) {
		// Only accept double-quoted strings without interpolation.
		if ( preg_match( '/(?<!\\\\)\$/', $inner ) ) {
			return null;
		}
		return stripcslashes( $inner );
	}
	return null;
}

/** Escape a value for the POT format. */
function pot_escape( string $s ): string {
	return str_replace(
		[ '\\', '"', "\n", "\t", "\r" ],
		[ '\\\\', '\\"', '\\n', '\\t', '\\r' ],
		$s
	);
}

$entries = []; // key => ['msgid','plural','context','refs'=>[]].

foreach ( php_files( $root ) as $file ) {
	$rel    = str_replace( '\\', '/', substr( $file, strlen( $root ) + 1 ) );
	$code   = file_get_contents( $file );
	$tokens = token_get_all( $code );
	$count  = count( $tokens );

	for ( $i = 0; $i < $count; $i++ ) {
		$tok = $tokens[ $i ];
		if ( ! is_array( $tok ) || T_STRING !== $tok[0] || ! isset( $functions[ $tok[1] ] ) ) {
			continue;
		}

		// Skip method/static calls ($obj->__(), Foo::__()).
		for ( $p = $i - 1; $p >= 0; $p-- ) {
			if ( is_array( $tokens[ $p ] ) && in_array( $tokens[ $p ][0], [ T_WHITESPACE, T_COMMENT, T_DOC_COMMENT ], true ) ) {
				continue;
			}
			break;
		}
		if ( $p >= 0 && is_array( $tokens[ $p ] ) && in_array( $tokens[ $p ][0], [ T_OBJECT_OPERATOR, T_DOUBLE_COLON, T_NULLSAFE_OBJECT_OPERATOR ], true ) ) {
			continue;
		}

		$fn   = $tok[1];
		$line = $tok[2];

		// Expect '(' next.
		$j = $i + 1;
		while ( $j < $count && is_array( $tokens[ $j ] ) && T_WHITESPACE === $tokens[ $j ][0] ) {
			$j++;
		}
		if ( $j >= $count || '(' !== $tokens[ $j ] ) {
			continue;
		}

		// Collect top-level argument literals (or null when not a plain literal).
		$args  = [];
		$depth = 0;
		$cur   = null; // null = nothing yet, false = non-literal, string = literal value.
		for ( $k = $j; $k < $count; $k++ ) {
			$t = $tokens[ $k ];
			if ( '(' === $t || '[' === $t ) {
				$depth++;
				if ( $depth > 1 ) {
					$cur = false;
				}
				continue;
			}
			if ( ')' === $t || ']' === $t ) {
				$depth--;
				if ( 0 === $depth ) {
					$args[] = $cur;
					break;
				}
				continue;
			}
			if ( 1 === $depth && ',' === $t ) {
				$args[] = $cur;
				$cur    = null;
				continue;
			}
			if ( is_array( $t ) && in_array( $t[0], [ T_WHITESPACE, T_COMMENT, T_DOC_COMMENT ], true ) ) {
				continue;
			}
			if ( 1 === $depth && is_array( $t ) && T_CONSTANT_ENCAPSED_STRING === $t[0] && null === $cur ) {
				$cur = decode_literal( $t[1] );
				if ( null === $cur ) {
					$cur = false;
				}
				continue;
			}
			// Anything else at arg level → not a plain literal.
			if ( 1 === $depth ) {
				$cur = false;
			}
		}

		$spec  = $functions[ $fn ];
		$msgid = $args[ $spec['msgid'] ] ?? null;
		if ( ! is_string( $msgid ) || '' === $msgid ) {
			continue; // Skip dynamic/empty msgids.
		}

		$context = null;
		if ( isset( $spec['context'] ) ) {
			$c = $args[ $spec['context'] ] ?? null;
			$context = is_string( $c ) ? $c : null;
		}
		$plural = null;
		if ( isset( $spec['plural'] ) ) {
			$pl = $args[ $spec['plural'] ] ?? null;
			$plural = is_string( $pl ) ? $pl : null;
		}

		$key = ( $context ?? '' ) . "\4" . $msgid . "\4" . ( $plural ?? '' );
		if ( ! isset( $entries[ $key ] ) ) {
			$entries[ $key ] = [
				'msgid'   => $msgid,
				'plural'  => $plural,
				'context' => $context,
				'refs'    => [],
			];
		}
		$entries[ $key ]['refs'][] = $rel . ':' . $line;
	}
}

// Stable order: by context then msgid.
uasort(
	$entries,
	static function ( $a, $b ) {
		return [ $a['context'] ?? '', $a['msgid'] ] <=> [ $b['context'] ?? '', $b['msgid'] ];
	}
);

// Header.
$date = gmdate( 'Y-m-d H:iO' );
$pot  = <<<HEAD
# Copyright (C) 2026 Abschleppdienst Sperhake
# This file is distributed under the GPL-2.0-or-later license.
msgid ""
msgstr ""
"Project-Id-Version: Sperhake Vehicle Tracking & Penalty Payment 1.3.0\\n"
"Report-Msgid-Bugs-To: https://abschleppdienst-sperhake.de/\\n"
"POT-Creation-Date: {$date}\\n"
"Language-Team: LANGUAGE <LL@li.org>\\n"
"MIME-Version: 1.0\\n"
"Content-Type: text/plain; charset=UTF-8\\n"
"Content-Transfer-Encoding: 8bit\\n"
"Plural-Forms: nplurals=2; plural=(n != 1);\\n"
"X-Domain: {$domain}\\n"

HEAD;
$pot .= "\n";

// Plugin header metadata (not captured by gettext calls).
$header_meta = [
	[ 'Plugin Name', 'Sperhake Vehicle Tracking & Penalty Payment' ],
	[ 'Description', 'Vehicle lookup by license plate, towing status, and online penalty payment via Stripe for Abschleppdienst Sperhake.' ],
	[ 'Author', 'Abschleppdienst Sperhake' ],
];
foreach ( $header_meta as [ $label, $value ] ) {
	$pot .= "#. {$label} of the plugin\n";
	$pot .= 'msgid "' . pot_escape( $value ) . "\"\n";
	$pot .= "msgstr \"\"\n\n";
}

foreach ( $entries as $e ) {
	$refs = array_unique( $e['refs'] );
	// Wrap reference lines at a sensible width.
	$line = '#:';
	foreach ( $refs as $ref ) {
		if ( strlen( $line ) + strlen( $ref ) + 1 > 76 ) {
			$pot  .= $line . "\n";
			$line  = '#:';
		}
		$line .= ' ' . $ref;
	}
	$pot .= $line . "\n";

	if ( null !== $e['context'] ) {
		$pot .= 'msgctxt "' . pot_escape( $e['context'] ) . "\"\n";
	}
	$pot .= 'msgid "' . pot_escape( $e['msgid'] ) . "\"\n";
	if ( null !== $e['plural'] ) {
		$pot .= 'msgid_plural "' . pot_escape( $e['plural'] ) . "\"\n";
		$pot .= "msgstr[0] \"\"\n";
		$pot .= "msgstr[1] \"\"\n\n";
	} else {
		$pot .= "msgstr \"\"\n\n";
	}
}

file_put_contents( __DIR__ . '/' . $domain . '.pot', $pot );
printf( "Wrote %d entries to %s.pot%s", count( $entries ), $domain, PHP_EOL );
