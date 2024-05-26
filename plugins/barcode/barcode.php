<?php

/****************************************************************************\

barcode.php - Generate barcodes from a single PHP file. MIT license.

Copyright (c) 2016-2018 Kreative Software.

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in
all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL
THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER
DEALINGS IN THE SOFTWARE.

\****************************************************************************/

if (realpath(__FILE__) == realpath($_SERVER['SCRIPT_FILENAME'])) {
	if (isset($_POST['s']) && isset($_POST['d'])) {
		$generator = new barcode_generator();
		$format = (isset($_POST['f']) ? $_POST['f'] : 'png');
		$generator->output_image($format, $_POST['s'], $_POST['d'], $_POST);
		exit(0);
	}
	if (isset($_GET['s']) && isset($_GET['d'])) {
		$generator = new barcode_generator();
		$format = (isset($_GET['f']) ? $_GET['f'] : 'png');
		$generator->output_image($format, $_GET['s'], $_GET['d'], $_GET);
		exit(0);
	}
}

class barcode_generator {

	public function output_image($format, $symbology, $data, $options) {
		switch (strtolower(preg_replace('/[^A-Za-z0-9]/', '', $format))) {
			case 'png':
				header('Content-Type: image/png');
				$image = $this->render_image($symbology, $data, $options);
				imagepng($image);
				imagedestroy($image);
				break;
			case 'gif':
				header('Content-Type: image/gif');
				$image = $this->render_image($symbology, $data, $options);
				imagegif($image);
				imagedestroy($image);
				break;
			case 'jpg': case 'jpe': case 'jpeg':
				header('Content-Type: image/jpeg');
				$image = $this->render_image($symbology, $data, $options);
				imagejpeg($image);
				imagedestroy($image);
				break;
			case 'svg':
				header('Content-Type: image/svg+xml');
				echo $this->render_svg($symbology, $data, $options);
				break;
		}
	}

	public function render_image($symbology, $data, $options) {
		list($code, $widths, $width, $height, $x, $y, $w, $h) =
			$this->encode_and_calculate_size($symbology, $data, $options);
		$image = imagecreatetruecolor($width, $height);
		imagesavealpha($image, true);
		$bgcolor = (isset($options['bc']) ? $options['bc'] : 'FFF');
		$bgcolor = $this->allocate_color($image, $bgcolor);
		imagefill($image, 0, 0, $bgcolor);
		$colors = array(
			(isset($options['cs']) ? $options['cs'] : ''),
			(isset($options['cm']) ? $options['cm'] : '000'),
			(isset($options['c2']) ? $options['c2'] : 'F00'),
			(isset($options['c3']) ? $options['c3'] : 'FF0'),
			(isset($options['c4']) ? $options['c4'] : '0F0'),
			(isset($options['c5']) ? $options['c5'] : '0FF'),
			(isset($options['c6']) ? $options['c6'] : '00F'),
			(isset($options['c7']) ? $options['c7'] : 'F0F'),
			(isset($options['c8']) ? $options['c8'] : 'FFF'),
			(isset($options['c9']) ? $options['c9'] : '000'),
		);
		foreach ($colors as $i => $color) {
			$colors[$i] = $this->allocate_color($image, $color);
		}
		$this->dispatch_render_image(
			$image, $code, $x, $y, $w, $h, $colors, $widths, $options
		);
		return $image;
	}

	public function render_svg($symbology, $data, $options) {
		list($code, $widths, $width, $height, $x, $y, $w, $h) =
			$this->encode_and_calculate_size($symbology, $data, $options);
		$svg  = '<?xml version="1.0"?>';
		$svg .= '<svg xmlns="http://www.w3.org/2000/svg" version="1.1"';
		$svg .= ' width="' . $width . '" height="' . $height . '"';
		$svg .= ' viewBox="0 0 ' . $width . ' ' . $height . '"><g>';
		$bgcolor = (isset($options['bc']) ? $options['bc'] : 'white');
		if ($bgcolor) {
			$svg .= '<rect x="0" y="0"';
			$svg .= ' width="' . $width . '" height="' . $height . '"';
			$svg .= ' fill="' . htmlspecialchars($bgcolor) . '"/>';
		}
		$colors = array(
			(isset($options['cs']) ? $options['cs'] : ''),
			(isset($options['cm']) ? $options['cm'] : 'black'),
			(isset($options['c2']) ? $options['c2'] : '#FF0000'),
			(isset($options['c3']) ? $options['c3'] : '#FFFF00'),
			(isset($options['c4']) ? $options['c4'] : '#00FF00'),
			(isset($options['c5']) ? $options['c5'] : '#00FFFF'),
			(isset($options['c6']) ? $options['c6'] : '#0000FF'),
			(isset($options['c7']) ? $options['c7'] : '#FF00FF'),
			(isset($options['c8']) ? $options['c8'] : 'white'),
			(isset($options['c9']) ? $options['c9'] : 'black'),
		);
		$svg .= $this->dispatch_render_svg(
			$code, $x, $y, $w, $h, $colors, $widths, $options
		);
		$svg .= '</g></svg>';
		return $svg;
	}

	/* - - - - INTERNAL FUNCTIONS - - - - */

	private function encode_and_calculate_size($symbology, $data, $options) {
		$code = $this->dispatch_encode($symbology, $data, $options);
		$widths = array(
			(isset($options['wq']) ? (int)$options['wq'] : 1),
			(isset($options['wm']) ? (int)$options['wm'] : 1),
			(isset($options['ww']) ? (int)$options['ww'] : 3),
			(isset($options['wn']) ? (int)$options['wn'] : 1),
			(isset($options['w4']) ? (int)$options['w4'] : 1),
			(isset($options['w5']) ? (int)$options['w5'] : 1),
			(isset($options['w6']) ? (int)$options['w6'] : 1),
			(isset($options['w7']) ? (int)$options['w7'] : 1),
			(isset($options['w8']) ? (int)$options['w8'] : 1),
			(isset($options['w9']) ? (int)$options['w9'] : 1),
		);
		$size = $this->dispatch_calculate_size($code, $widths, $options);
		$dscale = ($code && isset($code['g']) && $code['g'] == 'm') ? 4 : 1;
		$scale = (isset($options['sf']) ? (float)$options['sf'] : $dscale);
		$scalex = (isset($options['sx']) ? (float)$options['sx'] : $scale);
		$scaley = (isset($options['sy']) ? (float)$options['sy'] : $scale);
		$dpadding = ($code && isset($code['g']) && $code['g'] == 'm') ? 0 : 10;
		$padding = (isset($options['p']) ? (int)$options['p'] : $dpadding);
		$vert = (isset($options['pv']) ? (int)$options['pv'] : $padding);
		$horiz = (isset($options['ph']) ? (int)$options['ph'] : $padding);
		$top = (isset($options['pt']) ? (int)$options['pt'] : $vert);
		$left = (isset($options['pl']) ? (int)$options['pl'] : $horiz);
		$right = (isset($options['pr']) ? (int)$options['pr'] : $horiz);
		$bottom = (isset($options['pb']) ? (int)$options['pb'] : $vert);
		$dwidth = ceil($size[0] * $scalex) + $left + $right;
		$dheight = ceil($size[1] * $scaley) + $top + $bottom;
		$iwidth = (isset($options['w']) ? (int)$options['w'] : $dwidth);
		$iheight = (isset($options['h']) ? (int)$options['h'] : $dheight);
		$swidth = $iwidth - $left - $right;
		$sheight = $iheight - $top - $bottom;
		return array(
			$code, $widths, $iwidth, $iheight,
			$left, $top, $swidth, $sheight
		);
	}

	private function allocate_color($image, $color) {
		$color = preg_replace('/[^0-9A-Fa-f]/', '', $color);
		switch (strlen($color)) {
			case 1:
				$v = hexdec($color) * 17;
				return imagecolorallocate($image, $v, $v, $v);
			case 2:
				$v = hexdec($color);
				return imagecolorallocate($image, $v, $v, $v);
			case 3:
				$r = hexdec(substr($color, 0, 1)) * 17;
				$g = hexdec(substr($color, 1, 1)) * 17;
				$b = hexdec(substr($color, 2, 1)) * 17;
				return imagecolorallocate($image, $r, $g, $b);
			case 4:
				$a = hexdec(substr($color, 0, 1)) * 17;
				$r = hexdec(substr($color, 1, 1)) * 17;
				$g = hexdec(substr($color, 2, 1)) * 17;
				$b = hexdec(substr($color, 3, 1)) * 17;
				$a = round((255 - $a) * 127 / 255);
				return imagecolorallocatealpha($image, $r, $g, $b, $a);
			case 6:
				$r = hexdec(substr($color, 0, 2));
				$g = hexdec(substr($color, 2, 2));
				$b = hexdec(substr($color, 4, 2));
				return imagecolorallocate($image, $r, $g, $b);
			case 8:
				$a = hexdec(substr($color, 0, 2));
				$r = hexdec(substr($color, 2, 2));
				$g = hexdec(substr($color, 4, 2));
				$b = hexdec(substr($color, 6, 2));
				$a = round((255 - $a) * 127 / 255);
				return imagecolorallocatealpha($image, $r, $g, $b, $a);
			default:
				return imagecolorallocatealpha($image, 0, 0, 0, 127);
		}
	}

	/* - - - - DISPATCH - - - - */

	private function dispatch_encode($symbology, $data, $options) {
		switch (strtolower(preg_replace('/[^A-Za-z0-9]/', '', $symbology))) {
			case 'upca'       : return $this->upc_a_encode($data);
			case 'upce'       : return $this->upc_e_encode($data);
			case 'ean13nopad' : return $this->ean_13_encode($data, ' ');
			case 'ean13pad'   : return $this->ean_13_encode($data, '>');
			case 'ean13'      : return $this->ean_13_encode($data, '>');
			case 'ean8'       : return $this->ean_8_encode($data);
			case 'code39'     : return $this->code_39_encode($data);
			case 'code39ascii': return $this->code_39_ascii_encode($data);
			case 'code93'     : return $this->code_93_encode($data);
			case 'code93ascii': return $this->code_93_ascii_encode($data);
			case 'code128'    : return $this->code_128_encode($data, 0,false);
			case 'code128a'   : return $this->code_128_encode($data, 1,false);
			case 'code128b'   : return $this->code_128_encode($data, 2,false);
			case 'code128c'   : return $this->code_128_encode($data, 3,false);
			case 'code128ac'  : return $this->code_128_encode($data,-1,false);
			case 'code128bc'  : return $this->code_128_encode($data,-2,false);
			case 'ean128'     : return $this->code_128_encode($data, 0, true);
			case 'ean128a'    : return $this->code_128_encode($data, 1, true);
			case 'ean128b'    : return $this->code_128_encode($data, 2, true);
			case 'ean128c'    : return $this->code_128_encode($data, 3, true);
			case 'ean128ac'   : return $this->code_128_encode($data,-1, true);
			case 'ean128bc'   : return $this->code_128_encode($data,-2, true);
			case 'codabar'    : return $this->codabar_encode($data);
			case 'itf'        : return $this->itf_encode($data);
			case 'itf14'      : return $this->itf_encode($data);
			case 'qr'         : return $this->qr_encode($data, 0);
			case 'qrl'        : return $this->qr_encode($data, 0);
			case 'qrm'        : return $this->qr_encode($data, 1);
			case 'qrq'        : return $this->qr_encode($data, 2);
			case 'qrh'        : return $this->qr_encode($data, 3);
			case 'dmtx'       : return $this->dmtx_encode($data,false,false);
			case 'dmtxs'      : return $this->dmtx_encode($data,false,false);
			case 'dmtxr'      : return $this->dmtx_encode($data, true,false);
			case 'gs1dmtx'    : return $this->dmtx_encode($data,false, true);
			case 'gs1dmtxs'   : return $this->dmtx_encode($data,false, true);
			case 'gs1dmtxr'   : return $this->dmtx_encode($data, true, true);
		}
		return null;
	}

	private function dispatch_calculate_size($code, $widths, $options) {
		if ($code && isset($code['g']) && $code['g']) {
			switch ($code['g']) {
				case 'l':
					return $this->linear_calculate_size($code, $widths);
				case 'm':
					return $this->matrix_calculate_size($code, $widths);
			}
		}
		return array(0, 0);
	}

	private function dispatch_render_image(
		$image, $code, $x, $y, $w, $h, $colors, $widths, $options
	) {
		if ($code && isset($code['g']) && $code['g']) {
			switch ($code['g']) {
				case 'l':
					$this->linear_render_image(
						$image, $code, $x, $y, $w, $h,
						$colors, $widths, $options
					);
					break;
				case 'm':
					$this->matrix_render_image(
						$image, $code, $x, $y, $w, $h,
						$colors, $widths, $options
					);
					break;
			}
		}
	}

	private function dispatch_render_svg(
		$code, $x, $y, $w, $h, $colors, $widths, $options
	) {
		if ($code && isset($code['g']) && $code['g']) {
			switch ($code['g']) {
				case 'l':
					return $this->linear_render_svg(
						$code, $x, $y, $w, $h,
						$colors, $widths, $options
					);
				case 'm':
					return $this->matrix_render_svg(
						$code, $x, $y, $w, $h,
						$colors, $widths, $options
					);
			}
		}
		return '';
	}

	/* - - - - LINEAR BARCODE RENDERER - - - - */

	private function linear_calculate_size($code, $widths) {
		$width = 0;
		foreach ($code['b'] as $block) {
			foreach ($block['m'] as $module) {
				$width += $module[1] * $widths[$module[2]];
			}
		}
		return array($width, 80);
	}

	private function linear_render_image(
		$image, $code, $x, $y, $w, $h, $colors, $widths, $options
	) {
		$textheight = (isset($options['th']) ? (int)$options['th'] : 10);
		$textsize = (isset($options['ts']) ? (int)$options['ts'] : 1);
		$textcolor = (isset($options['tc']) ? $options['tc'] : '000');
		$textcolor = $this->allocate_color($image, $textcolor);
		$width = 0;
		foreach ($code['b'] as $block) {
			foreach ($block['m'] as $module) {
				$width += $module[1] * $widths[$module[2]];
			}
		}
		if ($width) {
			$scale = $w / $width;
			$scale = (($scale > 1) ? floor($scale) : 1);
			$x = floor($x + ($w - $width * $scale) / 2);
		} else {
			$scale = 1;
			$x = floor($x + $w / 2);
		}
		foreach ($code['b'] as $block) {
			if (isset($block['l'])) {
				$label = $block['l'][0];
				$ly = (isset($block['l'][1]) ? (float)$block['l'][1] : 1);
				$lx = (isset($block['l'][2]) ? (float)$block['l'][2] : 0.5);
				$my = round($y + min($h, $h + ($ly - 1) * $textheight));
				$ly = ($y + $h + $ly * $textheight);
				$ly = round($ly - imagefontheight($textsize));
			} else {
				$label = null;
				$my = $y + $h;
			}
			$mx = $x;
			foreach ($block['m'] as $module) {
				$mc = $colors[$module[0]];
				$mw = $mx + $module[1] * $widths[$module[2]] * $scale;
				imagefilledrectangle($image, $mx, $y, $mw - 1, $my - 1, $mc);
				$mx = $mw;
			}
			if (!is_null($label)) {
				$lx = ($x + ($mx - $x) * $lx);
				$lw = imagefontwidth($textsize) * strlen($label);
				$lx = round($lx - $lw / 2);
				imagestring($image, $textsize, $lx, $ly, $label, $textcolor);
			}
			$x = $mx;
		}
	}

	private function linear_render_svg(
		$code, $x, $y, $w, $h, $colors, $widths, $options
	) {
		$textheight = (isset($options['th']) ? (int)$options['th'] : 10);
		$textfont = (isset($options['tf']) ? $options['tf'] : 'monospace');
		$textsize = (isset($options['ts']) ? (int)$options['ts'] : 10);
		$textcolor = (isset($options['tc']) ? $options['tc'] : 'black');
		$width = 0;
		foreach ($code['b'] as $block) {
			foreach ($block['m'] as $module) {
				$width += $module[1] * $widths[$module[2]];
			}
		}
		if ($width) {
			$scale = $w / $width;
			if ($scale > 1) {
				$scale = floor($scale);
				$x = floor($x + ($w - $width * $scale) / 2);
			}
		} else {
			$scale = 1;
			$x = floor($x + $w / 2);
		}
		$tx = 'translate(' . $x . ' ' . $y . ')';
		if ($scale != 1) $tx .= ' scale(' . $scale . ' 1)';
		$svg = '<g transform="' . htmlspecialchars($tx) . '">';
		$x = 0;
		foreach ($code['b'] as $block) {
			if (isset($block['l'])) {
				$label = $block['l'][0];
				$ly = (isset($block['l'][1]) ? (float)$block['l'][1] : 1);
				$lx = (isset($block['l'][2]) ? (float)$block['l'][2] : 0.5);
				$mh = min($h, $h + ($ly - 1) * $textheight);
				$ly = $h + $ly * $textheight;
			} else {
				$label = null;
				$mh = $h;
			}
			$svg .= '<g>';
			$mx = $x;
			foreach ($block['m'] as $module) {
				$mc = htmlspecialchars($colors[$module[0]]);
				$mw = $module[1] * $widths[$module[2]];
				if ($mc) {
					$svg .= '<rect';
					$svg .= ' x="' . $mx . '" y="0"';
					$svg .= ' width="' . $mw . '"';
					$svg .= ' height="' . $mh . '"';
					$svg .= ' fill="' . $mc . '"/>';
				}
				$mx += $mw;
			}
			if (!is_null($label)) {
				$lx = ($x + ($mx - $x) * $lx);
				$svg .= '<text';
				$svg .= ' x="' . $lx . '" y="' . $ly . '"';
				$svg .= ' text-anchor="middle"';
				$svg .= ' font-family="'.htmlspecialchars($textfont).'"';
				$svg .= ' font-size="'.htmlspecialchars($textsize).'"';
				$svg .= ' fill="'.htmlspecialchars($textcolor).'">';
				$svg .= htmlspecialchars($label);
				$svg .= '</text>';
			}
			$svg .= '</g>';
			$x = $mx;
		}
		return $svg . '</g>';
	}

	/* - - - - MATRIX BARCODE RENDERER - - - - */

	private function matrix_calculate_size($code, $widths) {
		$width = (
			$code['q'][3] * $widths[0] +
			$code['s'][0] * $widths[1] +
			$code['q'][1] * $widths[0]
		);
		$height = (
			$code['q'][0] * $widths[0] +
			$code['s'][1] * $widths[1] +
			$code['q'][2] * $widths[0]
		);
		return array($width, $height);
	}

	private function matrix_render_image(
		$image, $code, $x, $y, $w, $h, $colors, $widths, $options
	) {
		$shape = (isset($options['ms']) ? strtolower($options['ms']) : '');
		$density = (isset($options['md']) ? (float)$options['md'] : 1);
		list($width, $height) = $this->matrix_calculate_size($code, $widths);
		if ($width && $height) {
			$scale = min($w / $width, $h / $height);
			$scale = (($scale > 1) ? floor($scale) : 1);
			$x = floor($x + ($w - $width * $scale) / 2);
			$y = floor($y + ($h - $height * $scale) / 2);
		} else {
			$scale = 1;
			$x = floor($x + $w / 2);
			$y = floor($y + $h / 2);
		}
		$x += $code['q'][3] * $widths[0] * $scale;
		$y += $code['q'][0] * $widths[0] * $scale;
		$wh = $widths[1] * $scale;
		foreach ($code['b'] as $by => $row) {
			$y1 = $y + $by * $wh;
			foreach ($row as $bx => $color) {
				$x1 = $x + $bx * $wh;
				$mc = $colors[$color];
				$this->matrix_dot_image(
					$image, $x1, $y1, $wh, $wh, $mc, $shape, $density
				);
			}
		}
	}

	private function matrix_render_svg(
		$code, $x, $y, $w, $h, $colors, $widths, $options
	) {
		$shape = (isset($options['ms']) ? strtolower($options['ms']) : '');
		$density = (isset($options['md']) ? (float)$options['md'] : 1);
		list($width, $height) = $this->matrix_calculate_size($code, $widths);
		if ($width && $height) {
			$scale = min($w / $width, $h / $height);
			if ($scale > 1) $scale = floor($scale);
			$x = floor($x + ($w - $width * $scale) / 2);
			$y = floor($y + ($h - $height * $scale) / 2);
		} else {
			$scale = 1;
			$x = floor($x + $w / 2);
			$y = floor($y + $h / 2);
		}
		$tx = 'translate(' . $x . ' ' . $y . ')';
		if ($scale != 1) $tx .= ' scale(' . $scale . ' ' . $scale . ')';
		$svg = '<g transform="' . htmlspecialchars($tx) . '">';
		$x = $code['q'][3] * $widths[0];
		$y = $code['q'][0] * $widths[0];
		$wh = $widths[1];
		foreach ($code['b'] as $by => $row) {
			$y1 = $y + $by * $wh;
			foreach ($row as $bx => $color) {
				$x1 = $x + $bx * $wh;
				$mc = $colors[$color];
				if ($mc) {
					$svg .= $this->matrix_dot_svg(
						$x1, $y1, $wh, $wh, $mc, $shape, $density
					);
				}
			}
		}
		return $svg . '</g>';
	}

	private function matrix_dot_image($image, $x, $y, $w, $h, $mc, $ms, $md) {
		switch ($ms) {
			default:
				$x = floor($x + (1 - $md) * $w / 2);
				$y = floor($y + (1 - $md) * $h / 2);
				$w = ceil($w * $md);
				$h = ceil($h * $md);
				imagefilledrectangle($image, $x, $y, $x+$w-1, $y+$h-1, $mc);
				break;
			case 'r':
				$cx = floor($x + $w / 2);
				$cy = floor($y + $h / 2);
				$dx = ceil($w * $md);
				$dy = ceil($h * $md);
				imagefilledellipse($image, $cx, $cy, $dx, $dy, $mc);
				break;
			case 'x':
				$x = floor($x + (1 - $md) * $w / 2);
				$y = floor($y + (1 - $md) * $h / 2);
				$w = ceil($w * $md);
				$h = ceil($h * $md);
				imageline($image, $x, $y, $x+$w-1, $y+$h-1, $mc);
				imageline($image, $x, $y+$h-1, $x+$w-1, $y, $mc);
				break;
		}
	}

	private function matrix_dot_svg($x, $y, $w, $h, $mc, $ms, $md) {
		switch ($ms) {
			default:
				$x += (1 - $md) * $w / 2;
				$y += (1 - $md) * $h / 2;
				$w *= $md;
				$h *= $md;
				$svg  = '<rect x="' . $x . '" y="' . $y . '"';
				$svg .= ' width="' . $w . '" height="' . $h . '"';
				$svg .= ' fill="' . $mc . '"/>';
				return $svg;
			case 'r':
				$cx = $x + $w / 2;
				$cy = $y + $h / 2;
				$rx = $w * $md / 2;
				$ry = $h * $md / 2;
				$svg  = '<ellipse cx="' . $cx . '" cy="' . $cy . '"';
				$svg .= ' rx="' . $rx . '" ry="' . $ry . '"';
				$svg .= ' fill="' . $mc . '"/>';
				return $svg;
			case 'x':
				$x1 = $x + (1 - $md) * $w / 2;
				$y1 = $y + (1 - $md) * $h / 2;
				$x2 = $x + $w - (1 - $md) * $w / 2;
				$y2 = $y + $h - (1 - $md) * $h / 2;
				$svg  = '<line x1="' . $x1 . '" y1="' . $y1 . '"';
				$svg .= ' x2="' . $x2 . '" y2="' . $y2 . '"';
				$svg .= ' stroke="' . $mc . '"';
				$svg .= ' stroke-width="' . ($md / 5) . '"/>';
				$svg .= '<line x1="' . $x1 . '" y1="' . $y2 . '"';
				$svg .= ' x2="' . $x2 . '" y2="' . $y1 . '"';
				$svg .= ' stroke="' . $mc . '"';
				$svg .= ' stroke-width="' . ($md / 5) . '"/>';
				return '<g>' . $svg . '</g>';
		}
	}

	/* - - - - UPC FAMILY ENCODER - - - - */

	private function upc_a_encode($data) {
		$data = $this->upc_a_normalize($data);
		$blocks = array();
		/* Quiet zone, start, first digit. */
		$digit = substr($data, 0, 1);
		$blocks[] = array(
			'm' => array(array(0, 9, 0)),
			'l' => array($digit, 0, 1/3)
		);
		$blocks[] = array(
			'm' => array(
				array(1, 1, 1),
				array(0, 1, 1),
				array(1, 1, 1),
			)
		);
		$blocks[] = array(
			'm' => array(
				array(0, $this->upc_alphabet[$digit][0], 1),
				array(1, $this->upc_alphabet[$digit][1], 1),
				array(0, $this->upc_alphabet[$digit][2], 1),
				array(1, $this->upc_alphabet[$digit][3], 1),
			)
		);
		/* Left zone. */
		for ($i = 1; $i < 6; $i++) {
			$digit = substr($data, $i, 1);
			$blocks[] = array(
				'm' => array(
					array(0, $this->upc_alphabet[$digit][0], 1),
					array(1, $this->upc_alphabet[$digit][1], 1),
					array(0, $this->upc_alphabet[$digit][2], 1),
					array(1, $this->upc_alphabet[$digit][3], 1),
				),
				'l' => array($digit, 0.5, (6 - $i) / 6)
			);
		}
		/* Middle. */
		$blocks[] = array(
			'm' => array(
				array(0, 1, 1),
				array(1, 1, 1),
				array(0, 1, 1),
				array(1, 1, 1),
				array(0, 1, 1),
			)
		);
		/* Right zone. */
		for ($i = 6; $i < 11; $i++) {
			$digit = substr($data, $i, 1);
			$blocks[] = array(
				'm' => array(
					array(1, $this->upc_alphabet[$digit][0], 1),
					array(0, $this->upc_alphabet[$digit][1], 1),
					array(1, $this->upc_alphabet[$digit][2], 1),
					array(0, $this->upc_alphabet[$digit][3], 1),
				),
				'l' => array($digit, 0.5, (11 - $i) / 6)
			);
		}
		/* Last digit, end, quiet zone. */
		$digit = substr($data, 11, 1);
		$blocks[] = array(
			'm' => array(
				array(1, $this->upc_alphabet[$digit][0], 1),
				array(0, $this->upc_alphabet[$digit][1], 1),
				array(1, $this->upc_alphabet[$digit][2], 1),
				array(0, $this->upc_alphabet[$digit][3], 1),
			)
		);
		$blocks[] = array(
			'm' => array(
				array(1, 1, 1),
				array(0, 1, 1),
				array(1, 1, 1),
			)
		);
		$blocks[] = array(
			'm' => array(array(0, 9, 0)),
			'l' => array($digit, 0, 2/3)
		);
		/* Return code. */
		return array('g' => 'l', 'b' => $blocks);
	}

	private function upc_e_encode($data) {
		$data = $this->upc_e_normalize($data);
		$blocks = array();
		/* Quiet zone, start. */
		$blocks[] = array(
			'm' => array(array(0, 9, 0))
		);
		$blocks[] = array(
			'm' => array(
				array(1, 1, 1),
				array(0, 1, 1),
				array(1, 1, 1),
			)
		);
		/* Digits */
		$system = substr($data, 0, 1) & 1;
		$check = substr($data, 7, 1);
		$pbits = $this->upc_parity[$check];
		for ($i = 1; $i < 7; $i++) {
			$digit = substr($data, $i, 1);
			$pbit = $pbits[$i - 1] ^ $system;
			$blocks[] = array(
				'm' => array(
					array(0, $this->upc_alphabet[$digit][$pbit ? 3 : 0], 1),
					array(1, $this->upc_alphabet[$digit][$pbit ? 2 : 1], 1),
					array(0, $this->upc_alphabet[$digit][$pbit ? 1 : 2], 1),
					array(1, $this->upc_alphabet[$digit][$pbit ? 0 : 3], 1),
				),
				'l' => array($digit, 0.5, (7 - $i) / 7)
			);
		}
		/* End, quiet zone. */
		$blocks[] = array(
			'm' => array(
				array(0, 1, 1),
				array(1, 1, 1),
				array(0, 1, 1),
				array(1, 1, 1),
				array(0, 1, 1),
				array(1, 1, 1),
			)
		);
		$blocks[] = array(
			'm' => array(array(0, 9, 0))
		);
		/* Return code. */
		return array('g' => 'l', 'b' => $blocks);
	}

	private function ean_13_encode($data, $pad) {
		$data = $this->ean_13_normalize($data);
		$blocks = array();
		/* Quiet zone, start, first digit (as parity). */
		$system = substr($data, 0, 1);
		$pbits = (
			(int)$system ?
			$this->upc_parity[$system] :
			array(1, 1, 1, 1, 1, 1)
		);
		$blocks[] = array(
			'm' => array(array(0, 9, 0)),
			'l' => array($system, 0.5, 1/3)
		);
		$blocks[] = array(
			'm' => array(
				array(1, 1, 1),
				array(0, 1, 1),
				array(1, 1, 1),
			)
		);
		/* Left zone. */
		for ($i = 1; $i < 7; $i++) {
			$digit = substr($data, $i, 1);
			$pbit = $pbits[$i - 1];
			$blocks[] = array(
				'm' => array(
					array(0, $this->upc_alphabet[$digit][$pbit ? 0 : 3], 1),
					array(1, $this->upc_alphabet[$digit][$pbit ? 1 : 2], 1),
					array(0, $this->upc_alphabet[$digit][$pbit ? 2 : 1], 1),
					array(1, $this->upc_alphabet[$digit][$pbit ? 3 : 0], 1),
				),
				'l' => array($digit, 0.5, (7 - $i) / 7)
			);
		}
		/* Middle. */
		$blocks[] = array(
			'm' => array(
				array(0, 1, 1),
				array(1, 1, 1),
				array(0, 1, 1),
				array(1, 1, 1),
				array(0, 1, 1),
			)
		);
		/* Right zone. */
		for ($i = 7; $i < 13; $i++) {
			$digit = substr($data, $i, 1);
			$blocks[] = array(
				'm' => array(
					array(1, $this->upc_alphabet[$digit][0], 1),
					array(0, $this->upc_alphabet[$digit][1], 1),
					array(1, $this->upc_alphabet[$digit][2], 1),
					array(0, $this->upc_alphabet[$digit][3], 1),
				),
				'l' => array($digit, 0.5, (13 - $i) / 7)
			);
		}
		/* End, quiet zone. */
		$blocks[] = array(
			'm' => array(
				array(1, 1, 1),
				array(0, 1, 1),
				array(1, 1, 1),
			)
		);
		$blocks[] = array(
			'm' => array(array(0, 9, 0)),
			'l' => array($pad, 0.5, 2/3)
		);
		/* Return code. */
		return array('g' => 'l', 'b' => $blocks);
	}

	private function ean_8_encode($data) {
		$data = $this->ean_8_normalize($data);
		$blocks = array();
		/* Quiet zone, start. */
		$blocks[] = array(
			'm' => array(array(0, 9, 0)),
			'l' => array('<', 0.5, 1/3)
		);
		$blocks[] = array(
			'm' => array(
				array(1, 1, 1),
				array(0, 1, 1),
				array(1, 1, 1),
			)
		);
		/* Left zone. */
		for ($i = 0; $i < 4; $i++) {
			$digit = substr($data, $i, 1);
			$blocks[] = array(
				'm' => array(
					array(0, $this->upc_alphabet[$digit][0], 1),
					array(1, $this->upc_alphabet[$digit][1], 1),
					array(0, $this->upc_alphabet[$digit][2], 1),
					array(1, $this->upc_alphabet[$digit][3], 1),
				),
				'l' => array($digit, 0.5, (4 - $i) / 5)
			);
		}
		/* Middle. */
		$blocks[] = array(
			'm' => array(
				array(0, 1, 1),
				array(1, 1, 1),
				array(0, 1, 1),
				array(1, 1, 1),
				array(0, 1, 1),
			)
		);
		/* Right zone. */
		for ($i = 4; $i < 8; $i++) {
			$digit = substr($data, $i, 1);
			$blocks[] = array(
				'm' => array(
					array(1, $this->upc_alphabet[$digit][0], 1),
					array(0, $this->upc_alphabet[$digit][1], 1),
					array(1, $this->upc_alphabet[$digit][2], 1),
					array(0, $this->upc_alphabet[$digit][3], 1),
				),
				'l' => array($digit, 0.5, (8 - $i) / 5)
			);
		}
		/* End, quiet zone. */
		$blocks[] = array(
			'm' => array(
				array(1, 1, 1),
				array(0, 1, 1),
				array(1, 1, 1),
			)
		);
		$blocks[] = array(
			'm' => array(array(0, 9, 0)),
			'l' => array('>', 0.5, 2/3)
		);
		/* Return code. */
		return array('g' => 'l', 'b' => $blocks);
	}

	private function upc_a_normalize($data) {
		$data = preg_replace('/[^0-9*]/', '', $data);
		/* Set length to 12 digits. */
		if (strlen($data) < 5) {
			$data = str_repeat('0', 12);
		} else if (strlen($data) < 12) {
			$system = substr($data, 0, 1);
			$edata = substr($data, 1, -2);
			$epattern = (int)substr($data, -2, 1);
			$check = substr($data, -1);
			if ($epattern < 3) {
				$left = $system . substr($edata, 0, 2) . $epattern;
				$right = substr($edata, 2) . $check;
			} else if ($epattern < strlen($edata)) {
				$left = $system . substr($edata, 0, $epattern);
				$right = substr($edata, $epattern) . $check;
			} else {
				$left = $system . $edata;
				$right = $epattern . $check;
			}
			$center = str_repeat('0', 12 - strlen($left . $right));
			$data = $left . $center . $right;
		} else if (strlen($data) > 12) {
			$left = substr($data, 0, 6);
			$right = substr($data, -6);
			$data = $left . $right;
		}
		/* Replace * with missing or check digit. */
		while (($o = strrpos($data, '*')) !== false) {
			$checksum = 0;
			for ($i = 0; $i < 12; $i++) {
				$digit = substr($data, $i, 1);
				$checksum += (($i % 2) ? 1 : 3) * $digit;
			}
			$checksum *= (($o % 2) ? 9 : 3);
			$left = substr($data, 0, $o);
			$center = substr($checksum, -1);
			$right = substr($data, $o + 1);
			$data = $left . $center . $right;
		}
		return $data;
	}

	private function upc_e_normalize($data) {
		$data = preg_replace('/[^0-9*]/', '', $data);
		/* If exactly 8 digits, use verbatim even if check digit is wrong. */
		if (preg_match(
			'/^([01])([0-9][0-9][0-9][0-9][0-9][0-9])([0-9])$/',
			$data, $m
		)) {
			return $data;
		}
		/* If unknown check digit, use verbatim but calculate check digit. */
		if (preg_match(
			'/^([01])([0-9][0-9][0-9][0-9][0-9][0-9])([*])$/',
			$data, $m
		)) {
			$data = $this->upc_a_normalize($data);
			return $m[1] . $m[2] . substr($data, -1);
		}
		/* Otherwise normalize to UPC-A and convert back. */
		$data = $this->upc_a_normalize($data);
		if (preg_match(
			'/^([01])([0-9][0-9])([0-2])0000([0-9][0-9][0-9])([0-9])$/',
			$data, $m
		)) {
			return $m[1] . $m[2] . $m[4] . $m[3] . $m[5];
		}
		if (preg_match(
			'/^([01])([0-9][0-9][0-9])00000([0-9][0-9])([0-9])$/',
			$data, $m
		)) {
			return $m[1] . $m[2] . $m[3] . '3' . $m[4];
		}
		if (preg_match(
			'/^([01])([0-9][0-9][0-9][0-9])00000([0-9])([0-9])$/',
			$data, $m
		)) {
			return $m[1] . $m[2] . $m[3] . '4' . $m[4];
		}
		if (preg_match(
			'/^([01])([0-9][0-9][0-9][0-9][0-9])0000([5-9])([0-9])$/',
			$data, $m
		)) {
			return $m[1] . $m[2] . $m[3] . $m[4];
		}
		return str_repeat('0', 8);
	}

	private function ean_13_normalize($data) {
		$data = preg_replace('/[^0-9*]/', '', $data);
		/* Set length to 13 digits. */
		if (strlen($data) < 13) {
			return '0' . $this->upc_a_normalize($data);
		} else if (strlen($data) > 13) {
			$left = substr($data, 0, 7);
			$right = substr($data, -6);
			$data = $left . $right;
		}
		/* Replace * with missing or check digit. */
		while (($o = strrpos($data, '*')) !== false) {
			$checksum = 0;
			for ($i = 0; $i < 13; $i++) {
				$digit = substr($data, $i, 1);
				$checksum += (($i % 2) ? 3 : 1) * $digit;
			}
			$checksum *= (($o % 2) ? 3 : 9);
			$left = substr($data, 0, $o);
			$center = substr($checksum, -1);
			$right = substr($data, $o + 1);
			$data = $left . $center . $right;
		}
		return $data;
	}

	private function ean_8_normalize($data) {
		$data = preg_replace('/[^0-9*]/', '', $data);
		/* Set length to 8 digits. */
		if (strlen($data) < 8) {
			$midpoint = floor(strlen($data) / 2);
			$left = substr($data, 0, $midpoint);
			$center = str_repeat('0', 8 - strlen($data));
			$right = substr($data, $midpoint);
			$data = $left . $center . $right;
		} else if (strlen($data) > 8) {
			$left = substr($data, 0, 4);
			$right = substr($data, -4);
			$data = $left . $right;
		}
		/* Replace * with missing or check digit. */
		while (($o = strrpos($data, '*')) !== false) {
			$checksum = 0;
			for ($i = 0; $i < 8; $i++) {
				$digit = substr($data, $i, 1);
				$checksum += (($i % 2) ? 1 : 3) * $digit;
			}
			$checksum *= (($o % 2) ? 9 : 3);
			$left = substr($data, 0, $o);
			$center = substr($checksum, -1);
			$right = substr($data, $o + 1);
			$data = $left . $center . $right;
		}
		return $data;
	}

	private $upc_alphabet = array(
		'0' => array(3, 2, 1, 1),
		'1' => array(2, 2, 2, 1),
		'2' => array(2, 1, 2, 2),
		'3' => array(1, 4, 1, 1),
		'4' => array(1, 1, 3, 2),
		'5' => array(1, 2, 3, 1),
		'6' => array(1, 1, 1, 4),
		'7' => array(1, 3, 1, 2),
		'8' => array(1, 2, 1, 3),
		'9' => array(3, 1, 1, 2),
	);

	private $upc_parity = array(
		'0' => array(1, 1, 1, 0, 0, 0),
		'1' => array(1, 1, 0, 1, 0, 0),
		'2' => array(1, 1, 0, 0, 1, 0),
		'3' => array(1, 1, 0, 0, 0, 1),
		'4' => array(1, 0, 1, 1, 0, 0),
		'5' => array(1, 0, 0, 1, 1, 0),
		'6' => array(1, 0, 0, 0, 1, 1),
		'7' => array(1, 0, 1, 0, 1, 0),
		'8' => array(1, 0, 1, 0, 0, 1),
		'9' => array(1, 0, 0, 1, 0, 1),
	);

	/* - - - - CODE 39 FAMILY ENCODER - - - - */

	private function code_39_encode($data) {
		$data = strtoupper(preg_replace('/[^0-9A-Za-z%$\/+ .-]/', '', $data));
		$blocks = array();
		/* Start */
		$blocks[] = array(
			'm' => array(
				array(1, 1, 1), array(0, 1, 2), array(1, 1, 1),
				array(0, 1, 1), array(1, 1, 2), array(0, 1, 1),
				array(1, 1, 2), array(0, 1, 1), array(1, 1, 1),
			),
			'l' => array('*')
		);
		/* Data */
		for ($i = 0, $n = strlen($data); $i < $n; $i++) {
			$blocks[] = array(
				'm' => array(array(0, 1, 3))
			);
			$char = substr($data, $i, 1);
			$block = $this->code_39_alphabet[$char];
			$blocks[] = array(
				'm' => array(
					array(1, 1, $block[0]),
					array(0, 1, $block[1]),
					array(1, 1, $block[2]),
					array(0, 1, $block[3]),
					array(1, 1, $block[4]),
					array(0, 1, $block[5]),
					array(1, 1, $block[6]),
					array(0, 1, $block[7]),
					array(1, 1, $block[8]),
				),
				'l' => array($char)
			);
		}
		$blocks[] = array(
			'm' => array(array(0, 1, 3))
		);
		/* End */
		$blocks[] = array(
			'm' => array(
				array(1, 1, 1), array(0, 1, 2), array(1, 1, 1),
				array(0, 1, 1), array(1, 1, 2), array(0, 1, 1),
				array(1, 1, 2), array(0, 1, 1), array(1, 1, 1),
			),
			'l' => array('*')
		);
		/* Return */
		return array('g' => 'l', 'b' => $blocks);
	}

	private function code_39_ascii_encode($data) {
		$modules = array();
		/* Start */
		$modules[] = array(1, 1, 1);
		$modules[] = array(0, 1, 2);
		$modules[] = array(1, 1, 1);
		$modules[] = array(0, 1, 1);
		$modules[] = array(1, 1, 2);
		$modules[] = array(0, 1, 1);
		$modules[] = array(1, 1, 2);
		$modules[] = array(0, 1, 1);
		$modules[] = array(1, 1, 1);
		/* Data */
		$label = '';
		for ($i = 0, $n = strlen($data); $i < $n; $i++) {
			$char = substr($data, $i, 1);
			$ch = ord($char);
			if ($ch < 128) {
				if ($ch < 32 || $ch >= 127) {
					$label .= ' ';
				} else {
					$label .= $char;
				}
				$ch = $this->code_39_asciibet[$ch];
				for ($j = 0, $m = strlen($ch); $j < $m; $j++) {
					$c = substr($ch, $j, 1);
					$b = $this->code_39_alphabet[$c];
					$modules[] = array(0, 1, 3);
					$modules[] = array(1, 1, $b[0]);
					$modules[] = array(0, 1, $b[1]);
					$modules[] = array(1, 1, $b[2]);
					$modules[] = array(0, 1, $b[3]);
					$modules[] = array(1, 1, $b[4]);
					$modules[] = array(0, 1, $b[5]);
					$modules[] = array(1, 1, $b[6]);
					$modules[] = array(0, 1, $b[7]);
					$modules[] = array(1, 1, $b[8]);
				}
			}
		}
		$modules[] = array(0, 1, 3);
		/* End */
		$modules[] = array(1, 1, 1);
		$modules[] = array(0, 1, 2);
		$modules[] = array(1, 1, 1);
		$modules[] = array(0, 1, 1);
		$modules[] = array(1, 1, 2);
		$modules[] = array(0, 1, 1);
		$modules[] = array(1, 1, 2);
		$modules[] = array(0, 1, 1);
		$modules[] = array(1, 1, 1);
		/* Return */
		$blocks = array(array('m' => $modules, 'l' => array($label)));
		return array('g' => 'l', 'b' => $blocks);
	}

	private function code_93_encode($data) {
		$data = strtoupper(preg_replace('/[^0-9A-Za-z%+\/$ .-]/', '', $data));
		$modules = array();
		/* Start */
		$modules[] = array(1, 1, 1);
		$modules[] = array(0, 1, 1);
		$modules[] = array(1, 1, 1);
		$modules[] = array(0, 1, 1);
		$modules[] = array(1, 4, 1);
		$modules[] = array(0, 1, 1);
		/* Data */
		$values = array();
		for ($i = 0, $n = strlen($data); $i < $n; $i++) {
			$char = substr($data, $i, 1);
			$block = $this->code_93_alphabet[$char];
			$modules[] = array(1, $block[0], 1);
			$modules[] = array(0, $block[1], 1);
			$modules[] = array(1, $block[2], 1);
			$modules[] = array(0, $block[3], 1);
			$modules[] = array(1, $block[4], 1);
			$modules[] = array(0, $block[5], 1);
			$values[] = $block[6];
		}
		/* Check Digits */
		for ($i = 0; $i < 2; $i++) {
			$index = count($values);
			$weight = 0;
			$checksum = 0;
			while ($index) {
				$index--;
				$weight++;
				$checksum += $weight * $values[$index];
				$checksum %= 47;
				$weight %= ($i ? 15 : 20);
			}
			$values[] = $checksum;
		}
		$alphabet = array_values($this->code_93_alphabet);
		for ($i = count($values) - 2, $n = count($values); $i < $n; $i++) {
			$block = $alphabet[$values[$i]];
			$modules[] = array(1, $block[0], 1);
			$modules[] = array(0, $block[1], 1);
			$modules[] = array(1, $block[2], 1);
			$modules[] = array(0, $block[3], 1);
			$modules[] = array(1, $block[4], 1);
			$modules[] = array(0, $block[5], 1);
		}
		/* End */
		$modules[] = array(1, 1, 1);
		$modules[] = array(0, 1, 1);
		$modules[] = array(1, 1, 1);
		$modules[] = array(0, 1, 1);
		$modules[] = array(1, 4, 1);
		$modules[] = array(0, 1, 1);
		$modules[] = array(1, 1, 1);
		/* Return */
		$blocks = array(array('m' => $modules, 'l' => array($data)));
		return array('g' => 'l', 'b' => $blocks);
	}

	private function code_93_ascii_encode($data) {
		$modules = array();
		/* Start */
		$modules[] = array(1, 1, 1);
		$modules[] = array(0, 1, 1);
		$modules[] = array(1, 1, 1);
		$modules[] = array(0, 1, 1);
		$modules[] = array(1, 4, 1);
		$modules[] = array(0, 1, 1);
		/* Data */
		$label = '';
		$values = array();
		for ($i = 0, $n = strlen($data); $i < $n; $i++) {
			$char = substr($data, $i, 1);
			$ch = ord($char);
			if ($ch < 128) {
				if ($ch < 32 || $ch >= 127) {
					$label .= ' ';
				} else {
					$label .= $char;
				}
				$ch = $this->code_93_asciibet[$ch];
				for ($j = 0, $m = strlen($ch); $j < $m; $j++) {
					$c = substr($ch, $j, 1);
					$b = $this->code_93_alphabet[$c];
					$modules[] = array(1, $b[0], 1);
					$modules[] = array(0, $b[1], 1);
					$modules[] = array(1, $b[2], 1);
					$modules[] = array(0, $b[3], 1);
					$modules[] = array(1, $b[4], 1);
					$modules[] = array(0, $b[5], 1);
					$values[] = $b[6];
				}
			}
		}
		/* Check Digits */
		for ($i = 0; $i < 2; $i++) {
			$index = count($values);
			$weight = 0;
			$checksum = 0;
			while ($index) {
				$index--;
				$weight++;
				$checksum += $weight * $values[$index];
				$checksum %= 47;
				$weight %= ($i ? 15 : 20);
			}
			$values[] = $checksum;
		}
		$alphabet = array_values($this->code_93_alphabet);
		for ($i = count($values) - 2, $n = count($values); $i < $n; $i++) {
			$block = $alphabet[$values[$i]];
			$modules[] = array(1, $block[0], 1);
			$modules[] = array(0, $block[1], 1);
			$modules[] = array(1, $block[2], 1);
			$modules[] = array(0, $block[3], 1);
			$modules[] = array(1, $block[4], 1);
			$modules[] = array(0, $block[5], 1);
		}
		/* End */
		$modules[] = array(1, 1, 1);
		$modules[] = array(0, 1, 1);
		$modules[] = array(1, 1, 1);
		$modules[] = array(0, 1, 1);
		$modules[] = array(1, 4, 1);
		$modules[] = array(0, 1, 1);
		$modules[] = array(1, 1, 1);
		/* Return */
		$blocks = array(array('m' => $modules, 'l' => array($label)));
		return array('g' => 'l', 'b' => $blocks);
	}

	private $code_39_alphabet = array(
		'1' => array(2, 1, 1, 2, 1, 1, 1, 1, 2),
		'2' => array(1, 1, 2, 2, 1, 1, 1, 1, 2),
		'3' => array(2, 1, 2, 2, 1, 1, 1, 1, 1),
		'4' => array(1, 1, 1, 2, 2, 1, 1, 1, 2),
		'5' => array(2, 1, 1, 2, 2, 1, 1, 1, 1),
		'6' => array(1, 1, 2, 2, 2, 1, 1, 1, 1),
		'7' => array(1, 1, 1, 2, 1, 1, 2, 1, 2),
		'8' => array(2, 1, 1, 2, 1, 1, 2, 1, 1),
		'9' => array(1, 1, 2, 2, 1, 1, 2, 1, 1),
		'0' => array(1, 1, 1, 2, 2, 1, 2, 1, 1),
		'A' => array(2, 1, 1, 1, 1, 2, 1, 1, 2),
		'B' => array(1, 1, 2, 1, 1, 2, 1, 1, 2),
		'C' => array(2, 1, 2, 1, 1, 2, 1, 1, 1),
		'D' => array(1, 1, 1, 1, 2, 2, 1, 1, 2),
		'E' => array(2, 1, 1, 1, 2, 2, 1, 1, 1),
		'F' => array(1, 1, 2, 1, 2, 2, 1, 1, 1),
		'G' => array(1, 1, 1, 1, 1, 2, 2, 1, 2),
		'H' => array(2, 1, 1, 1, 1, 2, 2, 1, 1),
		'I' => array(1, 1, 2, 1, 1, 2, 2, 1, 1),
		'J' => array(1, 1, 1, 1, 2, 2, 2, 1, 1),
		'K' => array(2, 1, 1, 1, 1, 1, 1, 2, 2),
		'L' => array(1, 1, 2, 1, 1, 1, 1, 2, 2),
		'M' => array(2, 1, 2, 1, 1, 1, 1, 2, 1),
		'N' => array(1, 1, 1, 1, 2, 1, 1, 2, 2),
		'O' => array(2, 1, 1, 1, 2, 1, 1, 2, 1),
		'P' => array(1, 1, 2, 1, 2, 1, 1, 2, 1),
		'Q' => array(1, 1, 1, 1, 1, 1, 2, 2, 2),
		'R' => array(2, 1, 1, 1, 1, 1, 2, 2, 1),
		'S' => array(1, 1, 2, 1, 1, 1, 2, 2, 1),
		'T' => array(1, 1, 1, 1, 2, 1, 2, 2, 1),
		'U' => array(2, 2, 1, 1, 1, 1, 1, 1, 2),
		'V' => array(1, 2, 2, 1, 1, 1, 1, 1, 2),
		'W' => array(2, 2, 2, 1, 1, 1, 1, 1, 1),
		'X' => array(1, 2, 1, 1, 2, 1, 1, 1, 2),
		'Y' => array(2, 2, 1, 1, 2, 1, 1, 1, 1),
		'Z' => array(1, 2, 2, 1, 2, 1, 1, 1, 1),
		'-' => array(1, 2, 1, 1, 1, 1, 2, 1, 2),
		'.' => array(2, 2, 1, 1, 1, 1, 2, 1, 1),
		' ' => array(1, 2, 2, 1, 1, 1, 2, 1, 1),
		'*' => array(1, 2, 1, 1, 2, 1, 2, 1, 1),
		'+' => array(1, 2, 1, 1, 1, 2, 1, 2, 1),
		'/' => array(1, 2, 1, 2, 1, 1, 1, 2, 1),
		'$' => array(1, 2, 1, 2, 1, 2, 1, 1, 1),
		'%' => array(1, 1, 1, 2, 1, 2, 1, 2, 1),
	);

	private $code_39_asciibet = array(
		'%U', '$A', '$B', '$C', '$D', '$E', '$F', '$G',
		'$H', '$I', '$J', '$K', '$L', '$M', '$N', '$O',
		'$P', '$Q', '$R', '$S', '$T', '$U', '$V', '$W',
		'$X', '$Y', '$Z', '%A', '%B', '%C', '%D', '%E',
		' ' , '/A', '/B', '/C', '/D', '/E', '/F', '/G',
		'/H', '/I', '/J', '/K', '/L', '-' , '.' , '/O',
		'0' , '1' , '2' , '3' , '4' , '5' , '6' , '7' ,
		'8' , '9' , '/Z', '%F', '%G', '%H', '%I', '%J',
		'%V', 'A' , 'B' , 'C' , 'D' , 'E' , 'F' , 'G' ,
		'H' , 'I' , 'J' , 'K' , 'L' , 'M' , 'N' , 'O' ,
		'P' , 'Q' , 'R' , 'S' , 'T' , 'U' , 'V' , 'W' ,
		'X' , 'Y' , 'Z' , '%K', '%L', '%M', '%N', '%O',
		'%W', '+A', '+B', '+C', '+D', '+E', '+F', '+G',
		'+H', '+I', '+J', '+K', '+L', '+M', '+N', '+O',
		'+P', '+Q', '+R', '+S', '+T', '+U', '+V', '+W',
		'+X', '+Y', '+Z', '%P', '%Q', '%R', '%S', '%T',
	);

	private $code_93_alphabet = array(
		'0' => array(1, 3, 1, 1, 1, 2,  0),
		'1' => array(1, 1, 1, 2, 1, 3,  1),
		'2' => array(1, 1, 1, 3, 1, 2,  2),
		'3' => array(1, 1, 1, 4, 1, 1,  3),
		'4' => array(1, 2, 1, 1, 1, 3,  4),
		'5' => array(1, 2, 1, 2, 1, 2,  5),
		'6' => array(1, 2, 1, 3, 1, 1,  6),
		'7' => array(1, 1, 1, 1, 1, 4,  7),
		'8' => array(1, 3, 1, 2, 1, 1,  8),
		'9' => array(1, 4, 1, 1, 1, 1,  9),
		'A' => array(2, 1, 1, 1, 1, 3, 10),
		'B' => array(2, 1, 1, 2, 1, 2, 11),
		'C' => array(2, 1, 1, 3, 1, 1, 12),
		'D' => array(2, 2, 1, 1, 1, 2, 13),
		'E' => array(2, 2, 1, 2, 1, 1, 14),
		'F' => array(2, 3, 1, 1, 1, 1, 15),
		'G' => array(1, 1, 2, 1, 1, 3, 16),
		'H' => array(1, 1, 2, 2, 1, 2, 17),
		'I' => array(1, 1, 2, 3, 1, 1, 18),
		'J' => array(1, 2, 2, 1, 1, 2, 19),
		'K' => array(1, 3, 2, 1, 1, 1, 20),
		'L' => array(1, 1, 1, 1, 2, 3, 21),
		'M' => array(1, 1, 1, 2, 2, 2, 22),
		'N' => array(1, 1, 1, 3, 2, 1, 23),
		'O' => array(1, 2, 1, 1, 2, 2, 24),
		'P' => array(1, 3, 1, 1, 2, 1, 25),
		'Q' => array(2, 1, 2, 1, 1, 2, 26),
		'R' => array(2, 1, 2, 2, 1, 1, 27),
		'S' => array(2, 1, 1, 1, 2, 2, 28),
		'T' => array(2, 1, 1, 2, 2, 1, 29),
		'U' => array(2, 2, 1, 1, 2, 1, 30),
		'V' => array(2, 2, 2, 1, 1, 1, 31),
		'W' => array(1, 1, 2, 1, 2, 2, 32),
		'X' => array(1, 1, 2, 2, 2, 1, 33),
		'Y' => array(1, 2, 2, 1, 2, 1, 34),
		'Z' => array(1, 2, 3, 1, 1, 1, 35),
		'-' => array(1, 2, 1, 1, 3, 1, 36),
		'.' => array(3, 1, 1, 1, 1, 2, 37),
		' ' => array(3, 1, 1, 2, 1, 1, 38),
		'$' => array(3, 2, 1, 1, 1, 1, 39),
		'/' => array(1, 1, 2, 1, 3, 1, 40),
		'+' => array(1, 1, 3, 1, 2, 1, 41),
		'%' => array(2, 1, 1, 1, 3, 1, 42),
		'#' => array(1, 2, 1, 2, 2, 1, 43), /* ($) */
		'&' => array(3, 1, 2, 1, 1, 1, 44), /* (%) */
		'|' => array(3, 1, 1, 1, 2, 1, 45), /* (/) */
		'=' => array(1, 2, 2, 2, 1, 1, 46), /* (+) */
		'*' => array(1, 1, 1, 1, 4, 1,  0),
	);

	private $code_93_asciibet = array(
		'&U', '#A', '#B', '#C', '#D', '#E', '#F', '#G',
		'#H', '#I', '#J', '#K', '#L', '#M', '#N', '#O',
		'#P', '#Q', '#R', '#S', '#T', '#U', '#V', '#W',
		'#X', '#Y', '#Z', '&A', '&B', '&C', '&D', '&E',
		' ' , '|A', '|B', '|C', '$' , '%' , '|F', '|G',
		'|H', '|I', '|J', '+' , '|L', '-' , '.' , '/' ,
		'0' , '1' , '2' , '3' , '4' , '5' , '6' , '7' ,
		'8' , '9' , '|Z', '&F', '&G', '&H', '&I', '&J',
		'&V', 'A' , 'B' , 'C' , 'D' , 'E' , 'F' , 'G' ,
		'H' , 'I' , 'J' , 'K' , 'L' , 'M' , 'N' , 'O' ,
		'P' , 'Q' , 'R' , 'S' , 'T' , 'U' , 'V' , 'W' ,
		'X' , 'Y' , 'Z' , '&K', '&L', '&M', '&N', '&O',
		'&W', '=A', '=B', '=C', '=D', '=E', '=F', '=G',
		'=H', '=I', '=J', '=K', '=L', '=M', '=N', '=O',
		'=P', '=Q', '=R', '=S', '=T', '=U', '=V', '=W',
		'=X', '=Y', '=Z', '&P', '&Q', '&R', '&S', '&T',
	);

	/* - - - - CODE 128 ENCODER - - - - */

	private function code_128_encode($data, $dstate, $fnc1) {
		$data = preg_replace('/[\x80-\xFF]/', '', $data);
		$label = preg_replace('/[\x00-\x1F\x7F]/', ' ', $data);
		$chars = $this->code_128_normalize($data, $dstate, $fnc1);
		$checksum = $chars[0] % 103;
		for ($i = 1, $n = count($chars); $i < $n; $i++) {
			$checksum += $i * $chars[$i];
			$checksum %= 103;
		}
		$chars[] = $checksum;
		$chars[] = 106;
		$modules = array();
		$modules[] = array(0, 10, 0);
		foreach ($chars as $char) {
			$block = $this->code_128_alphabet[$char];
			foreach ($block as $i => $module) {
				$modules[] = array(($i & 1) ^ 1, $module, 1);
			}
		}
		$modules[] = array(0, 10, 0);
		$blocks = array(array('m' => $modules, 'l' => array($label)));
		return array('g' => 'l', 'b' => $blocks);
	}

	private function code_128_normalize($data, $dstate, $fnc1) {
		$detectcba = '/(^[0-9]{4,}|^[0-9]{2}$)|([\x60-\x7F])|([\x00-\x1F])/';
		$detectc = '/(^[0-9]{6,}|^[0-9]{4,}$)/';
		$detectba = '/([\x60-\x7F])|([\x00-\x1F])/';
		$consumec = '/(^[0-9]{2})/';
		$state = (($dstate > 0 && $dstate < 4) ? $dstate : 0);
		$abstate = ((abs($dstate) == 2) ? 2 : 1);
		$chars = array(102 + ($state ? $state : $abstate));
		if ($fnc1) $chars[] = 102;
		while (strlen($data)) {
			switch ($state) {
				case 0:
					if (preg_match($detectcba, $data, $m)) {
						if ($m[1]) {
							$state = 3;
						} else if ($m[2]) {
							$state = 2;
						} else {
							$state = 1;
						}
					} else {
						$state = $abstate;
					}
					$chars = array(102 + $state);
					if ($fnc1) $chars[] = 102;
					break;
				case 1:
					if ($dstate <= 0 && preg_match($detectc, $data, $m)) {
						if (strlen($m[0]) % 2) {
							$data = substr($data, 1);
							$chars[] = 16 + substr($m[0], 0, 1);
						}
						$state = 3;
						$chars[] = 99;
					} else {
						$ch = ord(substr($data, 0, 1));
						$data = substr($data, 1);
						if ($ch < 32) {
							$chars[] = $ch + 64;
						} else if ($ch < 96) {
							$chars[] = $ch - 32;
						} else {
							if (preg_match($detectba, $data, $m)) {
								if ($m[1]) {
									$state = 2;
									$chars[] = 100;
								} else {
									$chars[] = 98;
								}
							} else {
								$chars[] = 98;
							}
							$chars[] = $ch - 32;
						}
					}
					break;
				case 2:
					if ($dstate <= 0 && preg_match($detectc, $data, $m)) {
						if (strlen($m[0]) % 2) {
							$data = substr($data, 1);
							$chars[] = 16 + substr($m[0], 0, 1);
						}
						$state = 3;
						$chars[] = 99;
					} else {
						$ch = ord(substr($data, 0, 1));
						$data = substr($data, 1);
						if ($ch >= 32) {
							$chars[] = $ch - 32;
						} else {
							if (preg_match($detectba, $data, $m)) {
								if ($m[2]) {
									$state = 1;
									$chars[] = 101;
								} else {
									$chars[] = 98;
								}
							} else {
								$chars[] = 98;
							}
							$chars[] = $ch + 64;
						}
					}
					break;
				case 3:
					if (preg_match($consumec, $data, $m)) {
						$data = substr($data, 2);
						$chars[] = (int)$m[0];
					} else {
						if (preg_match($detectba, $data, $m)) {
							if ($m[1]) {
								$state = 2;
							} else {
								$state = 1;
							}
						} else {
							$state = $abstate;
						}
						$chars[] = 102 - $state;
					}
					break;
			}
		}
		return $chars;
	}

	private $code_128_alphabet = array(
		array(2, 1, 2, 2, 2, 2), array(2, 2, 2, 1, 2, 2),
		array(2, 2, 2, 2, 2, 1), array(1, 2, 1, 2, 2, 3),
		array(1, 2, 1, 3, 2, 2), array(1, 3, 1, 2, 2, 2),
		array(1, 2, 2, 2, 1, 3), array(1, 2, 2, 3, 1, 2),
		array(1, 3, 2, 2, 1, 2), array(2, 2, 1, 2, 1, 3),
		array(2, 2, 1, 3, 1, 2), array(2, 3, 1, 2, 1, 2),
		array(1, 1, 2, 2, 3, 2), array(1, 2, 2, 1, 3, 2),
		array(1, 2, 2, 2, 3, 1), array(1, 1, 3, 2, 2, 2),
		array(1, 2, 3, 1, 2, 2), array(1, 2, 3, 2, 2, 1),
		array(2, 2, 3, 2, 1, 1), array(2, 2, 1, 1, 3, 2),
		array(2, 2, 1, 2, 3, 1), array(2, 1, 3, 2, 1, 2),
		array(2, 2, 3, 1, 1, 2), array(3, 1, 2, 1, 3, 1),
		array(3, 1, 1, 2, 2, 2), array(3, 2, 1, 1, 2, 2),
		array(3, 2, 1, 2, 2, 1), array(3, 1, 2, 2, 1, 2),
		array(3, 2, 2, 1, 1, 2), array(3, 2, 2, 2, 1, 1),
		array(2, 1, 2, 1, 2, 3), array(2, 1, 2, 3, 2, 1),
		array(2, 3, 2, 1, 2, 1), array(1, 1, 1, 3, 2, 3),
		array(1, 3, 1, 1, 2, 3), array(1, 3, 1, 3, 2, 1),
		array(1, 1, 2, 3, 1, 3), array(1, 3, 2, 1, 1, 3),
		array(1, 3, 2, 3, 1, 1), array(2, 1, 1, 3, 1, 3),
		array(2, 3, 1, 1, 1, 3), array(2, 3, 1, 3, 1, 1),
		array(1, 1, 2, 1, 3, 3), array(1, 1, 2, 3, 3, 1),
		array(1, 3, 2, 1, 3, 1), array(1, 1, 3, 1, 2, 3),
		array(1, 1, 3, 3, 2, 1), array(1, 3, 3, 1, 2, 1),
		array(3, 1, 3, 1, 2, 1), array(2, 1, 1, 3, 3, 1),
		array(2, 3, 1, 1, 3, 1), array(2, 1, 3, 1, 1, 3),
		array(2, 1, 3, 3, 1, 1), array(2, 1, 3, 1, 3, 1),
		array(3, 1, 1, 1, 2, 3), array(3, 1, 1, 3, 2, 1),
		array(3, 3, 1, 1, 2, 1), array(3, 1, 2, 1, 1, 3),
		array(3, 1, 2, 3, 1, 1), array(3, 3, 2, 1, 1, 1),
		array(3, 1, 4, 1, 1, 1), array(2, 2, 1, 4, 1, 1),
		array(4, 3, 1, 1, 1, 1), array(1, 1, 1, 2, 2, 4),
		array(1, 1, 1, 4, 2, 2), array(1, 2, 1, 1, 2, 4),
		array(1, 2, 1, 4, 2, 1), array(1, 4, 1, 1, 2, 2),
		array(1, 4, 1, 2, 2, 1), array(1, 1, 2, 2, 1, 4),
		array(1, 1, 2, 4, 1, 2), array(1, 2, 2, 1, 1, 4),
		array(1, 2, 2, 4, 1, 1), array(1, 4, 2, 1, 1, 2),
		array(1, 4, 2, 2, 1, 1), array(2, 4, 1, 2, 1, 1),
		array(2, 2, 1, 1, 1, 4), array(4, 1, 3, 1, 1, 1),
		array(2, 4, 1, 1, 1, 2), array(1, 3, 4, 1, 1, 1),
		array(1, 1, 1, 2, 4, 2), array(1, 2, 1, 1, 4, 2),
		array(1, 2, 1, 2, 4, 1), array(1, 1, 4, 2, 1, 2),
		array(1, 2, 4, 1, 1, 2), array(1, 2, 4, 2, 1, 1),
		array(4, 1, 1, 2, 1, 2), array(4, 2, 1, 1, 1, 2),
		array(4, 2, 1, 2, 1, 1), array(2, 1, 2, 1, 4, 1),
		array(2, 1, 4, 1, 2, 1), array(4, 1, 2, 1, 2, 1),
		array(1, 1, 1, 1, 4, 3), array(1, 1, 1, 3, 4, 1),
		array(1, 3, 1, 1, 4, 1), array(1, 1, 4, 1, 1, 3),
		array(1, 1, 4, 3, 1, 1), array(4, 1, 1, 1, 1, 3),
		array(4, 1, 1, 3, 1, 1), array(1, 1, 3, 1, 4, 1),
		array(1, 1, 4, 1, 3, 1), array(3, 1, 1, 1, 4, 1),
		array(4, 1, 1, 1, 3, 1), array(2, 1, 1, 4, 1, 2),
		array(2, 1, 1, 2, 1, 4), array(2, 1, 1, 2, 3, 2),
		array(2, 3, 3, 1, 1, 1, 2)
	);

	/* - - - - CODABAR ENCODER - - - - */

	private function codabar_encode($data) {
		$data = strtoupper(preg_replace(
			'/[^0-9ABCDENTabcdent*.\/:+$-]/', '', $data
		));
		$blocks = array();
		for ($i = 0, $n = strlen($data); $i < $n; $i++) {
			if ($blocks) {
				$blocks[] = array(
					'm' => array(array(0, 1, 3))
				);
			}
			$char = substr($data, $i, 1);
			$block = $this->codabar_alphabet[$char];
			$blocks[] = array(
				'm' => array(
					array(1, 1, $block[0]),
					array(0, 1, $block[1]),
					array(1, 1, $block[2]),
					array(0, 1, $block[3]),
					array(1, 1, $block[4]),
					array(0, 1, $block[5]),
					array(1, 1, $block[6]),
				),
				'l' => array($char)
			);
		}
		return array('g' => 'l', 'b' => $blocks);
	}

	private $codabar_alphabet = array(
		'0' => array(1, 1, 1, 1, 1, 2, 2),
		'1' => array(1, 1, 1, 1, 2, 2, 1),
		'4' => array(1, 1, 2, 1, 1, 2, 1),
		'5' => array(2, 1, 1, 1, 1, 2, 1),
		'2' => array(1, 1, 1, 2, 1, 1, 2),
		'-' => array(1, 1, 1, 2, 2, 1, 1),
		'$' => array(1, 1, 2, 2, 1, 1, 1),
		'9' => array(2, 1, 1, 2, 1, 1, 1),
		'6' => array(1, 2, 1, 1, 1, 1, 2),
		'7' => array(1, 2, 1, 1, 2, 1, 1),
		'8' => array(1, 2, 2, 1, 1, 1, 1),
		'3' => array(2, 2, 1, 1, 1, 1, 1),
		'C' => array(1, 1, 1, 2, 1, 2, 2),
		'D' => array(1, 1, 1, 2, 2, 2, 1),
		'A' => array(1, 1, 2, 2, 1, 2, 1),
		'B' => array(1, 2, 1, 2, 1, 1, 2),
		'*' => array(1, 1, 1, 2, 1, 2, 2),
		'E' => array(1, 1, 1, 2, 2, 2, 1),
		'T' => array(1, 1, 2, 2, 1, 2, 1),
		'N' => array(1, 2, 1, 2, 1, 1, 2),
		'.' => array(2, 1, 2, 1, 2, 1, 1),
		'/' => array(2, 1, 2, 1, 1, 1, 2),
		':' => array(2, 1, 1, 1, 2, 1, 2),
		'+' => array(1, 1, 2, 1, 2, 1, 2),
	);

	/* - - - - ITF ENCODER - - - - */

	private function itf_encode($data) {
		$data = preg_replace('/[^0-9]/', '', $data);
		if (strlen($data) % 2) $data = '0' . $data;
		$blocks = array();
		/* Quiet zone, start. */
		$blocks[] = array(
			'm' => array(array(0, 10, 0))
		);
		$blocks[] = array(
			'm' => array(
				array(1, 1, 1),
				array(0, 1, 1),
				array(1, 1, 1),
				array(0, 1, 1),
			)
		);
		/* Data. */
		for ($i = 0, $n = strlen($data); $i < $n; $i += 2) {
			$c1 = substr($data, $i, 1);
			$c2 = substr($data, $i+1, 1);
			$b1 = $this->itf_alphabet[$c1];
			$b2 = $this->itf_alphabet[$c2];
			$blocks[] = array(
				'm' => array(
					array(1, 1, $b1[0]),
					array(0, 1, $b2[0]),
					array(1, 1, $b1[1]),
					array(0, 1, $b2[1]),
					array(1, 1, $b1[2]),
					array(0, 1, $b2[2]),
					array(1, 1, $b1[3]),
					array(0, 1, $b2[3]),
					array(1, 1, $b1[4]),
					array(0, 1, $b2[4]),
				),
				'l' => array($c1 . $c2)
			);
		}
		/* End, quiet zone. */
		$blocks[] = array(
			'm' => array(
				array(1, 1, 2),
				array(0, 1, 1),
				array(1, 1, 1),
			)
		);
		$blocks[] = array(
			'm' => array(array(0, 10, 0))
		);
		/* Return code. */
		return array('g' => 'l', 'b' => $blocks);
	}

	private $itf_alphabet = array(
		'0' => array(1, 1, 2, 2, 1),
		'1' => array(2, 1, 1, 1, 2),
		'2' => array(1, 2, 1, 1, 2),
		'3' => array(2, 2, 1, 1, 1),
		'4' => array(1, 1, 2, 1, 2),
		'5' => array(2, 1, 2, 1, 1),
		'6' => array(1, 2, 2, 1, 1),
		'7' => array(1, 1, 1, 2, 2),
		'8' => array(2, 1, 1, 2, 1),
		'9' => array(1, 2, 1, 2, 1),
	);

	/* - - - - QR ENCODER - - - - */

	private function qr_encode($data, $ecl) {
		list($mode, $vers, $ec, $data) = $this->qr_encode_data($data, $ecl);
		$data = $this->qr_encode_ec($data, $ec, $vers);
		list($size, $mtx) = $this->qr_create_matrix($vers, $data);
		list($mask, $mtx) = $this->qr_apply_best_mask($mtx, $size);
		$mtx = $this->qr_finalize_matrix($mtx, $size, $ecl, $mask, $vers);
		return array(
			'g' => 'm',
			'q' => array(4, 4, 4, 4),
			's' => array($size, $size),
			'b' => $mtx
		);
	}

	private function qr_encode_data($data, $ecl) {
		$mode = $this->qr_detect_mode($data);
		$version = $this->qr_detect_version($data, $mode, $ecl);
		$version_group = (($version < 10) ? 0 : (($version < 27) ? 1 : 2));
		$ec_params = $this->qr_ec_params[($version - 1) * 4 + $ecl];
		/* Don't cut off mid-character if exceeding capacity. */
		$max_chars = $this->qr_capacity[$version - 1][$ecl][$mode];
		if ($mode == 3) $max_chars <<= 1;
		$data = substr($data, 0, $max_chars);
		/* Convert from character level to bit level. */
		switch ($mode) {
			case 0:
				$code = $this->qr_encode_numeric($data, $version_group);
				break;
			case 1:
				$code = $this->qr_encode_alphanumeric($data, $version_group);
				break;
			case 2:
				$code = $this->qr_encode_binary($data, $version_group);
				break;
			case 3:
				$code = $this->qr_encode_kanji($data, $version_group);
				break;
		}
		for ($i = 0; $i < 4; $i++) $code[] = 0;
		while (count($code) % 8) $code[] = 0;
		/* Convert from bit level to byte level. */
		$data = array();
		for ($i = 0, $n = count($code); $i < $n; $i += 8) {
			$byte = 0;
			if ($code[$i + 0]) $byte |= 0x80;
			if ($code[$i + 1]) $byte |= 0x40;
			if ($code[$i + 2]) $byte |= 0x20;
			if ($code[$i + 3]) $byte |= 0x10;
			if ($code[$i + 4]) $byte |= 0x08;
			if ($code[$i + 5]) $byte |= 0x04;
			if ($code[$i + 6]) $byte |= 0x02;
			if ($code[$i + 7]) $byte |= 0x01;
			$data[] = $byte;
		}
		for (
			$i = count($data), $a = 1, $n = $ec_params[0];
			$i < $n; $i++, $a ^= 1
		) {
			$data[] = $a ? 236 : 17;
		}
		/* Return. */
		return array($mode, $version, $ec_params, $data);
	}

	private function qr_detect_mode($data) {
		$numeric = '/^[0-9]*$/';
		$alphanumeric = '/^[0-9A-Z .\/:$%*+-]*$/';
		$kanji = '/^([\x81-\x9F\xE0-\xEA][\x40-\xFC]|[\xEB][\x40-\xBF])*$/';
		if (preg_match($numeric, $data)) return 0;
		if (preg_match($alphanumeric, $data)) return 1;
		if (preg_match($kanji, $data)) return 3;
		return 2;
	}

	private function qr_detect_version($data, $mode, $ecl) {
		$length = strlen($data);
		if ($mode == 3) $length >>= 1;
		for ($v = 0; $v < 40; $v++) {
			if ($length <= $this->qr_capacity[$v][$ecl][$mode]) {
				return $v + 1;
			}
		}
		return 40;
	}

	private function qr_encode_numeric($data, $version_group) {
		$code = array(0, 0, 0, 1);
		$length = strlen($data);
		switch ($version_group) {
			case 2:  /* 27 - 40 */
				$code[] = $length & 0x2000;
				$code[] = $length & 0x1000;
			case 1:  /* 10 - 26 */
				$code[] = $length & 0x0800;
				$code[] = $length & 0x0400;
			case 0:  /* 1 - 9 */
				$code[] = $length & 0x0200;
				$code[] = $length & 0x0100;
				$code[] = $length & 0x0080;
				$code[] = $length & 0x0040;
				$code[] = $length & 0x0020;
				$code[] = $length & 0x0010;
				$code[] = $length & 0x0008;
				$code[] = $length & 0x0004;
				$code[] = $length & 0x0002;
				$code[] = $length & 0x0001;
		}
		for ($i = 0; $i < $length; $i += 3) {
			$group = substr($data, $i, 3);
			switch (strlen($group)) {
				case 3:
					$code[] = $group & 0x200;
					$code[] = $group & 0x100;
					$code[] = $group & 0x080;
				case 2:
					$code[] = $group & 0x040;
					$code[] = $group & 0x020;
					$code[] = $group & 0x010;
				case 1:
					$code[] = $group & 0x008;
					$code[] = $group & 0x004;
					$code[] = $group & 0x002;
					$code[] = $group & 0x001;
			}
		}
		return $code;
	}

	private function qr_encode_alphanumeric($data, $version_group) {
		$alphabet = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ $%*+-./:';
		$code = array(0, 0, 1, 0);
		$length = strlen($data);
		switch ($version_group) {
			case 2:  /* 27 - 40 */
				$code[] = $length & 0x1000;
				$code[] = $length & 0x0800;
			case 1:  /* 10 - 26 */
				$code[] = $length & 0x0400;
				$code[] = $length & 0x0200;
			case 0:  /* 1 - 9 */
				$code[] = $length & 0x0100;
				$code[] = $length & 0x0080;
				$code[] = $length & 0x0040;
				$code[] = $length & 0x0020;
				$code[] = $length & 0x0010;
				$code[] = $length & 0x0008;
				$code[] = $length & 0x0004;
				$code[] = $length & 0x0002;
				$code[] = $length & 0x0001;
		}
		for ($i = 0; $i < $length; $i += 2) {
			$group = substr($data, $i, 2);
			if (strlen($group) > 1) {
				$c1 = strpos($alphabet, substr($group, 0, 1));
				$c2 = strpos($alphabet, substr($group, 1, 1));
				$ch = $c1 * 45 + $c2;
				$code[] = $ch & 0x400;
				$code[] = $ch & 0x200;
				$code[] = $ch & 0x100;
				$code[] = $ch & 0x080;
				$code[] = $ch & 0x040;
				$code[] = $ch & 0x020;
				$code[] = $ch & 0x010;
				$code[] = $ch & 0x008;
				$code[] = $ch & 0x004;
				$code[] = $ch & 0x002;
				$code[] = $ch & 0x001;
			} else {
				$ch = strpos($alphabet, $group);
				$code[] = $ch & 0x020;
				$code[] = $ch & 0x010;
				$code[] = $ch & 0x008;
				$code[] = $ch & 0x004;
				$code[] = $ch & 0x002;
				$code[] = $ch & 0x001;
			}
		}
		return $code;
	}

	private function qr_encode_binary($data, $version_group) {
		$code = array(0, 1, 0, 0);
		$length = strlen($data);
		switch ($version_group) {
			case 2:  /* 27 - 40 */
			case 1:  /* 10 - 26 */
				$code[] = $length & 0x8000;
				$code[] = $length & 0x4000;
				$code[] = $length & 0x2000;
				$code[] = $length & 0x1000;
				$code[] = $length & 0x0800;
				$code[] = $length & 0x0400;
				$code[] = $length & 0x0200;
				$code[] = $length & 0x0100;
			case 0:  /* 1 - 9 */
				$code[] = $length & 0x0080;
				$code[] = $length & 0x0040;
				$code[] = $length & 0x0020;
				$code[] = $length & 0x0010;
				$code[] = $length & 0x0008;
				$code[] = $length & 0x0004;
				$code[] = $length & 0x0002;
				$code[] = $length & 0x0001;
		}
		for ($i = 0; $i < $length; $i++) {
			$ch = ord(substr($data, $i, 1));
			$code[] = $ch & 0x80;
			$code[] = $ch & 0x40;
			$code[] = $ch & 0x20;
			$code[] = $ch & 0x10;
			$code[] = $ch & 0x08;
			$code[] = $ch & 0x04;
			$code[] = $ch & 0x02;
			$code[] = $ch & 0x01;
		}
		return $code;
	}

	private function qr_encode_kanji($data, $version_group) {
		$code = array(1, 0, 0, 0);
		$length = strlen($data);
		switch ($version_group) {
			case 2:  /* 27 - 40 */
				$code[] = $length & 0x1000;
				$code[] = $length & 0x0800;
			case 1:  /* 10 - 26 */
				$code[] = $length & 0x0400;
				$code[] = $length & 0x0200;
			case 0:  /* 1 - 9 */
				$code[] = $length & 0x0100;
				$code[] = $length & 0x0080;
				$code[] = $length & 0x0040;
				$code[] = $length & 0x0020;
				$code[] = $length & 0x0010;
				$code[] = $length & 0x0008;
				$code[] = $length & 0x0004;
				$code[] = $length & 0x0002;
		}
		for ($i = 0; $i < $length; $i += 2) {
			$group = substr($data, $i, 2);
			$c1 = ord(substr($group, 0, 1));
			$c2 = ord(substr($group, 1, 1));
			if ($c1 >= 0x81 && $c1 <= 0x9F && $c2 >= 0x40 && $c2 <= 0xFC) {
				$ch = ($c1 - 0x81) * 0xC0 + ($c2 - 0x40);
			} else if (
				($c1 >= 0xE0 && $c1 <= 0xEA && $c2 >= 0x40 && $c2 <= 0xFC) ||
				($c1 == 0xEB && $c2 >= 0x40 && $c2 <= 0xBF)
			) {
				$ch = ($c1 - 0xC1) * 0xC0 + ($c2 - 0x40);
			} else {
				$ch = 0;
			}
			$code[] = $ch & 0x1000;
			$code[] = $ch & 0x0800;
			$code[] = $ch & 0x0400;
			$code[] = $ch & 0x0200;
			$code[] = $ch & 0x0100;
			$code[] = $ch & 0x0080;
			$code[] = $ch & 0x0040;
			$code[] = $ch & 0x0020;
			$code[] = $ch & 0x0010;
			$code[] = $ch & 0x0008;
			$code[] = $ch & 0x0004;
			$code[] = $ch & 0x0002;
			$code[] = $ch & 0x0001;
		}
		return $code;
	}

	private function qr_encode_ec($data, $ec_params, $version) {
		$blocks = $this->qr_ec_split($data, $ec_params);
		$ec_blocks = array();
		for ($i = 0, $n = count($blocks); $i < $n; $i++) {
			$ec_blocks[] = $this->qr_ec_divide($blocks[$i], $ec_params);
		}
		$data = $this->qr_ec_interleave($blocks);
		$ec_data = $this->qr_ec_interleave($ec_blocks);
		$code = array();
		foreach ($data as $ch) {
			$code[] = $ch & 0x80;
			$code[] = $ch & 0x40;
			$code[] = $ch & 0x20;
			$code[] = $ch & 0x10;
			$code[] = $ch & 0x08;
			$code[] = $ch & 0x04;
			$code[] = $ch & 0x02;
			$code[] = $ch & 0x01;
		}
		foreach ($ec_data as $ch) {
			$code[] = $ch & 0x80;
			$code[] = $ch & 0x40;
			$code[] = $ch & 0x20;
			$code[] = $ch & 0x10;
			$code[] = $ch & 0x08;
			$code[] = $ch & 0x04;
			$code[] = $ch & 0x02;
			$code[] = $ch & 0x01;
		}
		for ($n = $this->qr_remainder_bits[$version - 1]; $n > 0; $n--) {
			$code[] = 0;
		}
		return $code;
	}

	private function qr_ec_split($data, $ec_params) {
		$blocks = array();
		$offset = 0;
		for ($i = $ec_params[2], $length = $ec_params[3]; $i > 0; $i--) {
			$blocks[] = array_slice($data, $offset, $length);
			$offset += $length;
		}
		for ($i = $ec_params[4], $length = $ec_params[5]; $i > 0; $i--) {
			$blocks[] = array_slice($data, $offset, $length);
			$offset += $length;
		}
		return $blocks;
	}

	private function qr_ec_divide($data, $ec_params) {
		$num_data = count($data);
		$num_error = $ec_params[1];
		$generator = $this->qr_ec_polynomials[$num_error];
		$message = $data;
		for ($i = 0; $i < $num_error; $i++) {
			$message[] = 0;
		}
		for ($i = 0; $i < $num_data; $i++) {
			if ($message[$i]) {
				$leadterm = $this->qr_log[$message[$i]];
				for ($j = 0; $j <= $num_error; $j++) {
					$term = ($generator[$j] + $leadterm) % 255;
					$message[$i + $j] ^= $this->qr_exp[$term];
				}
			}
		}
		return array_slice($message, $num_data, $num_error);
	}

	private function qr_ec_interleave($blocks) {
		$data = array();
		$num_blocks = count($blocks);
		for ($offset = 0; true; $offset++) {
			$break = true;
			for ($i = 0; $i < $num_blocks; $i++) {
				if (isset($blocks[$i][$offset])) {
					$data[] = $blocks[$i][$offset];
					$break = false;
				}
			}
			if ($break) break;
		}
		return $data;
	}

	private function qr_create_matrix($version, $data) {
		$size = $version * 4 + 17;
		$matrix = array();
		for ($i = 0; $i < $size; $i++) {
			$row = array();
			for ($j = 0; $j < $size; $j++) {
				$row[] = 0;
			}
			$matrix[] = $row;
		}
		/* Finder patterns. */
		for ($i = 0; $i < 8; $i++) {
			for ($j = 0; $j < 8; $j++) {
				$m = (($i == 7 || $j == 7) ? 2 :
				     (($i == 0 || $j == 0 || $i == 6 || $j == 6) ? 3 :
				     (($i == 1 || $j == 1 || $i == 5 || $j == 5) ? 2 : 3)));
				$matrix[$i][$j] = $m;
				$matrix[$size - $i - 1][$j] = $m;
				$matrix[$i][$size - $j - 1] = $m;
			}
		}
		/* Alignment patterns. */
		if ($version >= 2) {
			$alignment = $this->qr_alignment_patterns[$version - 2];
			foreach ($alignment as $i) {
				foreach ($alignment as $j) {
					if (!$matrix[$i][$j]) {
						for ($ii = -2; $ii <= 2; $ii++) {
							for ($jj = -2; $jj <= 2; $jj++) {
								$m = (max(abs($ii), abs($jj)) & 1) ^ 3;
								$matrix[$i + $ii][$j + $jj] = $m;
							}
						}
					}
				}
			}
		}
		/* Timing patterns. */
		for ($i = $size - 9; $i >= 8; $i--) {
			$matrix[$i][6] = ($i & 1) ^ 3;
			$matrix[6][$i] = ($i & 1) ^ 3;
		}
		/* Dark module. Such an ominous name for such an innocuous thing. */
		$matrix[$size - 8][8] = 3;
		/* Format information area. */
		for ($i = 0; $i <= 8; $i++) {
			if (!$matrix[$i][8]) $matrix[$i][8] = 1;
			if (!$matrix[8][$i]) $matrix[8][$i] = 1;
			if ($i && !$matrix[$size - $i][8]) $matrix[$size - $i][8] = 1;
			if ($i && !$matrix[8][$size - $i]) $matrix[8][$size - $i] = 1;
		}
		/* Version information area. */
		if ($version >= 7) {
			for ($i = 9; $i < 12; $i++) {
				for ($j = 0; $j < 6; $j++) {
					$matrix[$size - $i][$j] = 1;
					$matrix[$j][$size - $i] = 1;
				}
			}
		}
		/* Data. */
		$col = $size - 1;
		$row = $size - 1;
		$dir = -1;
		$offset = 0;
		$length = count($data);
		while ($col > 0 && $offset < $length) {
			if (!$matrix[$row][$col]) {
				$matrix[$row][$col] = $data[$offset] ? 5 : 4;
				$offset++;
			}
			if (!$matrix[$row][$col - 1]) {
				$matrix[$row][$col - 1] = $data[$offset] ? 5 : 4;
				$offset++;
			}
			$row += $dir;
			if ($row < 0 || $row >= $size) {
				$dir = -$dir;
				$row += $dir;
				$col -= 2;
				if ($col == 6) $col--;
			}
		}
		return array($size, $matrix);
	}

	private function qr_apply_best_mask($matrix, $size) {
		$best_mask = 0;
		$best_matrix = $this->qr_apply_mask($matrix, $size, $best_mask);
		$best_penalty = $this->qr_penalty($best_matrix, $size);
		for ($test_mask = 1; $test_mask < 8; $test_mask++) {
			$test_matrix = $this->qr_apply_mask($matrix, $size, $test_mask);
			$test_penalty = $this->qr_penalty($test_matrix, $size);
			if ($test_penalty < $best_penalty) {
				$best_mask = $test_mask;
				$best_matrix = $test_matrix;
				$best_penalty = $test_penalty;
			}
		}
		return array($best_mask, $best_matrix);
	}

	private function qr_apply_mask($matrix, $size, $mask) {
		for ($i = 0; $i < $size; $i++) {
			for ($j = 0; $j < $size; $j++) {
				if ($matrix[$i][$j] >= 4) {
					if ($this->qr_mask($mask, $i, $j)) {
						$matrix[$i][$j] ^= 1;
					}
				}
			}
		}
		return $matrix;
	}

	private function qr_mask($mask, $r, $c) {
		switch ($mask) {
			case 0: return !( ($r + $c) % 2 );
			case 1: return !( ($r     ) % 2 );
			case 2: return !( (     $c) % 3 );
			case 3: return !( ($r + $c) % 3 );
			case 4: return !( (floor(($r) / 2) + floor(($c) / 3)) % 2 );
			case 5: return !( ((($r * $c) % 2) + (($r * $c) % 3))     );
			case 6: return !( ((($r * $c) % 2) + (($r * $c) % 3)) % 2 );
			case 7: return !( ((($r + $c) % 2) + (($r * $c) % 3)) % 2 );
		}
	}

	private function qr_penalty(&$matrix, $size) {
		$score  = $this->qr_penalty_1($matrix, $size);
		$score += $this->qr_penalty_2($matrix, $size);
		$score += $this->qr_penalty_3($matrix, $size);
		$score += $this->qr_penalty_4($matrix, $size);
		return $score;
	}

	private function qr_penalty_1(&$matrix, $size) {
		$score = 0;
		for ($i = 0; $i < $size; $i++) {
			$rowvalue = 0;
			$rowcount = 0;
			$colvalue = 0;
			$colcount = 0;
			for ($j = 0; $j < $size; $j++) {
				$rv = ($matrix[$i][$j] == 5 || $matrix[$i][$j] == 3) ? 1 : 0;
				$cv = ($matrix[$j][$i] == 5 || $matrix[$j][$i] == 3) ? 1 : 0;
				if ($rv == $rowvalue) {
					$rowcount++;
				} else {
					if ($rowcount >= 5) $score += $rowcount - 2;
					$rowvalue = $rv;
					$rowcount = 1;
				}
				if ($cv == $colvalue) {
					$colcount++;
				} else {
					if ($colcount >= 5) $score += $colcount - 2;
					$colvalue = $cv;
					$colcount = 1;
				}
			}
			if ($rowcount >= 5) $score += $rowcount - 2;
			if ($colcount >= 5) $score += $colcount - 2;
		}
		return $score;
	}

	private function qr_penalty_2(&$matrix, $size) {
		$score = 0;
		for ($i = 1; $i < $size; $i++) {
			for ($j = 1; $j < $size; $j++) {
				$v1 = $matrix[$i - 1][$j - 1];
				$v2 = $matrix[$i - 1][$j    ];
				$v3 = $matrix[$i    ][$j - 1];
				$v4 = $matrix[$i    ][$j    ];
				$v1 = ($v1 == 5 || $v1 == 3) ? 1 : 0;
				$v2 = ($v2 == 5 || $v2 == 3) ? 1 : 0;
				$v3 = ($v3 == 5 || $v3 == 3) ? 1 : 0;
				$v4 = ($v4 == 5 || $v4 == 3) ? 1 : 0;
				if ($v1 == $v2 && $v2 == $v3 && $v3 == $v4) $score += 3;
			}
		}
		return $score;
	}

	private function qr_penalty_3(&$matrix, $size) {
		$score = 0;
		for ($i = 0; $i < $size; $i++) {
			$rowvalue = 0;
			$colvalue = 0;
			for ($j = 0; $j < 11; $j++) {
				$rv = ($matrix[$i][$j] == 5 || $matrix[$i][$j] == 3) ? 1 : 0;
				$cv = ($matrix[$j][$i] == 5 || $matrix[$j][$i] == 3) ? 1 : 0;
				$rowvalue = (($rowvalue << 1) & 0x7FF) | $rv;
				$colvalue = (($colvalue << 1) & 0x7FF) | $cv;
			}
			if ($rowvalue == 0x5D0 || $rowvalue == 0x5D) $score += 40;
			if ($colvalue == 0x5D0 || $colvalue == 0x5D) $score += 40;
			for ($j = 11; $j < $size; $j++) {
				$rv = ($matrix[$i][$j] == 5 || $matrix[$i][$j] == 3) ? 1 : 0;
				$cv = ($matrix[$j][$i] == 5 || $matrix[$j][$i] == 3) ? 1 : 0;
				$rowvalue = (($rowvalue << 1) & 0x7FF) | $rv;
				$colvalue = (($colvalue << 1) & 0x7FF) | $cv;
				if ($rowvalue == 0x5D0 || $rowvalue == 0x5D) $score += 40;
				if ($colvalue == 0x5D0 || $colvalue == 0x5D) $score += 40;
			}
		}
		return $score;
	}

	private function qr_penalty_4(&$matrix, $size) {
		$dark = 0;
		for ($i = 0; $i < $size; $i++) {
			for ($j = 0; $j < $size; $j++) {
				if ($matrix[$i][$j] == 5 || $matrix[$i][$j] == 3) {
					$dark++;
				}
			}
		}
		$dark *= 20;
		$dark /= $size * $size;
		$a = abs(floor($dark) - 10);
		$b = abs(ceil($dark) - 10);
		return min($a, $b) * 10;
	}

	private function qr_finalize_matrix(
		$matrix, $size, $ecl, $mask, $version
	) {
		/* Format Info */
		$format = $this->qr_format_info[$ecl * 8 + $mask];
		$matrix[8][0] = $format[0];
		$matrix[8][1] = $format[1];
		$matrix[8][2] = $format[2];
		$matrix[8][3] = $format[3];
		$matrix[8][4] = $format[4];
		$matrix[8][5] = $format[5];
		$matrix[8][7] = $format[6];
		$matrix[8][8] = $format[7];
		$matrix[7][8] = $format[8];
		$matrix[5][8] = $format[9];
		$matrix[4][8] = $format[10];
		$matrix[3][8] = $format[11];
		$matrix[2][8] = $format[12];
		$matrix[1][8] = $format[13];
		$matrix[0][8] = $format[14];
		$matrix[$size - 1][8] = $format[0];
		$matrix[$size - 2][8] = $format[1];
		$matrix[$size - 3][8] = $format[2];
		$matrix[$size - 4][8] = $format[3];
		$matrix[$size - 5][8] = $format[4];
		$matrix[$size - 6][8] = $format[5];
		$matrix[$size - 7][8] = $format[6];
		$matrix[8][$size - 8] = $format[7];
		$matrix[8][$size - 7] = $format[8];
		$matrix[8][$size - 6] = $format[9];
		$matrix[8][$size - 5] = $format[10];
		$matrix[8][$size - 4] = $format[11];
		$matrix[8][$size - 3] = $format[12];
		$matrix[8][$size - 2] = $format[13];
		$matrix[8][$size - 1] = $format[14];
		/* Version Info */
		if ($version >= 7) {
			$version = $this->qr_version_info[$version - 7];
			for ($i = 0; $i < 18; $i++) {
				$r = $size - 9 - ($i % 3);
				$c = 5 - floor($i / 3);
				$matrix[$r][$c] = $version[$i];
				$matrix[$c][$r] = $version[$i];
			}
		}
		/* Patterns & Data */
		for ($i = 0; $i < $size; $i++) {
			for ($j = 0; $j < $size; $j++) {
				$matrix[$i][$j] &= 1;
			}
		}
		return $matrix;
	}

	/*  maximum encodable characters = $qr_capacity [ (version - 1) ]  */
	/*    [ (0 for L, 1 for M, 2 for Q, 3 for H)                    ]  */
	/*    [ (0 for numeric, 1 for alpha, 2 for binary, 3 for kanji) ]  */
	private $qr_capacity = array(
		array(array(  41,   25,   17,   10), array(  34,   20,   14,    8),
		      array(  27,   16,   11,    7), array(  17,   10,    7,    4)),
		array(array(  77,   47,   32,   20), array(  63,   38,   26,   16),
		      array(  48,   29,   20,   12), array(  34,   20,   14,    8)),
		array(array( 127,   77,   53,   32), array( 101,   61,   42,   26),
		      array(  77,   47,   32,   20), array(  58,   35,   24,   15)),
		array(array( 187,  114,   78,   48), array( 149,   90,   62,   38),
		      array( 111,   67,   46,   28), array(  82,   50,   34,   21)),
		array(array( 255,  154,  106,   65), array( 202,  122,   84,   52),
		      array( 144,   87,   60,   37), array( 106,   64,   44,   27)),
		array(array( 322,  195,  134,   82), array( 255,  154,  106,   65),
		      array( 178,  108,   74,   45), array( 139,   84,   58,   36)),
		array(array( 370,  224,  154,   95), array( 293,  178,  122,   75),
		      array( 207,  125,   86,   53), array( 154,   93,   64,   39)),
		array(array( 461,  279,  192,  118), array( 365,  221,  152,   93),
		      array( 259,  157,  108,   66), array( 202,  122,   84,   52)),
		array(array( 552,  335,  230,  141), array( 432,  262,  180,  111),
		      array( 312,  189,  130,   80), array( 235,  143,   98,   60)),
		array(array( 652,  395,  271,  167), array( 513,  311,  213,  131),
		      array( 364,  221,  151,   93), array( 288,  174,  119,   74)),
		array(array( 772,  468,  321,  198), array( 604,  366,  251,  155),
		      array( 427,  259,  177,  109), array( 331,  200,  137,   85)),
		array(array( 883,  535,  367,  226), array( 691,  419,  287,  177),
		      array( 489,  296,  203,  125), array( 374,  227,  155,   96)),
		array(array(1022,  619,  425,  262), array( 796,  483,  331,  204),
		      array( 580,  352,  241,  149), array( 427,  259,  177,  109)),
		array(array(1101,  667,  458,  282), array( 871,  528,  362,  223),
		      array( 621,  376,  258,  159), array( 468,  283,  194,  120)),
		array(array(1250,  758,  520,  320), array( 991,  600,  412,  254),
		      array( 703,  426,  292,  180), array( 530,  321,  220,  136)),
		array(array(1408,  854,  586,  361), array(1082,  656,  450,  277),
		      array( 775,  470,  322,  198), array( 602,  365,  250,  154)),
		array(array(1548,  938,  644,  397), array(1212,  734,  504,  310),
		      array( 876,  531,  364,  224), array( 674,  408,  280,  173)),
		array(array(1725, 1046,  718,  442), array(1346,  816,  560,  345),
		      array( 948,  574,  394,  243), array( 746,  452,  310,  191)),
		array(array(1903, 1153,  792,  488), array(1500,  909,  624,  384),
		      array(1063,  644,  442,  272), array( 813,  493,  338,  208)),
		array(array(2061, 1249,  858,  528), array(1600,  970,  666,  410),
		      array(1159,  702,  482,  297), array( 919,  557,  382,  235)),
		array(array(2232, 1352,  929,  572), array(1708, 1035,  711,  438),
		      array(1224,  742,  509,  314), array( 969,  587,  403,  248)),
		array(array(2409, 1460, 1003,  618), array(1872, 1134,  779,  480),
		      array(1358,  823,  565,  348), array(1056,  640,  439,  270)),
		array(array(2620, 1588, 1091,  672), array(2059, 1248,  857,  528),
		      array(1468,  890,  611,  376), array(1108,  672,  461,  284)),
		array(array(2812, 1704, 1171,  721), array(2188, 1326,  911,  561),
		      array(1588,  963,  661,  407), array(1228,  744,  511,  315)),
		array(array(3057, 1853, 1273,  784), array(2395, 1451,  997,  614),
		      array(1718, 1041,  715,  440), array(1286,  779,  535,  330)),
		array(array(3283, 1990, 1367,  842), array(2544, 1542, 1059,  652),
		      array(1804, 1094,  751,  462), array(1425,  864,  593,  365)),
		array(array(3517, 2132, 1465,  902), array(2701, 1637, 1125,  692),
		      array(1933, 1172,  805,  496), array(1501,  910,  625,  385)),
		array(array(3669, 2223, 1528,  940), array(2857, 1732, 1190,  732),
		      array(2085, 1263,  868,  534), array(1581,  958,  658,  405)),
		array(array(3909, 2369, 1628, 1002), array(3035, 1839, 1264,  778),
		      array(2181, 1322,  908,  559), array(1677, 1016,  698,  430)),
		array(array(4158, 2520, 1732, 1066), array(3289, 1994, 1370,  843),
		      array(2358, 1429,  982,  604), array(1782, 1080,  742,  457)),
		array(array(4417, 2677, 1840, 1132), array(3486, 2113, 1452,  894),
		      array(2473, 1499, 1030,  634), array(1897, 1150,  790,  486)),
		array(array(4686, 2840, 1952, 1201), array(3693, 2238, 1538,  947),
		      array(2670, 1618, 1112,  684), array(2022, 1226,  842,  518)),
		array(array(4965, 3009, 2068, 1273), array(3909, 2369, 1628, 1002),
		      array(2805, 1700, 1168,  719), array(2157, 1307,  898,  553)),
		array(array(5253, 3183, 2188, 1347), array(4134, 2506, 1722, 1060),
		      array(2949, 1787, 1228,  756), array(2301, 1394,  958,  590)),
		array(array(5529, 3351, 2303, 1417), array(4343, 2632, 1809, 1113),
		      array(3081, 1867, 1283,  790), array(2361, 1431,  983,  605)),
		array(array(5836, 3537, 2431, 1496), array(4588, 2780, 1911, 1176),
		      array(3244, 1966, 1351,  832), array(2524, 1530, 1051,  647)),
		array(array(6153, 3729, 2563, 1577), array(4775, 2894, 1989, 1224),
		      array(3417, 2071, 1423,  876), array(2625, 1591, 1093,  673)),
		array(array(6479, 3927, 2699, 1661), array(5039, 3054, 2099, 1292),
		      array(3599, 2181, 1499,  923), array(2735, 1658, 1139,  701)),
		array(array(6743, 4087, 2809, 1729), array(5313, 3220, 2213, 1362),
		      array(3791, 2298, 1579,  972), array(2927, 1774, 1219,  750)),
		array(array(7089, 4296, 2953, 1817), array(5596, 3391, 2331, 1435),
		      array(3993, 2420, 1663, 1024), array(3057, 1852, 1273,  784)),
	);

	/*  $qr_ec_params[                                              */
	/*    4 * (version - 1) + (0 for L, 1 for M, 2 for Q, 3 for H)  */
	/*  ] = array(                                                  */
	/*    total number of data codewords,                           */
	/*    number of error correction codewords per block,           */
	/*    number of blocks in first group,                          */
	/*    number of data codewords per block in first group,        */
	/*    number of blocks in second group,                         */
	/*    number of data codewords per block in second group        */
	/*  );                                                          */
	private $qr_ec_params = array(
		array(   19,  7,  1,  19,  0,   0 ),
		array(   16, 10,  1,  16,  0,   0 ),
		array(   13, 13,  1,  13,  0,   0 ),
		array(    9, 17,  1,   9,  0,   0 ),
		array(   34, 10,  1,  34,  0,   0 ),
		array(   28, 16,  1,  28,  0,   0 ),
		array(   22, 22,  1,  22,  0,   0 ),
		array(   16, 28,  1,  16,  0,   0 ),
		array(   55, 15,  1,  55,  0,   0 ),
		array(   44, 26,  1,  44,  0,   0 ),
		array(   34, 18,  2,  17,  0,   0 ),
		array(   26, 22,  2,  13,  0,   0 ),
		array(   80, 20,  1,  80,  0,   0 ),
		array(   64, 18,  2,  32,  0,   0 ),
		array(   48, 26,  2,  24,  0,   0 ),
		array(   36, 16,  4,   9,  0,   0 ),
		array(  108, 26,  1, 108,  0,   0 ),
		array(   86, 24,  2,  43,  0,   0 ),
		array(   62, 18,  2,  15,  2,  16 ),
		array(   46, 22,  2,  11,  2,  12 ),
		array(  136, 18,  2,  68,  0,   0 ),
		array(  108, 16,  4,  27,  0,   0 ),
		array(   76, 24,  4,  19,  0,   0 ),
		array(   60, 28,  4,  15,  0,   0 ),
		array(  156, 20,  2,  78,  0,   0 ),
		array(  124, 18,  4,  31,  0,   0 ),
		array(   88, 18,  2,  14,  4,  15 ),
		array(   66, 26,  4,  13,  1,  14 ),
		array(  194, 24,  2,  97,  0,   0 ),
		array(  154, 22,  2,  38,  2,  39 ),
		array(  110, 22,  4,  18,  2,  19 ),
		array(   86, 26,  4,  14,  2,  15 ),
		array(  232, 30,  2, 116,  0,   0 ),
		array(  182, 22,  3,  36,  2,  37 ),
		array(  132, 20,  4,  16,  4,  17 ),
		array(  100, 24,  4,  12,  4,  13 ),
		array(  274, 18,  2,  68,  2,  69 ),
		array(  216, 26,  4,  43,  1,  44 ),
		array(  154, 24,  6,  19,  2,  20 ),
		array(  122, 28,  6,  15,  2,  16 ),
		array(  324, 20,  4,  81,  0,   0 ),
		array(  254, 30,  1,  50,  4,  51 ),
		array(  180, 28,  4,  22,  4,  23 ),
		array(  140, 24,  3,  12,  8,  13 ),
		array(  370, 24,  2,  92,  2,  93 ),
		array(  290, 22,  6,  36,  2,  37 ),
		array(  206, 26,  4,  20,  6,  21 ),
		array(  158, 28,  7,  14,  4,  15 ),
		array(  428, 26,  4, 107,  0,   0 ),
		array(  334, 22,  8,  37,  1,  38 ),
		array(  244, 24,  8,  20,  4,  21 ),
		array(  180, 22, 12,  11,  4,  12 ),
		array(  461, 30,  3, 115,  1, 116 ),
		array(  365, 24,  4,  40,  5,  41 ),
		array(  261, 20, 11,  16,  5,  17 ),
		array(  197, 24, 11,  12,  5,  13 ),
		array(  523, 22,  5,  87,  1,  88 ),
		array(  415, 24,  5,  41,  5,  42 ),
		array(  295, 30,  5,  24,  7,  25 ),
		array(  223, 24, 11,  12,  7,  13 ),
		array(  589, 24,  5,  98,  1,  99 ),
		array(  453, 28,  7,  45,  3,  46 ),
		array(  325, 24, 15,  19,  2,  20 ),
		array(  253, 30,  3,  15, 13,  16 ),
		array(  647, 28,  1, 107,  5, 108 ),
		array(  507, 28, 10,  46,  1,  47 ),
		array(  367, 28,  1,  22, 15,  23 ),
		array(  283, 28,  2,  14, 17,  15 ),
		array(  721, 30,  5, 120,  1, 121 ),
		array(  563, 26,  9,  43,  4,  44 ),
		array(  397, 28, 17,  22,  1,  23 ),
		array(  313, 28,  2,  14, 19,  15 ),
		array(  795, 28,  3, 113,  4, 114 ),
		array(  627, 26,  3,  44, 11,  45 ),
		array(  445, 26, 17,  21,  4,  22 ),
		array(  341, 26,  9,  13, 16,  14 ),
		array(  861, 28,  3, 107,  5, 108 ),
		array(  669, 26,  3,  41, 13,  42 ),
		array(  485, 30, 15,  24,  5,  25 ),
		array(  385, 28, 15,  15, 10,  16 ),
		array(  932, 28,  4, 116,  4, 117 ),
		array(  714, 26, 17,  42,  0,   0 ),
		array(  512, 28, 17,  22,  6,  23 ),
		array(  406, 30, 19,  16,  6,  17 ),
		array( 1006, 28,  2, 111,  7, 112 ),
		array(  782, 28, 17,  46,  0,   0 ),
		array(  568, 30,  7,  24, 16,  25 ),
		array(  442, 24, 34,  13,  0,   0 ),
		array( 1094, 30,  4, 121,  5, 122 ),
		array(  860, 28,  4,  47, 14,  48 ),
		array(  614, 30, 11,  24, 14,  25 ),
		array(  464, 30, 16,  15, 14,  16 ),
		array( 1174, 30,  6, 117,  4, 118 ),
		array(  914, 28,  6,  45, 14,  46 ),
		array(  664, 30, 11,  24, 16,  25 ),
		array(  514, 30, 30,  16,  2,  17 ),
		array( 1276, 26,  8, 106,  4, 107 ),
		array( 1000, 28,  8,  47, 13,  48 ),
		array(  718, 30,  7,  24, 22,  25 ),
		array(  538, 30, 22,  15, 13,  16 ),
		array( 1370, 28, 10, 114,  2, 115 ),
		array( 1062, 28, 19,  46,  4,  47 ),
		array(  754, 28, 28,  22,  6,  23 ),
		array(  596, 30, 33,  16,  4,  17 ),
		array( 1468, 30,  8, 122,  4, 123 ),
		array( 1128, 28, 22,  45,  3,  46 ),
		array(  808, 30,  8,  23, 26,  24 ),
		array(  628, 30, 12,  15, 28,  16 ),
		array( 1531, 30,  3, 117, 10, 118 ),
		array( 1193, 28,  3,  45, 23,  46 ),
		array(  871, 30,  4,  24, 31,  25 ),
		array(  661, 30, 11,  15, 31,  16 ),
		array( 1631, 30,  7, 116,  7, 117 ),
		array( 1267, 28, 21,  45,  7,  46 ),
		array(  911, 30,  1,  23, 37,  24 ),
		array(  701, 30, 19,  15, 26,  16 ),
		array( 1735, 30,  5, 115, 10, 116 ),
		array( 1373, 28, 19,  47, 10,  48 ),
		array(  985, 30, 15,  24, 25,  25 ),
		array(  745, 30, 23,  15, 25,  16 ),
		array( 1843, 30, 13, 115,  3, 116 ),
		array( 1455, 28,  2,  46, 29,  47 ),
		array( 1033, 30, 42,  24,  1,  25 ),
		array(  793, 30, 23,  15, 28,  16 ),
		array( 1955, 30, 17, 115,  0,   0 ),
		array( 1541, 28, 10,  46, 23,  47 ),
		array( 1115, 30, 10,  24, 35,  25 ),
		array(  845, 30, 19,  15, 35,  16 ),
		array( 2071, 30, 17, 115,  1, 116 ),
		array( 1631, 28, 14,  46, 21,  47 ),
		array( 1171, 30, 29,  24, 19,  25 ),
		array(  901, 30, 11,  15, 46,  16 ),
		array( 2191, 30, 13, 115,  6, 116 ),
		array( 1725, 28, 14,  46, 23,  47 ),
		array( 1231, 30, 44,  24,  7,  25 ),
		array(  961, 30, 59,  16,  1,  17 ),
		array( 2306, 30, 12, 121,  7, 122 ),
		array( 1812, 28, 12,  47, 26,  48 ),
		array( 1286, 30, 39,  24, 14,  25 ),
		array(  986, 30, 22,  15, 41,  16 ),
		array( 2434, 30,  6, 121, 14, 122 ),
		array( 1914, 28,  6,  47, 34,  48 ),
		array( 1354, 30, 46,  24, 10,  25 ),
		array( 1054, 30,  2,  15, 64,  16 ),
		array( 2566, 30, 17, 122,  4, 123 ),
		array( 1992, 28, 29,  46, 14,  47 ),
		array( 1426, 30, 49,  24, 10,  25 ),
		array( 1096, 30, 24,  15, 46,  16 ),
		array( 2702, 30,  4, 122, 18, 123 ),
		array( 2102, 28, 13,  46, 32,  47 ),
		array( 1502, 30, 48,  24, 14,  25 ),
		array( 1142, 30, 42,  15, 32,  16 ),
		array( 2812, 30, 20, 117,  4, 118 ),
		array( 2216, 28, 40,  47,  7,  48 ),
		array( 1582, 30, 43,  24, 22,  25 ),
		array( 1222, 30, 10,  15, 67,  16 ),
		array( 2956, 30, 19, 118,  6, 119 ),
		array( 2334, 28, 18,  47, 31,  48 ),
		array( 1666, 30, 34,  24, 34,  25 ),
		array( 1276, 30, 20,  15, 61,  16 ),
	);

	private $qr_ec_polynomials = array(
		7 => array(
			0, 87, 229, 146, 149, 238, 102, 21
		),
		10 => array(
			0, 251, 67, 46, 61, 118, 70, 64, 94, 32, 45
		),
		13 => array(
			0, 74, 152, 176, 100, 86, 100,
			106, 104, 130, 218, 206, 140, 78
		),
		15 => array(
			0, 8, 183, 61, 91, 202, 37, 51,
			58, 58, 237, 140, 124, 5, 99, 105
		),
		16 => array(
			0, 120, 104, 107, 109, 102, 161, 76, 3,
			91, 191, 147, 169, 182, 194, 225, 120
		),
		17 => array(
			0, 43, 139, 206, 78, 43, 239, 123, 206,
			214, 147, 24, 99, 150, 39, 243, 163, 136
		),
		18 => array(
			0, 215, 234, 158, 94, 184, 97, 118, 170, 79,
			187, 152, 148, 252, 179, 5, 98, 96, 153
		),
		20 => array(
			0, 17, 60, 79, 50, 61, 163, 26, 187, 202, 180,
			221, 225, 83, 239, 156, 164, 212, 212, 188, 190
		),
		22 => array(
			0, 210, 171, 247, 242, 93, 230, 14, 109, 221, 53, 200,
			74, 8, 172, 98, 80, 219, 134, 160, 105, 165, 231
		),
		24 => array(
			0, 229, 121, 135, 48, 211, 117, 251, 126, 159, 180, 169,
			152, 192, 226, 228, 218, 111, 0, 117, 232, 87, 96, 227, 21
		),
		26 => array(
			0, 173, 125, 158, 2, 103, 182, 118, 17,
			145, 201, 111, 28, 165, 53, 161, 21, 245,
			142, 13, 102, 48, 227, 153, 145, 218, 70
		),
		28 => array(
			0, 168, 223, 200, 104, 224, 234, 108, 180,
			110, 190, 195, 147, 205, 27, 232, 201, 21, 43,
			245, 87, 42, 195, 212, 119, 242, 37, 9, 123
		),
		30 => array(
			0, 41, 173, 145, 152, 216, 31, 179, 182, 50, 48,
			110, 86, 239, 96, 222, 125, 42, 173, 226, 193,
			224, 130, 156, 37, 251, 216, 238, 40, 192, 180
		),
	);

	private $qr_log = array(
		  0,   0,   1,  25,   2,  50,  26, 198,
		  3, 223,  51, 238,  27, 104, 199,  75,
		  4, 100, 224,  14,  52, 141, 239, 129,
		 28, 193, 105, 248, 200,   8,  76, 113,
		  5, 138, 101,  47, 225,  36,  15,  33,
		 53, 147, 142, 218, 240,  18, 130,  69,
		 29, 181, 194, 125, 106,  39, 249, 185,
		201, 154,   9, 120,  77, 228, 114, 166,
		  6, 191, 139,  98, 102, 221,  48, 253,
		226, 152,  37, 179,  16, 145,  34, 136,
		 54, 208, 148, 206, 143, 150, 219, 189,
		241, 210,  19,  92, 131,  56,  70,  64,
		 30,  66, 182, 163, 195,  72, 126, 110,
		107,  58,  40,  84, 250, 133, 186,  61,
		202,  94, 155, 159,  10,  21, 121,  43,
		 78, 212, 229, 172, 115, 243, 167,  87,
		  7, 112, 192, 247, 140, 128,  99,  13,
		103,  74, 222, 237,  49, 197, 254,  24,
		227, 165, 153, 119,  38, 184, 180, 124,
		 17,  68, 146, 217,  35,  32, 137,  46,
		 55,  63, 209,  91, 149, 188, 207, 205,
		144, 135, 151, 178, 220, 252, 190,  97,
		242,  86, 211, 171,  20,  42,  93, 158,
		132,  60,  57,  83,  71, 109,  65, 162,
		 31,  45,  67, 216, 183, 123, 164, 118,
		196,  23,  73, 236, 127,  12, 111, 246,
		108, 161,  59,  82,  41, 157,  85, 170,
		251,  96, 134, 177, 187, 204,  62,  90,
		203,  89,  95, 176, 156, 169, 160,  81,
		 11, 245,  22, 235, 122, 117,  44, 215,
		 79, 174, 213, 233, 230, 231, 173, 232,
		116, 214, 244, 234, 168,  80,  88, 175,
	);

	private $qr_exp = array(
		  1,   2,   4,   8,  16,  32,  64, 128,
		 29,  58, 116, 232, 205, 135,  19,  38,
		 76, 152,  45,  90, 180, 117, 234, 201,
		143,   3,   6,  12,  24,  48,  96, 192,
		157,  39,  78, 156,  37,  74, 148,  53,
		106, 212, 181, 119, 238, 193, 159,  35,
		 70, 140,   5,  10,  20,  40,  80, 160,
		 93, 186, 105, 210, 185, 111, 222, 161,
		 95, 190,  97, 194, 153,  47,  94, 188,
		101, 202, 137,  15,  30,  60, 120, 240,
		253, 231, 211, 187, 107, 214, 177, 127,
		254, 225, 223, 163,  91, 182, 113, 226,
		217, 175,  67, 134,  17,  34,  68, 136,
		 13,  26,  52, 104, 208, 189, 103, 206,
		129,  31,  62, 124, 248, 237, 199, 147,
		 59, 118, 236, 197, 151,  51, 102, 204,
		133,  23,  46,  92, 184, 109, 218, 169,
		 79, 158,  33,  66, 132,  21,  42,  84,
		168,  77, 154,  41,  82, 164,  85, 170,
		 73, 146,  57, 114, 228, 213, 183, 115,
		230, 209, 191,  99, 198, 145,  63, 126,
		252, 229, 215, 179, 123, 246, 241, 255,
		227, 219, 171,  75, 150,  49,  98, 196,
		149,  55, 110, 220, 165,  87, 174,  65,
		130,  25,  50, 100, 200, 141,   7,  14,
		 28,  56, 112, 224, 221, 167,  83, 166,
		 81, 162,  89, 178, 121, 242, 249, 239,
		195, 155,  43,  86, 172,  69, 138,   9,
		 18,  36,  72, 144,  61, 122, 244, 245,
		247, 243, 251, 235, 203, 139,  11,  22,
		 44,  88, 176, 125, 250, 233, 207, 131,
		 27,  54, 108, 216, 173,  71, 142,   1,
	);

	private $qr_remainder_bits = array(
		0, 7, 7, 7, 7, 7, 0, 0, 0, 0, 0, 0, 0, 3, 3, 3, 3, 3, 3, 3,
		4, 4, 4, 4, 4, 4, 4, 3, 3, 3, 3, 3, 3, 3, 0, 0, 0, 0, 0, 0,
	);

	private $qr_alignment_patterns = array(
		array(6, 18),
		array(6, 22),
		array(6, 26),
		array(6, 30),
		array(6, 34),
		array(6, 22, 38),
		array(6, 24, 42),
		array(6, 26, 46),
		array(6, 28, 50),
		array(6, 30, 54),
		array(6, 32, 58),
		array(6, 34, 62),
		array(6, 26, 46, 66),
		array(6, 26, 48, 70),
		array(6, 26, 50, 74),
		array(6, 30, 54, 78),
		array(6, 30, 56, 82),
		array(6, 30, 58, 86),
		array(6, 34, 62, 90),
		array(6, 28, 50, 72,  94),
		array(6, 26, 50, 74,  98),
		array(6, 30, 54, 78, 102),
		array(6, 28, 54, 80, 106),
		array(6, 32, 58, 84, 110),
		array(6, 30, 58, 86, 114),
		array(6, 34, 62, 90, 118),
		array(6, 26, 50, 74,  98, 122),
		array(6, 30, 54, 78, 102, 126),
		array(6, 26, 52, 78, 104, 130),
		array(6, 30, 56, 82, 108, 134),
		array(6, 34, 60, 86, 112, 138),
		array(6, 30, 58, 86, 114, 142),
		array(6, 34, 62, 90, 118, 146),
		array(6, 30, 54, 78, 102, 126, 150),
		array(6, 24, 50, 76, 102, 128, 154),
		array(6, 28, 54, 80, 106, 132, 158),
		array(6, 32, 58, 84, 110, 136, 162),
		array(6, 26, 54, 82, 110, 138, 166),
		array(6, 30, 58, 86, 114, 142, 170),
	);

	/*  format info string = $qr_format_info[            */
	/*    (0 for L, 8 for M, 16 for Q, 24 for H) + mask  */
	/*  ];                                               */
	private $qr_format_info = array(
		array( 1, 1, 1, 0, 1, 1, 1, 1, 1, 0, 0, 0, 1, 0, 0 ),
		array( 1, 1, 1, 0, 0, 1, 0, 1, 1, 1, 1, 0, 0, 1, 1 ),
		array( 1, 1, 1, 1, 1, 0, 1, 1, 0, 1, 0, 1, 0, 1, 0 ),
		array( 1, 1, 1, 1, 0, 0, 0, 1, 0, 0, 1, 1, 1, 0, 1 ),
		array( 1, 1, 0, 0, 1, 1, 0, 0, 0, 1, 0, 1, 1, 1, 1 ),
		array( 1, 1, 0, 0, 0, 1, 1, 0, 0, 0, 1, 1, 0, 0, 0 ),
		array( 1, 1, 0, 1, 1, 0, 0, 0, 1, 0, 0, 0, 0, 0, 1 ),
		array( 1, 1, 0, 1, 0, 0, 1, 0, 1, 1, 1, 0, 1, 1, 0 ),
		array( 1, 0, 1, 0, 1, 0, 0, 0, 0, 0, 1, 0, 0, 1, 0 ),
		array( 1, 0, 1, 0, 0, 0, 1, 0, 0, 1, 0, 0, 1, 0, 1 ),
		array( 1, 0, 1, 1, 1, 1, 0, 0, 1, 1, 1, 1, 1, 0, 0 ),
		array( 1, 0, 1, 1, 0, 1, 1, 0, 1, 0, 0, 1, 0, 1, 1 ),
		array( 1, 0, 0, 0, 1, 0, 1, 1, 1, 1, 1, 1, 0, 0, 1 ),
		array( 1, 0, 0, 0, 0, 0, 0, 1, 1, 0, 0, 1, 1, 1, 0 ),
		array( 1, 0, 0, 1, 1, 1, 1, 1, 0, 0, 1, 0, 1, 1, 1 ),
		array( 1, 0, 0, 1, 0, 1, 0, 1, 0, 1, 0, 0, 0, 0, 0 ),
		array( 0, 1, 1, 0, 1, 0, 1, 0, 1, 0, 1, 1, 1, 1, 1 ),
		array( 0, 1, 1, 0, 0, 0, 0, 0, 1, 1, 0, 1, 0, 0, 0 ),
		array( 0, 1, 1, 1, 1, 1, 1, 0, 0, 1, 1, 0, 0, 0, 1 ),
		array( 0, 1, 1, 1, 0, 1, 0, 0, 0, 0, 0, 0, 1, 1, 0 ),
		array( 0, 1, 0, 0, 1, 0, 0, 1, 0, 1, 1, 0, 1, 0, 0 ),
		array( 0, 1, 0, 0, 0, 0, 1, 1, 0, 0, 0, 0, 0, 1, 1 ),
		array( 0, 1, 0, 1, 1, 1, 0, 1, 1, 0, 1, 1, 0, 1, 0 ),
		array( 0, 1, 0, 1, 0, 1, 1, 1, 1, 1, 0, 1, 1, 0, 1 ),
		array( 0, 0, 1, 0, 1, 1, 0, 1, 0, 0, 0, 1, 0, 0, 1 ),
		array( 0, 0, 1, 0, 0, 1, 1, 1, 0, 1, 1, 1, 1, 1, 0 ),
		array( 0, 0, 1, 1, 1, 0, 0, 1, 1, 1, 0, 0, 1, 1, 1 ),
		array( 0, 0, 1, 1, 0, 0, 1, 1, 1, 0, 1, 0, 0, 0, 0 ),
		array( 0, 0, 0, 0, 1, 1, 1, 0, 1, 1, 0, 0, 0, 1, 0 ),
		array( 0, 0, 0, 0, 0, 1, 0, 0, 1, 0, 1, 0, 1, 0, 1 ),
		array( 0, 0, 0, 1, 1, 0, 1, 0, 0, 0, 0, 1, 1, 0, 0 ),
		array( 0, 0, 0, 1, 0, 0, 0, 0, 0, 1, 1, 1, 0, 1, 1 ),
	);

	/*  version info string = $qr_version_info[ (version - 7) ]  */
	private $qr_version_info = array(
		array( 0, 0, 0, 1, 1, 1, 1, 1, 0, 0, 1, 0, 0, 1, 0, 1, 0, 0 ),
		array( 0, 0, 1, 0, 0, 0, 0, 1, 0, 1, 1, 0, 1, 1, 1, 1, 0, 0 ),
		array( 0, 0, 1, 0, 0, 1, 1, 0, 1, 0, 1, 0, 0, 1, 1, 0, 0, 1 ),
		array( 0, 0, 1, 0, 1, 0, 0, 1, 0, 0, 1, 1, 0, 1, 0, 0, 1, 1 ),
		array( 0, 0, 1, 0, 1, 1, 1, 0, 1, 1, 1, 1, 1, 1, 0, 1, 1, 0 ),
		array( 0, 0, 1, 1, 0, 0, 0, 1, 1, 1, 0, 1, 1, 0, 0, 0, 1, 0 ),
		array( 0, 0, 1, 1, 0, 1, 1, 0, 0, 0, 0, 1, 0, 0, 0, 1, 1, 1 ),
		array( 0, 0, 1, 1, 1, 0, 0, 1, 1, 0, 0, 0, 0, 0, 1, 1, 0, 1 ),
		array( 0, 0, 1, 1, 1, 1, 1, 0, 0, 1, 0, 0, 1, 0, 1, 0, 0, 0 ),
		array( 0, 1, 0, 0, 0, 0, 1, 0, 1, 1, 0, 1, 1, 1, 1, 0, 0, 0 ),
		array( 0, 1, 0, 0, 0, 1, 0, 1, 0, 0, 0, 1, 0, 1, 1, 1, 0, 1 ),
		array( 0, 1, 0, 0, 1, 0, 1, 0, 1, 0, 0, 0, 0, 1, 0, 1, 1, 1 ),
		array( 0, 1, 0, 0, 1, 1, 0, 1, 0, 1, 0, 0, 1, 1, 0, 0, 1, 0 ),
		array( 0, 1, 0, 1, 0, 0, 1, 0, 0, 1, 1, 0, 1, 0, 0, 1, 1, 0 ),
		array( 0, 1, 0, 1, 0, 1, 0, 1, 1, 0, 1, 0, 0, 0, 0, 0, 1, 1 ),
		array( 0, 1, 0, 1, 1, 0, 1, 0, 0, 0, 1, 1, 0, 0, 1, 0, 0, 1 ),
		array( 0, 1, 0, 1, 1, 1, 0, 1, 1, 1, 1, 1, 1, 0, 1, 1, 0, 0 ),
		array( 0, 1, 1, 0, 0, 0, 1, 1, 1, 0, 1, 1, 0, 0, 0, 1, 0, 0 ),
		array( 0, 1, 1, 0, 0, 1, 0, 0, 0, 1, 1, 1, 1, 0, 0, 0, 0, 1 ),
		array( 0, 1, 1, 0, 1, 0, 1, 1, 1, 1, 1, 0, 1, 0, 1, 0, 1, 1 ),
		array( 0, 1, 1, 0, 1, 1, 0, 0, 0, 0, 1, 0, 0, 0, 1, 1, 1, 0 ),
		array( 0, 1, 1, 1, 0, 0, 1, 1, 0, 0, 0, 0, 0, 1, 1, 0, 1, 0 ),
		array( 0, 1, 1, 1, 0, 1, 0, 0, 1, 1, 0, 0, 1, 1, 1, 1, 1, 1 ),
		array( 0, 1, 1, 1, 1, 0, 1, 1, 0, 1, 0, 1, 1, 1, 0, 1, 0, 1 ),
		array( 0, 1, 1, 1, 1, 1, 0, 0, 1, 0, 0, 1, 0, 1, 0, 0, 0, 0 ),
		array( 1, 0, 0, 0, 0, 0, 1, 0, 0, 1, 1, 1, 0, 1, 0, 1, 0, 1 ),
		array( 1, 0, 0, 0, 0, 1, 0, 1, 1, 0, 1, 1, 1, 1, 0, 0, 0, 0 ),
		array( 1, 0, 0, 0, 1, 0, 1, 0, 0, 0, 1, 0, 1, 1, 1, 0, 1, 0 ),
		array( 1, 0, 0, 0, 1, 1, 0, 1, 1, 1, 1, 0, 0, 1, 1, 1, 1, 1 ),
		array( 1, 0, 0, 1, 0, 0, 1, 0, 1, 1, 0, 0, 0, 0, 1, 0, 1, 1 ),
		array( 1, 0, 0, 1, 0, 1, 0, 1, 0, 0, 0, 0, 1, 0, 1, 1, 1, 0 ),
		array( 1, 0, 0, 1, 1, 0, 1, 0, 1, 0, 0, 1, 1, 0, 0, 1, 0, 0 ),
		array( 1, 0, 0, 1, 1, 1, 0, 1, 0, 1, 0, 1, 0, 0, 0, 0, 0, 1 ),
		array( 1, 0, 1, 0, 0, 0, 1, 1, 0, 0, 0, 1, 1, 0, 1, 0, 0, 1 ),
	);

	/* - - - - DATA MATRIX ENCODER - - - - */

	private function dmtx_encode($data, $rect, $fnc1) {
		list($data, $ec) = $this->dmtx_encode_data($data, $rect, $fnc1);
		$data = $this->dmtx_encode_ec($data, $ec);
		list($h, $w, $mtx) = $this->dmtx_create_matrix($ec, $data);
		return array(
			'g' => 'm',
			'q' => array(1, 1, 1, 1),
			's' => array($w, $h),
			'b' => $mtx
		);
	}

	private function dmtx_encode_data($data, $rect, $fnc1) {
		/* Convert to data codewords. */
		$edata = ($fnc1 ? array(232) : array());
		$length = strlen($data);
		$offset = 0;
		while ($offset < $length) {
			$ch1 = ord(substr($data, $offset, 1));
			$offset++;
			if ($ch1 >= 0x30 && $ch1 <= 0x39) {
				$ch2 = ord(substr($data, $offset, 1));
				if ($ch2 >= 0x30 && $ch2 <= 0x39) {
					$offset++;
					$edata[] = (($ch1 - 0x30) * 10) + ($ch2 - 0x30) + 130;
				} else {
					$edata[] = $ch1 + 1;
				}
			} else if ($ch1 < 0x80) {
				$edata[] = $ch1 + 1;
			} else {
				$edata[] = 235;
				$edata[] = ($ch1 - 0x80) + 1;
			}
		}
		/* Add padding. */
		$length = count($edata);
		$ec_params = $this->dmtx_detect_version($length, $rect);
		if ($length > $ec_params[0]) {
			$length = $ec_params[0];
			$edata = array_slice($edata, 0, $length);
			if ($edata[$length - 1] == 235) {
				$edata[$length - 1] = 129;
			}
		} else if ($length < $ec_params[0]) {
			$length++;
			$edata[] = 129;
			while ($length < $ec_params[0]) {
				$length++;
				$r = (($length * 149) % 253) + 1;
				$edata[] = ($r + 129) % 254;
			}
		}
		/* Return. */
		return array($edata, $ec_params);
	}

	private function dmtx_detect_version($length, $rect) {
		for ($i = ($rect ? 24 : 0), $j = ($rect ? 30 : 24); $i < $j; $i++) {
			if ($length <= $this->dmtx_ec_params[$i][0]) {
				return $this->dmtx_ec_params[$i];
			}
		}
		return $this->dmtx_ec_params[$j - 1];
	}

	private function dmtx_encode_ec($data, $ec_params) {
		$blocks = $this->dmtx_ec_split($data, $ec_params);
		for ($i = 0, $n = count($blocks); $i < $n; $i++) {
			$ec_block = $this->dmtx_ec_divide($blocks[$i], $ec_params);
			$blocks[$i] = array_merge($blocks[$i], $ec_block);
		}
		return $this->dmtx_ec_interleave($blocks);
	}

	private function dmtx_ec_split($data, $ec_params) {
		$blocks = array();
		$num_blocks = $ec_params[2] + $ec_params[4];
		for ($i = 0; $i < $num_blocks; $i++) {
			$blocks[$i] = array();
		}
		for ($i = 0, $length = count($data); $i < $length; $i++) {
			$blocks[$i % $num_blocks][] = $data[$i];
		}
		return $blocks;
	}

	private function dmtx_ec_divide($data, $ec_params) {
		$num_data = count($data);
		$num_error = $ec_params[1];
		$generator = $this->dmtx_ec_polynomials[$num_error];
		$message = $data;
		for ($i = 0; $i < $num_error; $i++) {
			$message[] = 0;
		}
		for ($i = 0; $i < $num_data; $i++) {
			if ($message[$i]) {
				$leadterm = $this->dmtx_log[$message[$i]];
				for ($j = 0; $j <= $num_error; $j++) {
					$term = ($generator[$j] + $leadterm) % 255;
					$message[$i + $j] ^= $this->dmtx_exp[$term];
				}
			}
		}
		return array_slice($message, $num_data, $num_error);
	}

	private function dmtx_ec_interleave($blocks) {
		$data = array();
		$num_blocks = count($blocks);
		for ($offset = 0; true; $offset++) {
			$break = true;
			for ($i = 0; $i < $num_blocks; $i++) {
				if (isset($blocks[$i][$offset])) {
					$data[] = $blocks[$i][$offset];
					$break = false;
				}
			}
			if ($break) break;
		}
		return $data;
	}

	private function dmtx_create_matrix($ec_params, $data) {
		/* Create matrix. */
		$rheight = $ec_params[8] + 2;
		$rwidth = $ec_params[9] + 2;
		$height = $ec_params[6] * $rheight;
		$width = $ec_params[7] * $rwidth;
		$bitmap = array();
		for ($y = 0; $y < $height; $y++) {
			$row = array();
			for ($x = 0; $x < $width; $x++) {
				$row[] = ((
					((($x + $y) % 2) == 0) ||
					(($x % $rwidth) == 0) ||
					(($y % $rheight) == ($rheight - 1))
				) ? 1 : 0);
			}
			$bitmap[] = $row;
		}
		/* Create data region. */
		$rows = $ec_params[6] * $ec_params[8];
		$cols = $ec_params[7] * $ec_params[9];
		$matrix = array();
		for ($y = 0; $y < $rows; $y++) {
			$row = array();
			for ($x = 0; $x < $width; $x++) {
				$row[] = null;
			}
			$matrix[] = $row;
		}
		$this->dmtx_place_data($matrix, $rows, $cols, $data);
		/* Copy into matrix. */
		for ($yy = 0; $yy < $ec_params[6]; $yy++) {
			for ($xx = 0; $xx < $ec_params[7]; $xx++) {
				for ($y = 0; $y < $ec_params[8]; $y++) {
					for ($x = 0; $x < $ec_params[9]; $x++) {
						$row = $yy * $ec_params[8] + $y;
						$col = $xx * $ec_params[9] + $x;
						$b = $matrix[$row][$col];
						if (is_null($b)) continue;
						$row = $yy * $rheight + $y + 1;
						$col = $xx * $rwidth + $x + 1;
						$bitmap[$row][$col] = $b;
					}
				}
			}
		}
		/* Return matrix. */
		return array($height, $width, $bitmap);
	}

	private function dmtx_place_data(&$mtx, $rows, $cols, $data) {
		$row = 4;
		$col = 0;
		$offset = 0;
		$length = count($data);
		while (($row < $rows || $col < $cols) && $offset < $length) {
			/* Corner cases. Literally. */
			if ($row == $rows && $col == 0) {
				$this->dmtx_place_1($mtx, $rows, $cols, $data[$offset++]);
			} else if ($row == $rows - 2 && $col == 0 && $cols % 4 != 0) {
				$this->dmtx_place_2($mtx, $rows, $cols, $data[$offset++]);
			} else if ($row == $rows - 2 && $col == 0 && $cols % 8 == 4) {
				$this->dmtx_place_3($mtx, $rows, $cols, $data[$offset++]);
			} else if ($row == $rows + 4 && $col == 2 && $cols % 8 == 0) {
				$this->dmtx_place_4($mtx, $rows, $cols, $data[$offset++]);
			}
			/* Up and to the right. */
			while ($row >= 0 && $col < $cols && $offset < $length) {
				if ($row < $rows && $col >= 0 && is_null($mtx[$row][$col])) {
					$b = $data[$offset++];
					$this->dmtx_place_0($mtx, $rows, $cols, $row, $col, $b);
				}
				$row -= 2;
				$col += 2;
			}
			$row += 1;
			$col += 3;
			/* Down and to the left. */
			while ($row < $rows && $col >= 0 && $offset < $length) {
				if ($row >= 0 && $col < $cols && is_null($mtx[$row][$col])) {
					$b = $data[$offset++];
					$this->dmtx_place_0($mtx, $rows, $cols, $row, $col, $b);
				}
				$row += 2;
				$col -= 2;
			}
			$row += 3;
			$col += 1;
		}
	}

	private function dmtx_place_1(&$matrix, $rows, $cols, $b) {
		$matrix[$rows - 1][0] = (($b & 0x80) ? 1 : 0);
		$matrix[$rows - 1][1] = (($b & 0x40) ? 1 : 0);
		$matrix[$rows - 1][2] = (($b & 0x20) ? 1 : 0);
		$matrix[0][$cols - 2] = (($b & 0x10) ? 1 : 0);
		$matrix[0][$cols - 1] = (($b & 0x08) ? 1 : 0);
		$matrix[1][$cols - 1] = (($b & 0x04) ? 1 : 0);
		$matrix[2][$cols - 1] = (($b & 0x02) ? 1 : 0);
		$matrix[3][$cols - 1] = (($b & 0x01) ? 1 : 0);
	}

	private function dmtx_place_2(&$matrix, $rows, $cols, $b) {
		$matrix[$rows - 3][0] = (($b & 0x80) ? 1 : 0);
		$matrix[$rows - 2][0] = (($b & 0x40) ? 1 : 0);
		$matrix[$rows - 1][0] = (($b & 0x20) ? 1 : 0);
		$matrix[0][$cols - 4] = (($b & 0x10) ? 1 : 0);
		$matrix[0][$cols - 3] = (($b & 0x08) ? 1 : 0);
		$matrix[0][$cols - 2] = (($b & 0x04) ? 1 : 0);
		$matrix[0][$cols - 1] = (($b & 0x02) ? 1 : 0);
		$matrix[1][$cols - 1] = (($b & 0x01) ? 1 : 0);
	}

	private function dmtx_place_3(&$matrix, $rows, $cols, $b) {
		$matrix[$rows - 3][0] = (($b & 0x80) ? 1 : 0);
		$matrix[$rows - 2][0] = (($b & 0x40) ? 1 : 0);
		$matrix[$rows - 1][0] = (($b & 0x20) ? 1 : 0);
		$matrix[0][$cols - 2] = (($b & 0x10) ? 1 : 0);
		$matrix[0][$cols - 1] = (($b & 0x08) ? 1 : 0);
		$matrix[1][$cols - 1] = (($b & 0x04) ? 1 : 0);
		$matrix[2][$cols - 1] = (($b & 0x02) ? 1 : 0);
		$matrix[3][$cols - 1] = (($b & 0x01) ? 1 : 0);
	}

	private function dmtx_place_4(&$matrix, $rows, $cols, $b) {
		$matrix[$rows - 1][        0] = (($b & 0x80) ? 1 : 0);
		$matrix[$rows - 1][$cols - 1] = (($b & 0x40) ? 1 : 0);
		$matrix[        0][$cols - 3] = (($b & 0x20) ? 1 : 0);
		$matrix[        0][$cols - 2] = (($b & 0x10) ? 1 : 0);
		$matrix[        0][$cols - 1] = (($b & 0x08) ? 1 : 0);
		$matrix[        1][$cols - 3] = (($b & 0x04) ? 1 : 0);
		$matrix[        1][$cols - 2] = (($b & 0x02) ? 1 : 0);
		$matrix[        1][$cols - 1] = (($b & 0x01) ? 1 : 0);
	}

	private function dmtx_place_0(&$matrix, $rows, $cols, $row, $col, $b) {
		$this->dmtx_place_b($matrix, $rows, $cols, $row-2, $col-2, $b & 0x80);
		$this->dmtx_place_b($matrix, $rows, $cols, $row-2, $col-1, $b & 0x40);
		$this->dmtx_place_b($matrix, $rows, $cols, $row-1, $col-2, $b & 0x20);
		$this->dmtx_place_b($matrix, $rows, $cols, $row-1, $col-1, $b & 0x10);
		$this->dmtx_place_b($matrix, $rows, $cols, $row-1, $col-0, $b & 0x08);
		$this->dmtx_place_b($matrix, $rows, $cols, $row-0, $col-2, $b & 0x04);
		$this->dmtx_place_b($matrix, $rows, $cols, $row-0, $col-1, $b & 0x02);
		$this->dmtx_place_b($matrix, $rows, $cols, $row-0, $col-0, $b & 0x01);
	}

	private function dmtx_place_b(&$matrix, $rows, $cols, $row, $col, $b) {
		if ($row < 0) {
			$row += $rows;
			$col += (4 - (($rows + 4) % 8));
		}
		if ($col < 0) {
			$col += $cols;
			$row += (4 - (($cols + 4) % 8));
		}
		$matrix[$row][$col] = ($b ? 1 : 0);
	}

	/*  $dmtx_ec_params[] = array(                             */
	/*    total number of data codewords,                      */
	/*    number of error correction codewords per block,      */
	/*    number of blocks in first group,                     */
	/*    number of data codewords per block in first group,   */
	/*    number of blocks in second group,                    */
	/*    number of data codewords per block in second group,  */
	/*    number of data regions (vertical),                   */
	/*    number of data regions (horizontal),                 */
	/*    number of rows per data region,                      */
	/*    number of columns per data region                    */
	/*  );                                                     */
	private $dmtx_ec_params = array(
		array(    3,  5, 1,   3, 0,   0, 1, 1,  8,  8 ),
		array(    5,  7, 1,   5, 0,   0, 1, 1, 10, 10 ),
		array(    8, 10, 1,   8, 0,   0, 1, 1, 12, 12 ),
		array(   12, 12, 1,  12, 0,   0, 1, 1, 14, 14 ),
		array(   18, 14, 1,  18, 0,   0, 1, 1, 16, 16 ),
		array(   22, 18, 1,  22, 0,   0, 1, 1, 18, 18 ),
		array(   30, 20, 1,  30, 0,   0, 1, 1, 20, 20 ),
		array(   36, 24, 1,  36, 0,   0, 1, 1, 22, 22 ),
		array(   44, 28, 1,  44, 0,   0, 1, 1, 24, 24 ),
		array(   62, 36, 1,  62, 0,   0, 2, 2, 14, 14 ),
		array(   86, 42, 1,  86, 0,   0, 2, 2, 16, 16 ),
		array(  114, 48, 1, 114, 0,   0, 2, 2, 18, 18 ),
		array(  144, 56, 1, 144, 0,   0, 2, 2, 20, 20 ),
		array(  174, 68, 1, 174, 0,   0, 2, 2, 22, 22 ),
		array(  204, 42, 2, 102, 0,   0, 2, 2, 24, 24 ),
		array(  280, 56, 2, 140, 0,   0, 4, 4, 14, 14 ),
		array(  368, 36, 4,  92, 0,   0, 4, 4, 16, 16 ),
		array(  456, 48, 4, 114, 0,   0, 4, 4, 18, 18 ),
		array(  576, 56, 4, 144, 0,   0, 4, 4, 20, 20 ),
		array(  696, 68, 4, 174, 0,   0, 4, 4, 22, 22 ),
		array(  816, 56, 6, 136, 0,   0, 4, 4, 24, 24 ),
		array( 1050, 68, 6, 175, 0,   0, 6, 6, 18, 18 ),
		array( 1304, 62, 8, 163, 0,   0, 6, 6, 20, 20 ),
		array( 1558, 62, 8, 156, 2, 155, 6, 6, 22, 22 ),
		array(    5,  7, 1,   5, 0,   0, 1, 1,  6, 16 ),
		array(   10, 11, 1,  10, 0,   0, 1, 2,  6, 14 ),
		array(   16, 14, 1,  16, 0,   0, 1, 1, 10, 24 ),
		array(   22, 18, 1,  22, 0,   0, 1, 2, 10, 16 ),
		array(   32, 24, 1,  32, 0,   0, 1, 2, 14, 16 ),
		array(   49, 28, 1,  49, 0,   0, 1, 2, 14, 22 ),
	);

	private $dmtx_ec_polynomials = array(
		5 => array(
			0, 235, 207, 210, 244, 15
		),
		7 => array(
			0, 177, 30, 214, 218, 42, 197, 28
		),
		10 => array(
			0, 199, 50, 150, 120, 237, 131, 172, 83, 243, 55
		),
		11 => array(
			0, 213, 173, 212, 156, 103, 109, 174, 242, 215, 12, 66
		),
		12 => array(
			0, 168, 142, 35, 173, 94, 185, 107, 199, 74, 194, 233, 78
		),
		14 => array(
			0, 83, 171, 33, 39, 8, 12, 248,
			27, 38, 84, 93, 246, 173, 105
		),
		18 => array(
			0, 164, 9, 244, 69, 177, 163, 161, 231, 94,
			250, 199, 220, 253, 164, 103, 142, 61, 171
		),
		20 => array(
			0, 127, 33, 146, 23, 79, 25, 193, 122, 209, 233,
			230, 164, 1, 109, 184, 149, 38, 201, 61, 210
		),
		24 => array(
			0, 65, 141, 245, 31, 183, 242, 236, 177, 127, 225, 106,
			22, 131, 20, 202, 22, 106, 137, 103, 231, 215, 136, 85, 45
		),
		28 => array(
			0, 150, 32, 109, 149, 239, 213, 198, 48, 94,
			50, 12, 195, 167, 130, 196, 253, 99, 166, 239,
			222, 146, 190, 245, 184, 173, 125, 17, 151
		),
		36 => array(
			0, 57, 86, 187, 69, 140, 153, 31, 66, 135, 67, 248, 84,
			90, 81, 219, 197, 2, 1, 39, 16, 75, 229, 20, 51, 252,
			108, 213, 181, 183, 87, 111, 77, 232, 168, 176, 156
		),
		42 => array(
			0, 225, 38, 225, 148, 192, 254, 141, 11, 82, 237,
			81, 24, 13, 122, 0, 106, 167, 13, 207, 160, 88,
			203, 38, 142, 84, 66, 3, 168, 102, 156, 1, 200,
			88, 60, 233, 134, 115, 114, 234, 90, 65, 138
		),
		48 => array(
			0, 114, 69, 122, 30, 94, 11, 66, 230, 132, 73, 145, 137,
			135, 79, 214, 33, 12, 220, 142, 213, 136, 124, 215, 166,
			9, 222, 28, 154, 132, 4, 100, 170, 145, 59, 164, 215, 17,
			249, 102, 249, 134, 128, 5, 245, 131, 127, 221, 156
		),
		56 => array(
			0, 29, 179, 99, 149, 159, 72, 125, 22, 55, 60, 217,
			176, 156, 90, 43, 80, 251, 235, 128, 169, 254, 134,
			249, 42, 121, 118, 72, 128, 129, 232, 37, 15, 24, 221,
			143, 115, 131, 40, 113, 254, 19, 123, 246, 68, 166,
			66, 118, 142, 47, 51, 195, 242, 249, 131, 38, 66
		),
		62 => array(
			0, 182, 133, 162, 126, 236, 58, 172, 163, 53, 121, 159, 2,
			166, 137, 234, 158, 195, 164, 77, 228, 226, 145, 91, 180,
			232, 23, 241, 132, 135, 206, 184, 14, 6, 66, 238, 83, 100,
			111, 85, 202, 91, 156, 68, 218, 57, 83, 222, 188, 25, 179,
			144, 169, 164, 82, 154, 103, 89, 42, 141, 175, 32, 168
		),
		68 => array(
			0, 33, 79, 190, 245, 91, 221, 233, 25, 24, 6, 144,
			151, 121, 186, 140, 127, 45, 153, 250, 183, 70, 131,
			198, 17, 89, 245, 121, 51, 140, 252, 203, 82, 83, 233,
			152, 220, 155, 18, 230, 210, 94, 32, 200, 197, 192,
			194, 202, 129, 10, 237, 198, 94, 176, 36, 40, 139,
			201, 132, 219, 34, 56, 113, 52, 20, 34, 247, 15, 51
		),
	);

	private $dmtx_log = array(
		  0,   0,   1, 240,   2, 225, 241,  53,
		  3,  38, 226, 133, 242,  43,  54, 210,
		  4, 195,  39, 114, 227, 106, 134,  28,
		243, 140,  44,  23,  55, 118, 211, 234,
		  5, 219, 196,  96,  40, 222, 115, 103,
		228,  78, 107, 125, 135,   8,  29, 162,
		244, 186, 141, 180,  45,  99,  24,  49,
		 56,  13, 119, 153, 212, 199, 235,  91,
		  6,  76, 220, 217, 197,  11,  97, 184,
		 41,  36, 223, 253, 116, 138, 104, 193,
		229,  86,  79, 171, 108, 165, 126, 145,
		136,  34,   9,  74,  30,  32, 163,  84,
		245, 173, 187, 204, 142,  81, 181, 190,
		 46,  88, 100, 159,  25, 231,  50, 207,
		 57, 147,  14,  67, 120, 128, 154, 248,
		213, 167, 200,  63, 236, 110,  92, 176,
		  7, 161,  77, 124, 221, 102, 218,  95,
		198,  90,  12, 152,  98,  48, 185, 179,
		 42, 209,  37, 132, 224,  52, 254, 239,
		117, 233, 139,  22, 105,  27, 194, 113,
		230, 206,  87, 158,  80, 189, 172, 203,
		109, 175, 166,  62, 127, 247, 146,  66,
		137, 192,  35, 252,  10, 183,  75, 216,
		 31,  83,  33,  73, 164, 144,  85, 170,
		246,  65, 174,  61, 188, 202, 205, 157,
		143, 169,  82,  72, 182, 215, 191, 251,
		 47, 178,  89, 151, 101,  94, 160, 123,
		 26, 112, 232,  21,  51, 238, 208, 131,
		 58,  69, 148,  18,  15,  16,  68,  17,
		121, 149, 129,  19, 155,  59, 249,  70,
		214, 250, 168,  71, 201, 156,  64,  60,
		237, 130, 111,  20,  93, 122, 177, 150,
	);

	private $dmtx_exp = array(
		  1,   2,   4,   8,  16,  32,  64, 128,
		 45,  90, 180,  69, 138,  57, 114, 228,
		229, 231, 227, 235, 251, 219, 155,  27,
		 54, 108, 216, 157,  23,  46,  92, 184,
		 93, 186,  89, 178,  73, 146,   9,  18,
		 36,  72, 144,  13,  26,  52, 104, 208,
		141,  55, 110, 220, 149,   7,  14,  28,
		 56, 112, 224, 237, 247, 195, 171, 123,
		246, 193, 175, 115, 230, 225, 239, 243,
		203, 187,  91, 182,  65, 130,  41,  82,
		164, 101, 202, 185,  95, 190,  81, 162,
		105, 210, 137,  63, 126, 252, 213, 135,
		 35,  70, 140,  53, 106, 212, 133,  39,
		 78, 156,  21,  42,  84, 168, 125, 250,
		217, 159,  19,  38,  76, 152,  29,  58,
		116, 232, 253, 215, 131,  43,  86, 172,
		117, 234, 249, 223, 147,  11,  22,  44,
		 88, 176,  77, 154,  25,  50, 100, 200,
		189,  87, 174, 113, 226, 233, 255, 211,
		139,  59, 118, 236, 245, 199, 163, 107,
		214, 129,  47,  94, 188,  85, 170, 121,
		242, 201, 191,  83, 166,  97, 194, 169,
		127, 254, 209, 143,  51, 102, 204, 181,
		 71, 142,  49,  98, 196, 165, 103, 206,
		177,  79, 158,  17,  34,  68, 136,  61,
		122, 244, 197, 167,  99, 198, 161, 111,
		222, 145,  15,  30,  60, 120, 240, 205,
		183,  67, 134,  33,  66, 132,  37,  74,
		148,   5,  10,  20,  40,  80, 160, 109,
		218, 153,  31,  62, 124, 248, 221, 151,
		  3,   6,  12,  24,  48,  96, 192, 173,
		119, 238, 241, 207, 179,  75, 150,   1,
	);

}