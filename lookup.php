<?php

require_once 'char-utils.php';
require_once 'lookup-lib.php';

define('TIMEOUT', 20);

$ignoreComponents = json_decode(file_get_contents('processed.txt'), true);

if (isset($_GET['char'])) {
	$char = $_GET['char'];
	$base = 'hkcs_m' . strtolower(substr(charToCodepoint($char), 2));
	unset($ignoreComponents['u' . strtolower(substr(charToCodepoint($char), 2)) . '.txt']);
} else if (isset($_GET['name'])) {
	$char = null;
	$base = $_GET['name'];
	unset($ignoreComponents[str_replace('hkcs_m', 'hkcs-', $base) . '.txt']);
} else {
	$char = '冒';
	$base = 'hkcs_m' . strtolower(substr(charToCodepoint($char), 2));
	unset($ignoreComponents['u' . strtolower(substr(charToCodepoint($char), 2)) . '.txt']);
}

function getRelease() {
	$release = [];
	$files = glob('./release/ids-*.txt');
	sort($files);
	$files = array_reverse($files);
	$filename = $files[0];

	// $filename = './release/ids-' . date('Ymd') . '.txt';
	if (file_exists($filename)) {
		$contents = file($filename);
		foreach ($contents as $row) {
			list($codepoint, $char, $ids) = explode("\t", $row, 3);
			$release[$codepoint] = trim($ids);
		}
	}
	return $release;
}

?>

<!doctype html>
<style>
body{font:24px/1.4 sans-serif}
h1{margin:0;font-size:24px}
summary{font-size:16px;-webkit-user-select:none}
.info{font-size:12px;}
.warning{font-size:12px;font-weight:bold}
.diff{background:#ffeecc}
.missing{background:#ffff00;outline:1px solid red}
pre{line-height:1.4;overflow:auto;padding:4px}
</style>
<title><?
if ($char) echo 'Lookup ' . htmlspecialchars($char);
else echo 'Lookup ' . htmlspecialchars($base);
?></title>

<div style="position:fixed;top:0;left:0;width:100%;background:#fff;box-sizing:border-box;padding:10px;height:54px;border-bottom:1px solid #ccc">〓 &nbsp; ⿰ &nbsp; ⿱ &nbsp; ⿲ &nbsp; ⿳ &nbsp; ⿴ &nbsp; ⿵ &nbsp; ⿶ &nbsp; ⿷ &nbsp; ⿸ &nbsp; ⿹ &nbsp; ⿺ &nbsp; ⿻</div>
<div style="margin-top:61px"></div>
<h1><?
if ($char) echo 'Lookup for char: ' . htmlspecialchars($char);
else echo 'Lookup for component: ' . htmlspecialchars($base);
?></h1>

<?

$db = new DB();
$release = getRelease();

$processed = [];
if (empty($_GET['force_children'])) {
	foreach ($ignoreComponents as $fileName => $list) {
		foreach ($list as $item) {
			$processed[$item] = $fileName;
		}
	}
}

$starttime = time();
$baseGlyph = $db->getGlyph($base);
$results = [];
$results[$char][] = [ $base, $baseGlyph->tryConvertToIDS() ];
?>
<details open>
	<summary>Evaluation Log</summary>
<?
enumerateComponents($db, $results, $base, $processed);
?>
</details>

<?
function friendlyName($name) {
	try {
		$codepoint = nameToCodepoint($name);
		$char = codepointToChar($codepoint);
	} catch (Exception $e) {
		$char = '??';
	}
	return htmlspecialchars($name . ' (' . $char . ')');
}

function isChar($name) {
	try {
		if (preg_match('#-p[0-9]{2}-s[0-9]{2}#', $name)) {
			return null;
		}
		$codepoint = nameToCodepoint($name);
		$char = codepointToChar($codepoint);
		return $char;
	} catch (Exception $e) {
		return null;
	}
}

function enumerateComponents($db, &$results, $base, &$processed) {
	global $starttime, $release;

	$componentNames = $db->getGlyphNames($base);
	$components = [];
	if (count($componentNames) == 0) {
		$componentNames = [ $base ];
		echo '<div class=warning>No positional variants found, forcefully adding base as positional variant.</div>';
	}

	foreach ($componentNames as $componentName) {
		if (isset($processed[$componentName]) && $processed[$componentName] !== true) {
			echo '<div class=info><b>Skipped ' . friendlyName($componentName) . '</b> - Existing in ' . $processed[$componentName] . '. <u>Add &force_children=1 to include.</u></div>';
			continue;
		}
		$processed[$componentName] = true;
		$list = $db->getGlyphsWithComponent($componentName);
		if (count($componentNames) == 1 && count($list) == 0) {
			echo '<div class=info>No positional variants found.</div>';
			continue;
		}
		$components[$componentName] = $list;
		echo '<div class=info>Found positional variant ' . friendlyName($componentName) . ' used by ' . count($list) . ' glyphs.</div>';
	}

	$i = 0;
	foreach ($components as $componentName => $glyphs) {
		foreach ($glyphs as $glyphData) {
			if (!empty($_GET['skip_existing'])) {
				try {
					$codepoint = nameToCodepoint($glyphData[0]);
					if (isset($release[$codepoint]) && $release[$codepoint] === $glyphData[1]) {
						continue;
					}
				} catch (Exception $e) {
					
				}
			}
			if (preg_match('#-p[0-9]{2}-s[0-9]{2}#', $glyphData[0])) {
				echo '<div class=info>Skipped disassembling ' . friendlyName($glyphData[0]) . ' to IDS (included by containing ' . friendlyName($glyphData[2]) . ')</div>';
			} else {
				$i++;
				$results[$glyphData[2]][] = $glyphData;
			}
		}
	}

	if ($i) {
		echo '<div class=info style="margin-bottom:4px">Added ' . $i  . ' results.</div>';
	}

	// Enumerate Glyphs
	foreach ($components as $componentName => $glyphs) {
		foreach ($glyphs as $glyphData) {
			if (!empty($_GET['skip_existing'])) {
				try {
					$codepoint = nameToCodepoint($glyphData[0]);
					if (isset($release[$codepoint]) && $release[$codepoint] === $glyphData[1]) {
						continue;
					}
				} catch (Exception $e) {
					
				}
			}
			if (isset($processed[$glyphData[0]])) {
				try {
					$char = codepointToChar(nameToCodepoint($glyphData[0]));
				} catch (Exception $e) {
					$char = '??';
				}
				if ($processed[$glyphData[0]] === true) {
					echo '<div class=info>Skipped enumerating ' . friendlyName($glyphData[0]) . ' to avoid infinite loop.</div>';
				} else {
					echo '<div class=info>Skipped enumerating ' . friendlyName($glyphData[0]) . ' because its children exist in another file - ' . htmlspecialchars($processed[$glyphData[0]]) . '.</div>';
				}
				continue;
			}

			$processed[$glyphData[0]] = true;

			if (preg_match('#^hkcs_m([0-9a-f]{4,5})#', $glyphData[0], $fileMatch) && file_exists('./u' . $fileMatch[1] . '.txt')) {
				$skippedGlyphChar = isChar($glyphData[0]);
				if ($skippedGlyphChar) {
					echo '<div class=info>Skipped enumerating ' . friendlyName($glyphData[0]) . ' because it exists in another file (<a href="?char=' . $skippedGlyphChar . '&force_children=1" target=_blank>Enumerate</a>).</div>';
				} else {
					echo '<div class=info>Skipped enumerating ' . friendlyName($glyphData[0]) . ' because it exists in another file.</div>';
				}
				continue;
			}

			if ((time() - $starttime) > TIMEOUT) {
				echo '<div style="color:red">Timed out before enumerating ' . friendlyName($glyphData[0]) . '</div>';
				define('TIMED_OUT', 1);
				return;
			}

			echo '<div class=info>Enumerating ' . friendlyName($glyphData[0]) . '.</div>';
			echo '<div style="padding-left:10px;margin-bottom:4px;border-left:4px solid #fc9">';
			enumerateComponents($db, $results, $glyphData[0], $processed);
			echo '</div>';
		}
	}
}

?>

<? if (!defined('TIMED_OUT')) : ?>
<script>document.querySelector("details").open = false</script>
<? endif; ?>
<script src="page-toast.js"></script>
<page-toast id=toast></page-toast>
<script>
window.addEventListener('click', e => {
	if (e.target.closest('pre#source') && window.getSelection().type === "Caret") {
		var text = document.getElementById('source').innerText;
		var elem = document.createElement("textarea");
		document.body.appendChild(elem);
		elem.value = text;
		elem.select();
		document.execCommand("copy");
		document.body.removeChild(elem);
		document.getElementById('toast').show('Copied to clipboard');
	}
})
</script>

<div style="display:grid;grid-template-columns:minmax(20px, 1fr) minmax(20px, 1fr);gap:10px">
<?

echo '<pre id=source>';
foreach ($results as $base => $glyphs) {
	echo '# ' . $base . "\r\n";
	foreach ($glyphs as $glyphData) {
		if (strpos($glyphData[0], 'hkcs_m') !== 0) {
			echo $glyphData[0] . "\t" . $glyphData[0] . "\t" . htmlspecialchars($glyphData[1]) . "\r\n";
		} else {
			try {
				$glyphCodepoint = nameToCodepoint($glyphData[0]);
				echo $glyphCodepoint . "\t" . codepointToChar($glyphCodepoint) . "\t" . htmlspecialchars($glyphData[1]) . "\r\n";
			} catch (Exception $e) {
				
			}
		}
	}
	echo "\r\n";
}
echo '</pre>';
?>

<?

echo '<pre>';
foreach ($results as $base => $glyphs) {
	echo '# ' . $base . "\r\n";
	foreach ($glyphs as $glyphData) {
		if (strpos($glyphData[0], 'hkcs_m') !== 0) {
			echo $glyphData[0] . "\t" . $glyphData[0] . "\t" . htmlspecialchars($glyphData[1]) . "\r\n";
		} else {
			$glyphCodepoint = nameToCodepoint($glyphData[0]);
			if (isset($release[$glyphCodepoint]) && $release[$glyphCodepoint] !== $glyphData[1]) {
				$class = 'diff';
			} else if (!isset($release[$glyphCodepoint])) {
				$class = 'missing';
			} else {
				$class = '';
			}
			try {
				ob_start();
				echo '<span class="' . $class . '">';
				echo $glyphCodepoint . "\t" . codepointToChar($glyphCodepoint) . "\t" . htmlspecialchars(isset($release[$glyphCodepoint]) ? $release[$glyphCodepoint] : '') . "\r\n";
				echo '</span>';
				echo ob_get_clean();
			} catch (Exception $e) {
				ob_clean();
			}
		}
	}
	echo "\r\n";
}
echo '</pre>';
?>

</div>