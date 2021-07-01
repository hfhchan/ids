<?php

function codepointToChar($codepoint) {
	if (preg_match('@^U\+[0-9A-F]{4,5}$@', $codepoint)) {
		return iconv('UTF-32BE', 'UTF-8', pack("H*", str_pad(substr($codepoint, 2), 8, '0', STR_PAD_LEFT)));
	}
	throw new Exception('Invalid Input');
}

function charToCodepoint($char) {
	if (mb_strlen($char, 'UTF-8') === 1) {
		return 'U+'.strtoupper(ltrim(bin2hex(iconv('UTF-8', 'UTF-32BE', $char)),'0'));
	}
	throw new Exception('Invalid Input');
}

function charToUSV($char) {
	if (mb_strlen($char, 'UTF-8') === 1) {
		return hexdec(ltrim(bin2hex(iconv('UTF-8', 'UTF-32BE', $char)), '0'));
	}
	throw new Exception('Invalid Input');
}

function isCompatibilityCodepoint($codepoint) {
	$unified = [0xFA0E, 0xFA0F, 0xFA11, 0xFA13, 0xFA14, 0xFA1F, 0xFA21, 0xFA23, 0xFA24, 0xFA27, 0xFA28, 0xFA29];
	$usv = hexdec(substr($codepoint, 2));
	foreach ($unified as $u) {
		if ($usv == $u) return false;
	}
	if ($usv >= 0xF900 && $usv <= 0xFAFF) {
		return true;
	}
	if ($usv >= 0x2F800 && $usv <= 0x2FA1F) {
		return true;
	}
	return false;
}

function getShiftedUSV($codepoint) {
	$usv = hexdec(substr($codepoint, 2));
	if ($usv >= 0x4E00 && $usv <= 0x9FFF) {
		return $usv;
	}
	if ($usv >= 0x3400 && $usv <= 0x4DFF) {
		return $usv + 0x10000;
	}
	return $usv + 0x10000;
}
