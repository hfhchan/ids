<?php

require_once 'init.php';
require_once 'char-utils.php';
require_once 'lookup-lib.php';

$files = scandir('data/.');
$files = array_filter($files, function ($fileName) {
	return $fileName[0] !== '.' && $fileName !== 'canonicalize.txt' && $fileName !== 'processed.txt' && $fileName !== 'other.txt' && substr($fileName, -4) === '.txt';
});

$error = false;
$seen = [];
$data = [];
$components = [];
foreach ($files as $file) {
	$lines = file('data/' . $file);
	if ($lines[0][0] !== '#') {
		echo '<div class=info>Skipped ' . htmlspecialchars($file) . '.</div>';
		echo '<pre style="font-size:10px;border-left:3px solid #ccc;padding-left:5px">' . implode("", $lines) . '</pre>';
		continue;
	}
	foreach ($lines as $line) {
		$line = trim($line);
		if ($line === '') continue;
		if ($line[0] === '#') {
			$titleName = trim(substr($line, 1));
			if (substr($titleName, 0, 5) === 'hkcs_') {
				$components[$file][] = $titleName;
			}
			continue;
		}
		list($codepoint, $char, $ids) = explode("\t", $line, 3);
		$usv = hexdec(substr($codepoint, 2));
		if (isset($seen[$codepoint]) && $seen[$codepoint] !== $line) {
			echo '<div class=warning>Warning: ' . $codepoint . ' in ' . $file . ' has entry ' . $line . ', expected ' . $seen[$codepoint] . '</div>';
			$error = true;
		}
		$seen[$codepoint] = $line;
		$data[$codepoint] = $line;
	}
}

uksort($data, function ($a, $b) {
	return getShiftedUSV($a) - getShiftedUSV($b);
});

$ids = '';
foreach ($data as $line) {
	$ids .= trim($line) . "\r\n";
}
file_put_contents('./release/ids-' . date('Ymd') . '.txt', $ids);

file_put_contents('./processed.txt', json_encode($components));

?>
<title>Release Generator</title>
<style>
body{font:16px Arial, sans-serif}
td, th{border:1px solid #999;padding:2px 5px;text-align:left}
</style>
<p>Total <?=count($data)?> rows</p>
<div style="display:grid;grid-template-columns:1fr 1fr">
	<div>
<?
$chunks = array_chunk($data, 100);
foreach ($chunks as $i => $chunk) {
	if ($i > 100) break;
?>
		<table style="border-collapse:collapse">
<?
	foreach ($chunk as $line) {
		list($codepoint, $char, $ids) = explode("\t", $line, 3);
		$ids = str_replace('⺫', '⺫(目)', $ids);
		$ids = str_replace('⺲', '⺲(𦉰)', $ids);
?>
			<tr>
				<th><?=$codepoint?></th>
				<td><?=$char?></td>
				<td><?=htmlspecialchars($ids)?></td>
			</tr>
<?
}
?>
		</table>
<?
}
?>
	</div>
	<div>
		<table style="border-collapse:collapse">
<?
	for ($i = 0x4E00; $i <= 0x9FFF; $i++) {
		if (isset($data['U+' . strtoupper(dechex($i))])) {
			continue;
		} else {
			$codepoint = 'U+' . strtoupper(dechex($i));
			$char = codepointToChar($codepoint);
			$ids = '???';
		}
?>
			<tr <? if ($ids === '???') echo 'style="background:#fff"'; else echo 'style="background:#eee;color:#999"'; ?>>
				<th><?=$codepoint?></th>
				<td><?=$char?></td>
				<td><?=htmlspecialchars($ids)?></td>
			</tr>
<?
	}

	$missing = [];
	for ($i = 0x3400; $i <= 0x9FFF; $i++) {
		continue;

		if (isset($data['U+' . strtoupper(dechex($i))])) {
			continue;
		} else {
			$missing[] = 'U+' . strtoupper(dechex($i));
			$codepoint = 'U+' . strtoupper(dechex($i));
			$char = codepointToChar($codepoint);
			$ids = '???';
			try {
				$db = new DB();
				$glyph = $db->getGlyph('hkcs_m' . dechex($i));
				$idsList = $glyph->tryConvertToIDS();
				$idsList = preg_split('//u', $idsList, null, PREG_SPLIT_NO_EMPTY);
				$ids = '';
				foreach ($idsList as $c) {
					$related = 'u' . strtolower(substr(charToCodepoint($c), 2)) . '.txt';
					if (file_exists($related)) {
						$ids .= '!!';
					}
					$ids .= '<a target=_blank href="lookup.php?char='.$c.'">' . $c . '</a> ';
				}
			} catch (Exception $e) {

			}
		}
?>
			<tr <? if (true) echo 'style="background:#fff"'; else echo 'style="background:#eee;color:#999"'; ?>>
				<th><?=$codepoint?></th>
				<td><a target=_blank href="lookup.php?char=<?=$char?>"><?=$char?></a></td>
				<td><?=($ids)?></td>
			</tr>
<?
	}

	for ($i = 0x9E00; $i < 0x9F00; $i++) {
		if (isset($data['U+' . strtoupper(dechex($i))])) {
			$line = $data['U+' . strtoupper(dechex($i))];
			list($codepoint, $char, $ids) = explode("\t", $line, 3);
			$ids = str_replace('⺫', '⺫(目)', $ids);
			$ids = str_replace('⺲', '⺲(𦉰)', $ids);
		} else {
			$codepoint = 'U+' . strtoupper(dechex($i));
			$char = codepointToChar($codepoint);
			$ids = '???';
		}
?>
			<tr <? if ($ids === '???') echo 'style="background:#fff"'; else echo 'style="background:#eee;color:#999"'; ?>>
				<th><?=$codepoint?></th>
				<td><?=$char?></td>
				<td><?=htmlspecialchars($ids)?></td>
			</tr>
<?
	}
?>
		</table>
	</div>
</div>