<?php
/**
 * Tests for Registrar::build_meta() and the MCP-only tool type.
 *
 * @package Ketunkoti\AI
 */

namespace Ketunkoti\AI\Tests;

use Ketunkoti\AI\Registrar;

require_once __DIR__ . '/bootstrap.php';

$ro = [
	'readonly'    => true,
	'destructive' => false,
	'idempotent'  => true,
];
$m  = Registrar::build_meta( [], $ro, 'mcp_tool' );
check( 'mcp_tool not public', false, $m['public'] );
check( 'mcp_tool not in rest', false, $m['show_in_rest'] );
check( 'mcp_tool type', 'tool', $m['mcp']['type'] );
check( 'mcp_tool mcp public when readonly', true, $m['mcp']['public'] );

$t = Registrar::build_meta( [], $ro, 'tool' );
check( 'tool stays public', true, $t['public'] );
check( 'tool in rest', true, $t['show_in_rest'] );

$p = Registrar::build_meta( [], $ro, 'prompt' );
check( 'prompt not public', false, $p['public'] );
check( 'prompt type', 'prompt', $p['mcp']['type'] );

$w = Registrar::build_meta(
	[],
	[
		'readonly'    => false,
		'destructive' => false,
		'idempotent'  => false,
	],
	'mcp_tool'
);
check( 'mcp_tool write not mcp public', false, $w['mcp']['public'] );

check( 'home ability still public', true, wp_get_ability( 'ketunkoti/get-home' )->get_meta()['public'] ?? null );

finish();
