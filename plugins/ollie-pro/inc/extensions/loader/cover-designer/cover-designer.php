<?php
/**
 * Cover Designer Control
 *
 * Server-renders the pure-CSS gradient background for Cover blocks that
 * opted in. The generators (blobs, waves, mesh, halftone), palette
 * derivation, and sanitization all mirror their counterparts in
 * /inc/extensions/src/controls/cover-designer/index.js — both sides use
 * the same Park-Miller PRNG, so stored settings render the identical
 * design in the editor and on the frontend. Keep the two implementations
 * in sync. The PRNG multiply assumes 64-bit PHP integers.
 *
 * @package OllieUIHelpers
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Setting defaults and clamps.
 *
 * @return array
 */
function ollie_ui_helpers_cover_designer_defaults() {
	return array(
		'color'      => '#2f6fbe',
		'color2'     => '#d96ba1',
		'softness'   => 2.05,
		'saturation' => 1.4,
		'opacity'    => 1.0,
		'rotation'   => 52,
		'zoom'       => 14,
		'stretch'    => 2.5,
		'speed'      => 1.0,
		'seed'       => 20260801,
		'animate'    => false,
		'mode'       => 'dark',
	);
}

/**
 * Validate a #rrggbb hex color.
 *
 * @param mixed $color Raw value.
 * @return string Sanitized hex or empty string.
 */
function ollie_ui_helpers_cover_designer_sanitize_hex( $color ) {
	if ( is_string( $color ) && preg_match( '/^#[0-9a-fA-F]{6}$/', trim( $color ) ) ) {
		return strtolower( trim( $color ) );
	}
	return '';
}

/**
 * Whitelist and clamp settings before they hit the page.
 *
 * @param array $gradient Raw ollieGradient attribute.
 * @return array Sanitized settings.
 */
function ollie_ui_helpers_cover_designer_sanitize( array $gradient ) {
	$defaults = ollie_ui_helpers_cover_designer_defaults();
	$clamps   = array(
		'softness'   => array( 1, 5 ),
		'saturation' => array( 0, 2 ),
		'opacity'    => array( 0, 1 ),
		'rotation'   => array( 0, 360 ),
		'zoom'       => array( 1, 30 ), // Wider than the slider for legacy stored values.
		'stretch'    => array( 1, 8 ),
		'speed'      => array( 0, 15 ),
	);

	$out = array();

	foreach ( array( 'color', 'color2' ) as $key ) {
		$hex         = ollie_ui_helpers_cover_designer_sanitize_hex( $gradient[ $key ] ?? '' );
		$out[ $key ] = $hex ? $hex : $defaults[ $key ];
	}

	foreach ( $clamps as $key => $range ) {
		$value       = isset( $gradient[ $key ] ) && is_numeric( $gradient[ $key ] )
			? (float) $gradient[ $key ]
			: (float) $defaults[ $key ];
		$out[ $key ] = max( $range[0], min( $range[1], $value ) );
	}

	$seed        = isset( $gradient['seed'] ) && is_numeric( $gradient['seed'] )
		? (int) $gradient['seed']
		: $defaults['seed'];
	$out['seed'] = max( 1, min( 2147483646, $seed ) );

	// Animation is opt-in: absent means off, matching the editor default.
	$out['animate'] = ! empty( $gradient['animate'] );

	$out['mode'] = ( isset( $gradient['mode'] ) && 'light' === $gradient['mode'] ) ? 'light' : 'dark';

	$out['variant'] = ( isset( $gradient['variant'] ) && in_array( $gradient['variant'], array( 'waves', 'mesh' ), true ) )
		? $gradient['variant']
		: 'glow';

	return $out;
}

/**
 * Convert #rrggbb to [h, s, l].
 *
 * @param string $hex Hex color.
 * @return array
 */
function ollie_ui_helpers_cover_designer_hex_to_hsl( $hex ) {
	$r   = hexdec( substr( $hex, 1, 2 ) ) / 255;
	$g   = hexdec( substr( $hex, 3, 2 ) ) / 255;
	$b   = hexdec( substr( $hex, 5, 2 ) ) / 255;
	$max = max( $r, $g, $b );
	$min = min( $r, $g, $b );
	$l   = ( $max + $min ) / 2;
	$d   = $max - $min;
	if ( 0.0 === (float) $d ) {
		return array( 0.0, 0.0, $l );
	}
	$s = $d / ( 1 - abs( 2 * $l - 1 ) );
	if ( $max === $r ) {
		$h = fmod( ( $g - $b ) / $d, 6.0 );
	} elseif ( $max === $g ) {
		$h = ( $b - $r ) / $d + 2;
	} else {
		$h = ( $r - $g ) / $d + 4;
	}
	$h = fmod( $h * 60 + 360, 360.0 );
	return array( $h, $s, $l );
}

/**
 * Convert h/s/l to #rrggbb.
 *
 * @param float $h Hue in degrees.
 * @param float $s Saturation 0-1.
 * @param float $l Lightness 0-1.
 * @return string
 */
function ollie_ui_helpers_cover_designer_hsl_to_hex( $h, $s, $l ) {
	$h = fmod( fmod( $h, 360.0 ) + 360, 360.0 );
	$s = min( 1, max( 0, $s ) );
	$l = min( 1, max( 0, $l ) );
	$c = ( 1 - abs( 2 * $l - 1 ) ) * $s;
	$x = $c * ( 1 - abs( fmod( $h / 60, 2.0 ) - 1 ) );
	$m = $l - $c / 2;
	if ( $h < 60 ) { $rgb = array( $c, $x, 0 ); }
	elseif ( $h < 120 ) { $rgb = array( $x, $c, 0 ); }
	elseif ( $h < 180 ) { $rgb = array( 0, $c, $x ); }
	elseif ( $h < 240 ) { $rgb = array( 0, $x, $c ); }
	elseif ( $h < 300 ) { $rgb = array( $x, 0, $c ); }
	else { $rgb = array( $c, 0, $x ); }
	$out = '#';
	foreach ( $rgb as $v ) {
		$out .= str_pad( dechex( (int) round( ( $v + $m ) * 255 ) ), 2, '0', STR_PAD_LEFT );
	}
	return $out;
}

/**
 * Derive a full palette from a base + secondary color pair. The base drives
 * the background, shadow, and main streak tones; the secondary supplies the
 * glow and accent. Dark mode paints light blobs (screen-blended) over a
 * near-black base; light mode paints deeper tones (multiply-blended) over a
 * near-white base. Mirrors derivePalette() in the control JS.
 *
 * @param string $hex  Base color.
 * @param string $hex2 Secondary color.
 * @param string $mode 'dark' or 'light'.
 * @return array { colors: string[5], back: string, shadow: string }
 */
function ollie_ui_helpers_cover_designer_derive_palette( $hex, $hex2, $mode = 'dark' ) {
	list( $h, $s )   = ollie_ui_helpers_cover_designer_hex_to_hsl( $hex );
	list( $h2, $s2 ) = ollie_ui_helpers_cover_designer_hex_to_hsl( $hex2 );

	// Near-gray input means a deliberately monochrome brand — keep it.
	$se  = $s < 0.12 ? $s : min( 1, max( $s, 0.35 ) );
	$se2 = $s2 < 0.12 ? $s2 : min( 1, max( $s2, 0.35 ) );

	if ( 'light' === $mode ) {
		return array(
			'colors' => array(
				ollie_ui_helpers_cover_designer_hsl_to_hex( $h, $se * 0.85, 0.88 ),
				ollie_ui_helpers_cover_designer_hsl_to_hex( $h, $se * 0.90, 0.72 ),
				ollie_ui_helpers_cover_designer_hsl_to_hex( $h2, $se2 * 0.80, 0.68 ),
				ollie_ui_helpers_cover_designer_hsl_to_hex( $h + 12, $se * 0.70, 0.55 ),
				ollie_ui_helpers_cover_designer_hsl_to_hex( $h2, $se2 * 0.75, 0.62 ),
			),
			'back'   => ollie_ui_helpers_cover_designer_hsl_to_hex( $h, $se * 0.35, 0.97 ),
			'shadow' => ollie_ui_helpers_cover_designer_hsl_to_hex( $h, $se * 0.50, 0.90 ),
		);
	}

	return array(
		'colors' => array(
			ollie_ui_helpers_cover_designer_hsl_to_hex( $h, $se * 0.85, 0.24 ),
			ollie_ui_helpers_cover_designer_hsl_to_hex( $h, $se * 0.90, 0.47 ),
			ollie_ui_helpers_cover_designer_hsl_to_hex( $h2, $se2 * 0.80, 0.62 ),
			ollie_ui_helpers_cover_designer_hsl_to_hex( $h + 12, $se * 0.70, 0.94 ),
			ollie_ui_helpers_cover_designer_hsl_to_hex( $h2, $se2 * 0.75, 0.82 ),
		),
		'back'   => ollie_ui_helpers_cover_designer_hsl_to_hex( $h, $se * 0.60, 0.04 ),
		'shadow' => ollie_ui_helpers_cover_designer_hsl_to_hex( $h, $se * 0.65, 0.13 ),
	);
}

/**
 * Texture overlay defaults (the `ollieTexture` attribute). Mirrors
 * TEXTURE_BASE in the control JS.
 *
 * @return array
 */
function ollie_ui_helpers_cover_designer_texture_defaults() {
	return array(
		'style'        => 'dots',
		'contrast'     => 'auto',
		'autoMarks'    => 'light',
		'dotsStrength' => 0.5,
		'dotSize'      => 1.5,
		'dotGap'       => 20,
		'gridStrength' => 0.35,
		'lineWidth'    => 0.5,
		'lineGap'      => 30,
		'grainStrength' => 0.5,
		'textureScale' => 1.75,
		'htStrength'   => 0.5,
		'htSize'       => 1.25,
		'htGap'        => 0.5,
		'htSeed'       => 20260801,
	);
}

/**
 * Whitelist and clamp the `ollieTexture` attribute. Mirrors
 * normalizeTexture() in the control JS; `marks` resolves the contrast
 * choice to the ink color actually painted.
 *
 * @param array $texture Raw attribute.
 * @return array
 */
function ollie_ui_helpers_cover_designer_sanitize_texture( array $texture ) {
	$defaults = ollie_ui_helpers_cover_designer_texture_defaults();
	$clamps   = array(
		'dotsStrength' => array( 0, 1 ),
		'dotSize'      => array( 1, 10 ),
		'dotGap'       => array( 4, 30 ),
		'gridStrength' => array( 0, 1 ),
		'lineWidth'    => array( 0.5, 6 ),
		'lineGap'      => array( 6, 100 ),
		'grainStrength' => array( 0, 1 ),
		'textureScale' => array( 0.5, 3 ),
		'htStrength'   => array( 0, 1 ),
		'htSize'       => array( 0.05, 3 ),
		'htGap'        => array( 0.25, 5 ),
	);

	$out = array();
	foreach ( $clamps as $key => $range ) {
		$value       = isset( $texture[ $key ] ) && is_numeric( $texture[ $key ] )
			? (float) $texture[ $key ]
			: (float) $defaults[ $key ];
		$out[ $key ] = max( $range[0], min( $range[1], $value ) );
	}

	$out['style'] = ( isset( $texture['style'] ) && in_array( $texture['style'], array( 'grid', 'grain', 'halftone' ), true ) )
		? $texture['style']
		: 'dots';

	$out['contrast']  = ( isset( $texture['contrast'] ) && in_array( $texture['contrast'], array( 'light', 'dark' ), true ) )
		? $texture['contrast']
		: 'auto';
	$out['autoMarks'] = ( isset( $texture['autoMarks'] ) && 'dark' === $texture['autoMarks'] ) ? 'dark' : 'light';
	$out['marks']     = 'auto' === $out['contrast'] ? $out['autoMarks'] : $out['contrast'];

	$out['htSeed'] = ( isset( $texture['htSeed'] ) && is_numeric( $texture['htSeed'] ) )
		? max( 1, min( 2147483646, (int) $texture['htSeed'] ) )
		: $defaults['htSeed'];

	$strength_keys   = array(
		'dots'     => 'dotsStrength',
		'grid'     => 'gridStrength',
		'grain'    => 'grainStrength',
		'halftone' => 'htStrength',
	);
	$out['strength'] = $out[ $strength_keys[ $out['style'] ] ];

	return $out;
}

/**
 * Render the standalone texture overlay. Mirrors paintTextureHost() in the
 * control JS — keep the class/style construction in sync.
 *
 * @param array $s Sanitized texture settings.
 * @return string
 */
function ollie_ui_helpers_cover_designer_render_texture( array $s ) {
	return sprintf(
		'<div class="ollie-cover-texture is-%s%s" aria-hidden="true" style="--ocd-noise-o:%.2F;--ocd-noise-s:%.2F;--ocd-dot:%.1Fpx;--ocd-dot-gap:%.0Fpx;--ocd-line:%.1Fpx;--ocd-line-gap:%.0Fpx">%s</div>',
		$s['style'],
		'dark' === $s['marks'] ? ' is-marks-dark' : '',
		$s['strength'],
		$s['textureScale'],
		$s['dotSize'],
		$s['dotGap'],
		$s['lineWidth'],
		$s['lineGap'],
		'halftone' === $s['style'] ? ollie_ui_helpers_cover_designer_halftone_svg( $s ) : ''
	);
}

/**
 * Halftone texture: a hex-packed grid of dots whose radii follow a seeded
 * density field, rotated like a print screen. Mirrors halftoneSvg() in the
 * editor script — identical PRNG call order; keep the two in sync.
 *
 * @param array $s Sanitized settings.
 * @return string
 */
function ollie_ui_helpers_cover_designer_halftone_svg( array $s ) {
	$state = $s['htSeed'];
	$rand  = static function () use ( &$state ) {
		$state = ( $state * 16807 ) % 2147483647;
		return ( $state - 1 ) / 2147483646;
	};

	$cell  = 0.6 + $s['htGap'] * 0.32;
	$max_r = $cell * 0.25 * $s['htSize'];
	// Explicit round-half-away-from-zero formatting so this mirrors the
	// editor JS exactly (Math.round, toFixed, and printf all treat ties
	// differently).
	$fmt2 = static function ( $v ) {
		$r = round( abs( $v ) * 100 ) / 100;
		return number_format( ( $v < 0 && 0.0 !== $r ) ? -$r : $r, 2, '.', '' );
	};

	// A few soft density blobs decide how big each dot swells.
	$fields = array();
	for ( $k = 0; $k < 4; $k++ ) {
		$fields[] = array(
			'cx' => $rand() * 140 - 20,
			'cy' => $rand() * 140 - 20,
			'r'  => 30 + $rand() * 40,
			'i'  => 0.7 + $rand() * 0.5,
		);
	}

	$circles = '';
	$row     = 0;
	for ( $y = -15; $y <= 115; $y += $cell, $row++ ) {
		$offset = $row % 2 ? $cell / 2 : 0;
		for ( $x = -15 + $offset; $x <= 115; $x += $cell ) {
			$density = 0;
			for ( $k = 0; $k < 4; $k++ ) {
				$dx       = $x - $fields[ $k ]['cx'];
				$dy       = $y - $fields[ $k ]['cy'];
				$d        = sqrt( $dx * $dx + $dy * $dy );
				$density += $fields[ $k ]['i'] * max( 0, 1 - $d / $fields[ $k ]['r'] );
			}
			$r = $max_r * min( 1, $density );
			if ( $r < $max_r * 0.12 || $r < 0.005 ) {
				continue;
			}
			$circles .= '<circle cx="' . $fmt2( $x ) . '" cy="' . $fmt2( $y ) . '" r="' . $fmt2( $r ) . '"/>';
		}
	}

	return '<svg viewBox="0 0 100 100" preserveAspectRatio="xMidYMid slice"><g transform="rotate(15 50 50)" fill="#fff">' . $circles . '</g></svg>';
}

/**
 * Find the '>' that closes the first real tag, skipping leading comments
 * and any '>' inside quoted attribute values (a raw strpos can land inside
 * an attribute like title="a > b" and corrupt the injected markup).
 *
 * @param string $html Block HTML.
 * @return int|false Byte offset of the closing '>' or false.
 */
function ollie_ui_helpers_cover_designer_first_tag_end( $html ) {
	$len = strlen( $html );
	$i   = 0;

	while ( $i < $len ) {
		if ( ctype_space( $html[ $i ] ) ) {
			$i++;
			continue;
		}
		if ( '<!--' === substr( $html, $i, 4 ) ) {
			$end = strpos( $html, '-->', $i + 4 );
			if ( false === $end ) {
				return false;
			}
			$i = $end + 3;
			continue;
		}
		break;
	}

	if ( $i >= $len || '<' !== $html[ $i ] ) {
		return false;
	}

	$quote = '';
	for ( ; $i < $len; $i++ ) {
		$ch = $html[ $i ];
		if ( '' !== $quote ) {
			if ( $ch === $quote ) {
				$quote = '';
			}
		} elseif ( '"' === $ch || "'" === $ch ) {
			$quote = $ch;
		} elseif ( '>' === $ch ) {
			return $i;
		}
	}

	return false;
}

/**
 * Render the Mesh variant: huge, heavily-blurred color fields pinned toward
 * the corners and edges. Reuses the blob markup and CSS — only the generated
 * geometry differs. Mirrors buildMesh() in the editor script — identical
 * PRNG call order; keep the two in sync.
 *
 * @param array $s Sanitized settings with derived colors.
 * @return string
 */
function ollie_ui_helpers_cover_designer_render_mesh( array $s ) {
	$state = $s['seed'];
	$rand  = static function () use ( &$state ) {
		$state = ( $state * 16807 ) % 2147483647;
		return ( $state - 1 ) / 2147483646;
	};

	$colors      = $s['colors'];
	// UI "Zoom" (stretch key) sizes the fields; UI "Stretch" (zoom key)
	// elongates them, area-preserving: height shrinks as width grows, so
	// fields smear into bands instead of just getting smaller. Both are
	// neutral (1.0) at 3 and 9.
	$field_scale = 0.55 + $s['stretch'] * 0.15;
	$flatten     = min( 1.3, max( 0.4, 9 / max( 1, $s['zoom'] ) ) );
	$blur        = (int) round( 14 + $s['softness'] * 16 );
	$speed       = max( 0.05, $s['speed'] );

	// Loose anchor per field; jitter keeps each seed's composition unique.
	$anchors   = array( array( 12, 15 ), array( 85, 12 ), array( 15, 80 ), array( 88, 85 ), array( 50, 45 ), array( 35, 60 ) );
	$color_idx = array( 1, 2, 4, 3, 0, 2 );

	$html = '';
	for ( $i = 0; $i < 6; $i++ ) {
		$cx = $anchors[ $i ][0] + ( $rand() * 24 - 12 );
		$cy = $anchors[ $i ][1] + ( $rand() * 24 - 12 );
		$w  = ( ( 55 + $rand() * 45 ) * $field_scale ) / sqrt( $flatten );
		$h  = $w * ( 0.7 + $rand() * 0.5 ) * $flatten;
		$op = 0.5 + $rand() * 0.35;
		$a  = -$s['rotation'] + $rand() * 30 - 15;
		$t  = ( 30 + $rand() * 30 ) / $speed;
		$dl = -( $rand() * $t );
		$dx = $rand() * 20 - 10;
		$dy = $rand() * 16 - 8;
		$ds = 0.94 + $rand() * 0.16;
		$ad = $rand() * 10 - 5;

		$style = sprintf(
			'left:%.2F%%;top:%.2F%%;width:%.2F%%;height:%.2F%%;--ocd-o:%.2F;--ocd-c:%s;--ocd-blur:%dpx;--ocd-a:%.1Fdeg;--ocd-ad:%.1Fdeg;--ocd-dx:%.1F%%;--ocd-dy:%.1F%%;--ocd-ds:%.3F;--ocd-t:%.1Fs;--ocd-dl:%.1Fs',
			$cx,
			$cy,
			$w,
			$h,
			$op,
			$colors[ $color_idx[ $i ] ],
			$blur,
			$a,
			$ad,
			$dx,
			$dy,
			$ds,
			$t,
			$dl
		);

		$html .= '<span class="ocd-blob" style="' . esc_attr( $style ) . '"></span>';
	}

	return $html;
}

/**
 * Render the Waves variant: layered full-width bezier bands. Mirrors
 * buildWaves() in the editor script — identical PRNG call order; keep the
 * two implementations in sync.
 *
 * @param array $s Sanitized settings with derived colors.
 * @return string
 */
function ollie_ui_helpers_cover_designer_render_waves( array $s ) {
	$state = $s['seed'];
	$rand  = static function () use ( &$state ) {
		$state = ( $state * 16807 ) % 2147483647;
		return ( $state - 1 ) / 2147483646;
	};

	$colors    = $s['colors'];
	$amp_scale = $s['stretch'] / 3;
	$spread    = 55 * min( 1.4, 3 / sqrt( max( 1, $s['zoom'] ) ) );
	$blur_base = 2.8 + ( $s['softness'] - 1 ) * 5;
	$speed     = max( 0.05, $s['speed'] );
	$tilt      = ( fmod( $s['rotation'], 360 ) / 360 ) * 16 - 8;

	// Back-to-front: deep fills, then bright ribbons riding the crests.
	$color_idx = array( 0, 1, 3, 2, 4, 1 );
	$xs        = array( -20, 15, 50, 85, 120 );

	$paths = '';
	for ( $i = 0; $i < 6; $i++ ) {
		$t         = $i / 5;
		$is_ribbon = ( 2 === $i || 4 === $i );
		$base_y    = 14 + $t * $spread + ( $rand() * 14 - 7 );
		$amp       = ( 6 + $rand() * 8 ) * $amp_scale;
		$sign      = $rand() < 0.5 ? -1 : 1;

		$ys = array();
		foreach ( $xs as $unused ) {
			$ys[] = $base_y + $sign * ( 0.35 + $rand() * 0.65 ) * $amp;
			$sign = -$sign;
		}

		$thickness = 7 + $rand() * 10;
		$op        = $is_ribbon ? 0.7 + $rand() * 0.25 : 0.35 + $rand() * 0.25;
		$t_anim    = ( 30 + $rand() * 30 ) / $speed;
		$dl        = -( $rand() * $t_anim );
		$dx        = $rand() * 10 - 5;
		$dy        = $rand() * 6 - 3;

		// Horizontal-tangent cubics through the sample points: a smooth swell.
		$d = 'M' . $xs[0] . ' ' . sprintf( '%.1F', $ys[0] );
		for ( $j = 1; $j < 5; $j++ ) {
			$d .= sprintf(
				'C%s %.1F %s %.1F %s %.1F',
				$xs[ $j - 1 ] + 17.5,
				$ys[ $j - 1 ],
				$xs[ $j ] - 17.5,
				$ys[ $j ],
				$xs[ $j ],
				$ys[ $j ]
			);
		}
		if ( $is_ribbon ) {
			$d .= sprintf( 'L120 %.1F', $ys[4] + $thickness );
			for ( $j = 3; $j >= 0; $j-- ) {
				$d .= sprintf(
					'C%s %.1F %s %.1F %s %.1F',
					$xs[ $j + 1 ] - 17.5,
					$ys[ $j + 1 ] + $thickness,
					$xs[ $j ] + 17.5,
					$ys[ $j ] + $thickness,
					$xs[ $j ],
					$ys[ $j ] + $thickness
				);
			}
			$d .= 'Z';
		} else {
			$d .= 'L120 140L-20 140Z';
		}

		$blur = max( 2.8, $is_ribbon ? $blur_base * 0.5 : $blur_base * ( 1.6 - 0.8 * $t ) );

		$style = sprintf(
			'--ocd-o:%.2F;--ocd-blur:%.1Fpx;--ocd-t:%.1Fs;--ocd-dl:%.1Fs;--ocd-dx:%.1F%%;--ocd-dy:%.1F%%',
			$op,
			$blur,
			$t_anim,
			$dl,
			$dx,
			$dy
		);

		$paths .= '<path class="ocd-wave" d="' . esc_attr( $d ) . '" fill="' . esc_attr( $colors[ $color_idx[ $i ] ] ) . '" style="' . esc_attr( $style ) . '"></path>';
	}

	return '<svg class="ocd-waves" viewBox="-20 -20 140 140" preserveAspectRatio="none" style="' . esc_attr( sprintf( '--ocd-tilt:%.1Fdeg', $tilt ) ) . '">' . $paths . '</svg>';
}

/**
 * Deterministic blob layout (mirror of buildBlobs() in the control JS).
 *
 * @param array $s Sanitized settings including derived 'colors'.
 * @return string Blob markup.
 */
function ollie_ui_helpers_cover_designer_render_blobs( array $s ) {
	$state = $s['seed'];
	$rand  = static function () use ( &$state ) {
		$state = ( $state * 16807 ) % 2147483647;
		return ( $state - 1 ) / 2147483646;
	};

	$colors     = $s['colors'];
	$ncols      = count( $colors );
	// UI "Zoom" (stretch key) sizes the glows; UI "Stretch" (zoom key)
	// elongates the streaks, area-preserving: they grow longer as they
	// thin, instead of just shrinking. Both are neutral (1.0) at 3 and 9.
	$size_scale = 0.55 + $s['stretch'] * 0.15;
	$elong      = max( 0.6, $s['zoom'] / 3 );
	$streak_len = sqrt( $elong / 3 );
	$blur       = (int) round( 6 + $s['softness'] * 26 );
	$speed      = max( 0.05, $s['speed'] );

	$html = '';
	for ( $i = 0; $i < 9; $i++ ) {
		$is_streak = $i >= 3;
		$light     = min( $ncols - 1, 2 + (int) floor( $rand() * ( $ncols - 2 ) ) );
		$mid       = min( $ncols - 1, 1 + (int) floor( $rand() * ( $ncols - 2 ) ) );
		$color     = $colors[ $is_streak ? $light : $mid ];
		$cx        = 8 + fmod( $i * 0.7548 + $rand() * 0.4, 1.0 ) * 84;
		$cy        = 8 + fmod( $i * 0.5698 + $rand() * 0.4, 1.0 ) * 84;

		if ( $is_streak ) {
			$w  = ( 50 + $rand() * 45 ) * $size_scale * $streak_len;
			$h  = $w / ( $elong * ( 1.6 + $rand() * 1.4 ) );
			$op = 0.65 + $rand() * 0.3;
		} else {
			$w  = ( 45 + $rand() * 30 ) * $size_scale;
			$h  = $w * ( 0.55 + $rand() * 0.35 );
			$op = 0.4 + $rand() * 0.25;
		}

		$a  = -$s['rotation'] + $rand() * 24 - 12;
		$t  = ( 26 + $rand() * 34 ) / $speed;
		$dl = -( $rand() * $t );
		$dx = $rand() * 30 - 15;
		$dy = $rand() * 24 - 12;
		$ds = 0.92 + $rand() * 0.22;
		$ad = $rand() * 14 - 7;

		$style = sprintf(
			'left:%.2F%%;top:%.2F%%;width:%.2F%%;height:%.2F%%;--ocd-o:%.2F;--ocd-c:%s;--ocd-blur:%dpx;--ocd-a:%.1Fdeg;--ocd-ad:%.1Fdeg;--ocd-dx:%.1F%%;--ocd-dy:%.1F%%;--ocd-ds:%.3F;--ocd-t:%.1Fs;--ocd-dl:%.1Fs',
			$cx,
			$cy,
			$w,
			$h,
			$op,
			$color,
			$blur,
			$a,
			$ad,
			$dx,
			$dy,
			$ds,
			$t,
			$dl
		);

		$html .= '<span class="ocd-blob" style="' . esc_attr( $style ) . '"></span>';
	}

	return $html;
}

/**
 * Filter cover block content to inject the gradient layer on the frontend.
 *
 * @param string $block_content The block content.
 * @param array  $block The block data.
 * @return string Modified block content.
 */
function ollie_ui_helpers_cover_designer_render_block( $block_content, $block ) {
	// Only process cover blocks.
	if ( 'core/cover' !== $block['blockName'] ) {
		return $block_content;
	}

	$gradient = isset( $block['attrs']['ollieGradient'] ) ? $block['attrs']['ollieGradient'] : null;
	$texture  = isset( $block['attrs']['ollieTexture'] ) ? $block['attrs']['ollieTexture'] : null;

	$gradient_on = is_array( $gradient ) && ! empty( $gradient['enabled'] );
	$texture_on  = is_array( $texture ) && ! empty( $texture['enabled'] );

	if ( ! $gradient_on && ! $texture_on ) {
		return $block_content;
	}

	// Use WP_HTML_Tag_Processor to add the stacking classes.
	$processor = new WP_HTML_Tag_Processor( $block_content );
	if ( ! $processor->next_tag() ) {
		return $block_content;
	}
	if ( $gradient_on ) {
		$processor->add_class( 'has-ollie-gradient' );
	}
	if ( $texture_on ) {
		$processor->add_class( 'has-ollie-texture' );
	}
	$block_content = $processor->get_updated_html();

	$pos = ollie_ui_helpers_cover_designer_first_tag_end( $block_content );
	if ( false === $pos ) {
		return $block_content;
	}

	$inject = '';

	if ( $gradient_on ) {
		$settings           = ollie_ui_helpers_cover_designer_sanitize( $gradient );
		$palette            = ollie_ui_helpers_cover_designer_derive_palette( $settings['color'], $settings['color2'], $settings['mode'] );
		$settings['colors'] = $palette['colors'];

		$animated = $settings['animate'] && $settings['speed'] > 0;

		$base_bg = sprintf(
			'linear-gradient(%ddeg, %s, %s 55%%, %s)',
			( (int) round( $settings['rotation'] ) + 180 ) % 360,
			$palette['back'],
			$palette['shadow'],
			$palette['back']
		);

		$inject .= sprintf(
			'<div class="ollie-cover-gradient%s" aria-hidden="true" style="background:%s;filter:saturate(%.2F);--ocd-fade:%.2F">%s</div>',
			( $animated ? ' is-animated' : '' ) . ( 'light' === $settings['mode'] ? ' is-light' : '' ),
			esc_attr( $base_bg ),
			$settings['saturation'],
			$settings['opacity'],
			( 'waves' === $settings['variant']
				? ollie_ui_helpers_cover_designer_render_waves( $settings )
				: ( 'mesh' === $settings['variant']
					? ollie_ui_helpers_cover_designer_render_mesh( $settings )
					: ollie_ui_helpers_cover_designer_render_blobs( $settings ) ) )
		);
	}

	if ( $texture_on ) {
		$inject .= ollie_ui_helpers_cover_designer_render_texture(
			ollie_ui_helpers_cover_designer_sanitize_texture( $texture )
		);
	}

	return substr( $block_content, 0, $pos + 1 ) . $inject . substr( $block_content, $pos + 1 );
}
add_filter( 'render_block_core/cover', 'ollie_ui_helpers_cover_designer_render_block', 10, 2 );
