<?php

$file = file('canonicalize.txt');
$differentiatedComponents = [];
$preferredVariants = [];
$normalizedEntries = [];

foreach ($file as $row) {
    $row = trim($row);
    if (empty($row) || $row[0] === '#') continue;
    list($type, $key, $val) = explode("\t", $row, 3);
    if ($type === 'diff') {
        $differentiatedComponents[trim($key)] = trim(substr($val, 1));
    } else if ($type === 'preferred') {
        $preferredVariants[trim($key)] = trim(substr($val, 1));
    } else if ($type === 'reject') {
        $normalizedEntries[trim($key)] = trim($val);
    } else {
		$normalizedEntries[trim($val)] = trim($key);
	}
}

$files = scandir('data/.');
$files = array_filter($files, function ($fileName) {
	return $fileName[0] !== '.' && substr($fileName, -4) === '.txt';
});
usort($files, function ($f2, $f1) {
	return filemtime('data/' . $f1) - filemtime('data/' . $f2);
});

$seen = [];
$data = [];
foreach ($files as $file) {
	$lines = file('data/' . $file);
	foreach ($lines as $line) {
		$line = trim($line);
		if ($line === '' || $line[0] === '#') continue;
		list($codepoint, $char, $ids) = explode("\t", $line, 3);
		$usv = hexdec(substr($codepoint, 2));
		if (isset($seen[$codepoint]) && $seen[$codepoint] !== $line) {
			echo '<div class=warning>Warning: ' . $codepoint . ' in ' . $file . ' has entry ' . $line . ', expected ' . $seen[$codepoint] . '</div>';
		}
		foreach ($normalizedEntries as $key => $value) {
			if (strpos($ids, $key) !== false) {
				echo '<div class=warning>Warning: Unexpected ' . $key . ' found in entry ' . $line . ', expected ' . $value . ' - <a href="//zi.tools/zi/' . $char . '" target=_blank>Lookup etymology at zi.tools</a></div>';
			}
		}
		$seen[$codepoint] = $line;
		$data[$file][] = $line;
	}
}

function renderHTML($charString) {
	$charString = str_replace('⺫', '⺫(目)', $charString);
	$charString = str_replace('⺲', '⺲(𦉰)', $charString);
	$charString = htmlspecialchars($charString);

	// Overwrite characters which have peculiar shape in I.Ming
	$charString = str_replace('𠯑', '{hkcs-20bd1}', $charString);

	// Replace with image
	$charString = preg_replace_callback('#\\{hkcs-([0-9a-f]{4,5})(-(v?c?[0-9]{2}))?\\}#', function($a) {
		$codepoint = $a[1];
		$variation = isset($a[3]) ? $a[3] : '';

		$map = [
			// Special override to avoid special shape in I.Ming
			'20bd1'     => 'hkcs_m20bd1', // ⿱氏口

			// Special component for simplified char
			'9e1f-c01'  => 'cdp-8964', // 鸟

			// Components
			'4e18-v01'  => 'hkcs_m4e18-v01-p26-s00', // bottom of 虛
			'4e2e-v01'  => 'hkcs_m4e2e-v01-p02-s02', // right of 执/孰
			'4e2e-v02'  => 'hkcs_m4e2e-v02-p02-s00', // right of 巩
			'4eac-v01'  => 'hkcs_mcdp-8c4d', // top of 亮/豪
			'4eca-v01'  => 'hkcs_m4eca-v01-p03-s00', // top of 禽
			'4f10-v01'  => 'hkcs_m4f10-v01-p04-s00', // top of 蔑
			'5202-v01'  => 'hkcs_m5202-v01-p08-s00', // middle of 疈/辨
			'53c8-v01'  => 'hkcs_m53c8-v01-p03-s00', // top of 灵
			'53ca-v01'  => 'hkcs_m53ca-v01-p03-s00', // top of 急
			'5bb3-c01'  => 'hkcs_m5bb3-c01-p03-s00', // top of 害/憲
			'5bb7-v01'  => 'hkcs_m5bb7-v01-p01-s00', // top of 奥, this should be p03
			'5c12-v01'  => 'hkcs_m5c12-v01-p03-s00', // top of 𩰦
			'5de4-c01'  => 'hkcs_mcdp-8d46-p04-s00', // bottom of 巤/鼠
			'5e7e-c01'  => 'hkcs_m5e7e-c01-p05-s00', // outer of 幾/畿
			'5f9e-c02'  => 'hkcs_m5f9e-c02-p02-s00', // right of 從/緃
			'5fae-v01'  => 'hkcs_m5fae-v01-p05-s00', // outer of 微/㣲/徵
			'5fb9-v01'  => 'hkcs_m5fb9-v01-p02-s00', // right of 徹/撤
			'611b-c01'  => 'hkcs_mcdp-8bb8-p03-s01', // top of 爱/舜
			'6215-v01'  => 'hkcs_m6215-p05-s00', // outer of 臧
			'624d-v01'  => 'hkcs_m624d-v01-p05-s01', // outer of 在/存
			'6562-c01'  => 'cdp-8c5b', // left of 敢
			'66b4-c02'  => 'hkcs_m66b4-c02-p03-s00', // top of 曓/㬥/㬧/𧬉
			'66fa-c01'  => 'u24c14', // top of 曺
			'6bc0-c01'  => 'hkcs_mcdp-8db4', // left of 毀
			'7077-v01'  => 'hkcs_m7077-v01-p02-s00', // right of 朕
			'7077-v02'  => 'hkcs_m7077-v01', // top right of 𦢅
			'722a-v01'  => 'hkcs_m722a-v01-p01-s00', // left of 印/center left of 裦
			'722a-v02'  => 'hkcs_m722a-v02-p26-s00', // bottom of 虐
			'754f-c01'  => 'hkcs_m754f-c01-p04-s00', // bottom of 畏
			'7fb2-c01'  => 'cdp-8dba', // bottom of 羲
			'80e4-c01'  => 'hkcs_m80e4-c01-p08-s00', // middle of 胤
			'81fe-v01'  => 'hkcs_m81fe-v01-p03-s00', // top of 貴
			'8201-v01'  => 'cdp-8b64', // top outer of 𦦩
			'821f-v01'  => 'hkcs_m821f-v01-p01-s00', // left of 朕
			'92bd-v01'  => 'hkcs_m92bd-v01', // ⿰金{hkcs_m20bd1-v01}
			'9578-c01'  => 'hkcs_m9578-c01-p03-s00', // top of 镸
			'9700-v01'  => 'u2ff1-cdp-88e1-cdp-8c40', // right of 𠍶
			'9801-v01'  => 'hkcs_m9801-v01-p03-s00', // top of 憂
			'98a8-v01'  => 'hkcs_m98a8-v01-p05-s00', // outer of 𫗀
			'99ac-c01'  => 'hkcs_m99ac-c01-p03-s00', // top of 𮕹
			'9ce5-c01'  => 'hkcs_m9ce5-c01-p03-s00', // outer of 鳥
			'9f4a-c00'  => 'hkcs_m9f4a-c00-p03-s00', // top of 韲/𪔉
			'3694-v01'  => 'hkcs_m3694-v01-p01-s00', // left of 執
			'3771-c01'  => 'hkcs_m3771-c01-s00', // outer of 寎/寐
			'3771-c02'  => 'hkcs_m3771-c02-s00', // outer of 㝱/㝲
			'2097d-v01' => 'hkcs_m2097d', // right of 括
			'20b1e-v01' => 'hkcs_m20b1e-v01-p04-s00', // bottom of 具/其
			'20bd1-v01' => 'hkcs_m20bd1-v01', // right of 括
//			'23a8a-c01' => 'hkcs_m23a8a-c01-s00', // top left of 彀
			'23a8a-c01' => 'cdp-8d44', // top left of 彀
			'23ade-c01' => 'hkcs_m23ade-c01-p01-s00', // left of 𣫞
			'26a4e-v01' => 'hkcs_m26a4e-v01-p05-s00', // top left of 𦫁/𦫂
			'2699d-c01' => 'hkcs_m2699d-c01-p03-s00', // top of 㸑/爨

			'2ebc-v01'  => 'hkcs_m2ebc-v01-p03-s00', // top right of 將
		];

		$index = $codepoint . '-' . $variation;
		if (isset($map[$index])) {
			$glyphName = $map[$index];
		} else if (isset($map[$codepoint])) {
			$glyphName = $map[$codepoint];
		} else {
			$glyphName = 'hkcs_m' . $codepoint . '-' . $variation;
		}

		return '<img src="' . htmlspecialchars('http://glyphwiki.org/glyph/' . $glyphName) . '.svg" width=20 style="vertical-align:-4px;line-height:1">';
	}, $charString);
	return $charString;
}

function verifyIDS($idsListString) {
	$valid = true;

	$idsList = explode("\t", $idsListString);
	foreach ($idsList as $ids) {
		while (true) {
			$len1 = strlen($ids);

			$ids = preg_replace('@\x{2ff0}([\x{2e00}-\x{2eff}\x{3000}-\x{40000}]|\{[a-z0-9-]+\}){2}@u', '？', $ids);
			$ids = preg_replace('@\x{2ff1}([\x{2e00}-\x{2eff}\x{3000}-\x{40000}]|\{[a-z0-9-]+\}){2}@u', '？', $ids);
			$ids = preg_replace('@\x{2ff2}([\x{2e00}-\x{2eff}\x{3000}-\x{40000}]|\{[a-z0-9-]+\}){3}@u', '？', $ids);
			$ids = preg_replace('@\x{2ff3}([\x{2e00}-\x{2eff}\x{3000}-\x{40000}]|\{[a-z0-9-]+\}){3}@u', '？', $ids);
			$ids = preg_replace('@\x{2ff4}([\x{2e00}-\x{2eff}\x{3000}-\x{40000}]|\{[a-z0-9-]+\}){2}@u', '？', $ids);
			$ids = preg_replace('@\x{2ff5}([\x{2e00}-\x{2eff}\x{3000}-\x{40000}]|\{[a-z0-9-]+\}){2}@u', '？', $ids);
			$ids = preg_replace('@\x{2ff6}([\x{2e00}-\x{2eff}\x{3000}-\x{40000}]|\{[a-z0-9-]+\}){2}@u', '？', $ids);
			$ids = preg_replace('@\x{2ff7}([\x{2e00}-\x{2eff}\x{3000}-\x{40000}]|\{[a-z0-9-]+\}){2}@u', '？', $ids);
			$ids = preg_replace('@\x{2ff8}([\x{2e00}-\x{2eff}\x{3000}-\x{40000}]|\{[a-z0-9-]+\}){2}@u', '？', $ids);
			$ids = preg_replace('@\x{2ff9}([\x{2e00}-\x{2eff}\x{3000}-\x{40000}]|\{[a-z0-9-]+\}){2}@u', '？', $ids);
			$ids = preg_replace('@\x{2ffa}([\x{2e00}-\x{2eff}\x{3000}-\x{40000}]|\{[a-z0-9-]+\}){2}@u', '？', $ids);
			$ids = preg_replace('@\x{2ffb}([\x{2e00}-\x{2eff}\x{3000}-\x{40000}]|\{[a-z0-9-]+\}){2}@u', '？', $ids);

			$len2 = strlen($ids);

			if ($len1 == $len2) {
				break;
			}
		}

		if ($ids !== '？' && !preg_match('@^.$@u', $ids)) {
			$valid = false;
		}
	}

	return $valid;
}

?>
<title>IDS file check</title>
<style>
body{font:16px Arial, sans-serif}
td, th{border:1px solid #999;padding:2px 5px;text-align:left}
</style>
<?
$i = 0;
foreach ($data as $file => $lines) {
	$i++;
	if ($i > 20) {
		break;
	}
?>
<h2><?=$file?></h2>
<table style="border-collapse:collapse">
	<col width=100><col width=40><col width=180><col width=16>
	<col width=100><col width=40><col width=180><col width=16>
	<col width=100><col width=40><col width=180><col width=16>
	<col width=100><col width=40><col width=180><col width=16>
<?
	$rows = array_chunk($lines, 4);
	foreach ($rows as $cols) {
?>
	<tr>
<?
		foreach ($cols as $line) {
			list($codepoint, $char, $ids) = explode("\t", $line, 3);
			$char = renderHTML($char);
			$idsHTML = renderHTML($ids);
			
			$result = verifyIDS($ids);
			if (!$result) {
				$idsHTML = '<div style="background:red;color:white;outline:5px solid red">' . $idsHTML . '</div>';
			}
?>
		<th><?=$codepoint?></th>
		<td><?=$char?></td>
		<td><?=$idsHTML?></td>
		<td>&nbsp;</td>
<?
		}
?>
	</tr>
<?
	}
?>
</table>
<?
}
?>
