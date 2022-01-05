<?php

require_once 'init.php';

function nameToCodepoint($name) {
	$str = str_replace('hkcs_', '', $name);
	$str = substr($str, 1);
	if (strpos($str, '-')) {
		$str = substr($str, 0, strpos($str, '-'));
	}
	return 'U+' . strtoupper($str);
}


class DB {
	public $db;

	public function __construct() {
		$this->db = new PDO("sqlite:cache.sqlite3");
		$this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	}

	public function getGlyphNames($base) {
		$names = [];
		$q = $this->db->prepare("SELECT id FROM cache WHERE id = ? OR id LIKE ?");
		$q->execute([ $base, $base . '%' ]);
		while ($name = $q->fetchColumn()) {
			$names[] = $name;
		}

		$q = $this->db->prepare("SELECT data FROM cache WHERE data LIKE ?");
		$q->execute([ '%:'. $base . '%' ]);
		while ($data = $q->fetchColumn()) {
			if (preg_match('@\\:(' . $base . '(-(c|v|p|s)[0-9]{2}([0-9]{2})?)+)@', $data, $matches)) {
				$names[] = $matches[1];
			}
		}

		$names = array_values(array_unique($names));
		sort($names);
		return $names;
	}

	public function getGlyph($id) {
		$q = $this->db->prepare("SELECT id, data FROM cache WHERE id = ?");
		$q->execute([ $id ]);
		$result = $q->fetch(PDO::FETCH_OBJ);
		if (!$result) {
			$json = file_get_contents('http://non-ssl.glyphwiki.org/api/glyph?name=' . $id);
			if ($json) {
				$obj = json_decode($json);
				if ($obj) {
					$q2 = $this->db->prepare("INSERT INTO cache (id, data, lastmod) VALUES (?, ?, ?)");
					$q2->execute([ $obj->name, $obj->data, time() ]);
					return new Glyph($obj->name, $obj->data);
				}
			}
			return null;
		}
		return new Glyph($result->id, $result->data);
	}

	public function getGlyphsWithComponent($componentName) {
		$matches = [];
		$q = $this->db->prepare("SELECT id FROM cache WHERE data LIKE ?");
		$q->execute([ '%' . $componentName . '%' ]);

		while ($name = $q->fetchColumn()) {
			if (substr($name, 0, 6) !== 'hkcs_m') {
				continue; // Glyph is included by a marker glyph such as "hkcs_tangut"
			}
			if ($name === 'hkcs_multi') {
				continue;
			}

			$glyph = $this->getGlyph($name);
			if ($glyph->hasComponent($componentName)) {
				if ((strpos($glyph->data, 'hkcs_u25a1') === false // &&
					// strpos($glyph->data, 'hkcs_oldhanzi') === false &&
					// strpos($glyph->data, '99:0:0:0:0:150:150:') === false
					) ||
					strpos($glyph->data, 'hkcs_char-2192') !== false ||
					strpos($glyph->data, 'hkcs_char-ff1d') !== false ||
					strpos($glyph->data, 'hkcs_char-2245') !== false) {
						$matches[] = [ $name, $glyph->tryConvertToIDS(), $this->getRelevantName($componentName, $glyph) ];
				}
			}
		}

		usort($matches, function ($a, $b) {
			return getShiftedUSV(nameToCodepoint($a[0])) - getShiftedUSV(nameToCodepoint($b[0]));
		});

		return $matches;
	}

	public function getRelevantName($componentName, $glyph) {
		$type = $glyph->getType();
		if ($type) return $type;
		return $componentName;
	}
}

class Glyph {
	public function __construct($name, $data) {
		$this->name = $name;
		$this->data = $data;
	}

	public function getType() {
		$glyphData = str_replace('$', "\n", $this->data);
		$glyphData = preg_replace('#@([0-9]+)#', "", $glyphData);
		$rows = explode("\n", $glyphData);
		foreach ($rows as $row) {
			if (strpos($row, 'hkcs_char-ff1d') !== false) {
				return 'Source Code Separation / Duplicate';
			}
			if (strpos($row, 'hkcs_char-2245') !== false) {
				return 'Simplifications';
			}
			if (strpos($row, 'hkcs_oldhanzi') !== false) {
				return 'Ancient Form';
			}
			if (strpos($row, 'hkcs_char-2192') !== false) {
				return 'Variant';
			}
			if (strpos($row, '99:0:0:0:0:150:150:hkcs_wrong') !== false) {
				return 'Corrupted Form';
			}
		}
		return '';
	}

	public function hasComponent($componentName) {
		$glyphData = urldecode($this->data) . "\n";
		$glyphData = str_replace('$', "\n", $glyphData);
		$glyphData = preg_replace('#@([0-9]+)#', "", $glyphData);
		if (strpos($glyphData, ':' . $componentName . "\n") !== false || strpos($glyphData, ':' . $componentName . ":0:0:0") !== false || strpos($glyphData, ':' . $componentName . ":0:") !== false || strpos($glyphData, ':' . $componentName . ":0\n") !== false) {
			return true;
		}
		return false;
	}

	public $overrideCache = [
		'壽' => true,
	];

	public function tryConvertToIDS() {
		static $cache;

		try {
			$cacheKey = codepointToChar(nameToCodepoint($this->name));
		} catch (Exception $e) {
			$cacheKey = $this->name;
		}

		if (isset($this->overrideCache[$cacheKey])) {
			return $cacheKey;
		}

		$chars = [];
		$glyphData = urldecode($this->data) . "\n";
		$glyphData = str_replace('$', "\n", $glyphData);
		$glyphData = preg_replace('#@([0-9]+)#', "", $glyphData);
		$rows = explode("\n", $glyphData);
		foreach ($rows as $row) {
			if (strlen($row) > 10 && $row[0] === '9') {
				list($t, $skew1, $skew2, $x1, $y1, $x2, $y2, $component) = explode(':', $row);
				if ($component === 'hkcs_cdp-8c4f') {
					$chars[] = '⿱⿰{hkcs-2ebc-v01}又';
					continue;
				}

				if (substr($component, 0, 6) !== 'hkcs_m') continue;

				if ($component === 'hkcs_m2ff0-u53e3-u5315-p04-s00') {
					$idx = count($chars) - 2;
					if ($idx == -1) {
						$chars[$idx + 2] = $chars[$idx + 1];
						$chars[$idx + 1] = '⿱';
					} else if ($idx >= 0 && $chars[$idx] !== '⿱') {
						$chars[] = '⿱';
					}

					$chars[] = '⿰';
					$chars[] = '口匕';
					continue;
				}

				// Ignore -v01 prefix
				if ($component === 'hkcs_m9ad8-v01-p03-s00') {
					$chars[] = '⿱';
					$chars[] = '高';
					continue;
				}
				if ($component === 'hkcs_m536f-v01-p03-s00') {
					$chars[] = '⿱';
					$chars[] = '卯';
					continue;
				}
				if ($component === 'hkcs_m5ddd-v01-p04-s00') {
					$chars[] = '⿱';
					$chars[] = '𫶧';
					continue;
				}
				if ($component === 'hkcs_mcdp-8c4d') {
					$chars[] = '⿱';
					$chars[] = '{hkcs-4eac-v01}';
					continue;
				}

				// Fake
				if ($component === 'hkcs_mcdp-8bb8-p03-s01') {
					$component = 'hkcs_m611b-c01-p03-s00';
				}
				// Fake
				if ($component === 'hkcs_mcdp-8d46-p04-s00') {
					$component = 'hkcs_m5de4-c01-p04-s00';
				}
				// Fake for right of 隋
				if ($component === 'hkcs_mcdp-8ca9-p16-s00') {
					$component = 'hkcs_m968b-c01-p16-s00';
				}
				// Fake for right of 隋
				if ($component === 'hkcs_mcdp-8ca9-p04-s00') {
					$component = 'hkcs_m968b-c01-p04-s00';
				}

				// Convert -v01 and -c01 prefixes
				if (preg_match('#hkcs_m([0-9a-f]{4,5})-(v|c)([0-9]{2})-p0(1|2)-s[0-9]{2}#', $component, $matches)) {
					$component = 'hkcs-' . $matches[1] . '-' . $matches[2] . $matches[3];
					$chars[] = '⿰';
					$chars[] = '{' . $component . '}';
					continue;
				}

				if (preg_match('#hkcs_m([0-9a-f]{4,5})-(v|c)([0-9]{2})-p0(3|4)-s[0-9]{2}#', $component, $matches)) {
					$component = 'hkcs-' . $matches[1] . '-' . $matches[2] . $matches[3];
					$chars[] = '⿱';
					$chars[] = '{' . $component . '}';
					continue;
				}

				if ($component == 'hkcs_m8103-c02' || $component == 'hkcs_m7d55-c02') {
					$chars[] = '⿱刀巴';
					continue;
				}

				if (preg_match('#hkcs_m([0-9a-f]{4,5})-(v|c)([0-9]{2})#', $component, $matches)) {
					$component = 'hkcs-' . $matches[1] . '-' . $matches[2] . $matches[3];

					if ($component === 'hkcs-2304b-c01') $chars[] = '??⿹';
					else $chars[] = '⿰⿱??';

					$chars[] = '{' . $component . '}';
					continue;
				}

				// Convert cdp prefixes to unknown
				if (
					strpos($component, 'cdp') !== false ||
					$component === 'hkcs_m233b5-v01-p03-s00' ||
					$component === 'hkcs_m233b5-v01-p03-s01'
				) {
					echo '<div class=warning>Warning: ' . $component . ' converted to 〓, included by <a href="https://en.glyphwiki.org/wiki/' . $this->name . '" target=_blank>' . $this->name . '</a></div>';
					$char = '〓';
				} else {
					try {
						$char = codepointToChar(nameToCodepoint($component));
					} catch (Exception $e) {
						echo '<div class=warning>Error: Component ' . $component . ' not recognized, included by ' . $this->name . '</div>';
						throw $e;
					}
				}

				if ($char === '乙') {
					if ($y1 > 80) {
						$component = 'hkcs_m4e59-p04-s00-virtual';
					}
				}

				if ($char === '巨') {
					if ($y2 < 120) {
						$component = 'hkcs_m5de8-p03-s00-virtual';
					}
				}

				if (strpos($component, '-p01-')) {
					$chars[] = '⿰';
				}
				if (strpos($component, '-p02-')) {
					$idx = count($chars) - 2;
					if ($idx == -1) {
						$chars[$idx + 2] = $chars[$idx + 1];
						$chars[$idx + 1] = '⿰';
					} else if ($idx >= 0 && $chars[$idx] !== '⿰' && $chars[$idx] !== '⿺' && $chars[$idx] !== '⿸') {
						$chars[] = '⿰';
					}
				}
				if (strpos($component, '-p03-')) {
					$chars[] = '⿱';
				}
				if (strpos($component, '-p04-')) {
					$idx = count($chars) - 2;
					if ($idx == -1) {
						$chars[$idx + 2] = $chars[$idx + 1];
						$chars[$idx + 1] = '⿱';
					} else if ($idx >= 0 && $chars[$idx] !== '⿱') {
						$chars[] = '⿱';
					}
				}
				if ($char === '𠘨') {
					$chars[] = '⿵𠘨';
					continue;
				}
				if ($component === 'hkcs_m5f1c-p05-s00') {
					$chars[] = '⿲弓弓→';
					continue;
				}
				if ($component === 'hkcs_m22a92-p05-s00') {
					$chars[] = '⿲手手→';
					continue;
				}
				if (substr($component, 0, 12) === 'hkcs_m29c32-') {
					$chars[] = '⿱⿲弓→弓鬲';
					continue;
				}
				if ($component === 'hkcs_m5c3e-p10-s00' || $component === 'hkcs_m5c3e-p05-s00') {
					$chars[] = '⿸㞑';
					continue;
				}
				if ($component === 'hkcs_m8863-p05-s00' || $component === 'hkcs_m8863-p05-s01' || $component === 'hkcs_m8863-p05-s02' || $component === 'hkcs_m8863-p05-s03' || $component === 'hkcs_m8863-p05-s04') {
					$chars[] = '⿳亠𧘇→';
					continue;
				}
				if ($component === 'hkcs_m4e59-p15-s00') {
					$chars[] = '⿹⺄';
					continue;
				}
				if ($char === '頃' && strpos($component, '-p05-') !== false) {
					$chars[] = '⿹頃';
					continue;
				}
				if ($component === 'hkcs_m884c-p05-s00' || $component === 'hkcs_m884c-p05-s01') {
					$chars[] = '⿲彳亍→';
					continue;
				}
				if (
					(strpos($component, '-p05-') !== false && $char === '𢦏') ||
					(strpos($component, '-p05-') !== false && $char === '戈') ||
					(strpos($component, '-p05-') !== false && $char === '气') ||
					(strpos($component, '-p05-') !== false && $char === '勹')) {
						$chars[] = '⿹';
				}
				if (
					$char === '屵' || 
					$char === '㫃' || 
					$char === '𠩺' || 
					$char === '疒' ||  
					(strpos($component, '-p05-') !== false && $char === '厥') || 
					(strpos($component, '-p05-') !== false && $char === '攸') || 
					(strpos($component, '-p05-') !== false && $char === '厤') || 
					(strpos($component, '-p05-') !== false && $char === '倝') || 
					(strpos($component, '-p05-') !== false && $char === '产') || 
					(strpos($component, '-p05-') !== false && $char === '朕') || 
					(strpos($component, '-p05-') !== false && $char === '尸') || 
					(strpos($component, '-p11-') !== false && $char === '尸') || 
					(strpos($component, '-p05-') !== false && $char === '戶') || 
					(strpos($component, '-p05-') !== false && $char === '府') || 
					(strpos($component, '-p10-') !== false && $char === '鹿') || 
					(strpos($component, '-p05-') !== false && $char === '鹿') || 
					(strpos($component, '-p05-') !== false && $char === '麻') || 
					(strpos($component, '-p05-') !== false && $char === '广') || 
					(strpos($component, '-p05-') !== false && $char === '厂') ||
					(strpos($component, '-p05-') !== false && $char === '虍')) {
						$chars[] = '⿸';
				}
				if ($char === '匚' || $char === '匸') {
					$chars[] = '⿷';
				}
				if ($char === '囗') {
					$chars[] = '⿴';
				}
				if (
					$char === '辶' || 
					$char === '廴' ||
					(strpos($component, '-p05-') !== false && $char === '更') ||
					(strpos($component, '-p05-') !== false && $char === '黽') ||
					(strpos($component, '-p05-') !== false && $char === '龜') ||
					(strpos($component, '-p05-') !== false && $char === '先') ||
					(strpos($component, '-p05-') !== false && $char === '礼') ||
					(strpos($component, '-p05-') !== false && $char === '是') ||
					(strpos($component, '-p05-') !== false && $char === '見') ||
					(strpos($component, '-p05-') !== false && $char === '光') ||
					(strpos($component, '-p05-') !== false && $char === '屯') ||
					(strpos($component, '-p05-') !== false && $char === '巴') ||
					(strpos($component, '-p05-') !== false && $char === '冘') ||
					(strpos($component, '-p11-') !== false && $char === '乳') || 
					(strpos($component, '-p05-') !== false && $char === '乙') || 
					(strpos($component, '-p05-') !== false && $char === '瓦') || 
					(strpos($component, '-p05-') !== false && $char === '堯') || 
					(strpos($component, '-p05-') !== false && $char === '支') || 
					(strpos($component, '-p10-') !== false && $char === '鬼') || 
					(strpos($component, '-p05-') !== false && $char === '鬼') || 
					(strpos($component, '-p05-') !== false && $char === '毛') || 
					(strpos($component, '-p05-') !== false && $char === '元') || 
					(strpos($component, '-p05-') !== false && $char === '黽') || 
					(strpos($component, '-p05-') !== false && $char === '克') || 
					(strpos($component, '-p05-') !== false && $char === '虎') || 
					(strpos($component, '-p05-') !== false && $char === '尢') || 
					(strpos($component, '-p05-') !== false && $char === '九') || 
					(strpos($component, '-p05-') !== false && $char === '瓜') || 
					(strpos($component, '-p05-') !== false && $char === '爪') || 
					(strpos($component, '-p05-') !== false && $char === '麥') || 
					(strpos($component, '-p05-') !== false && $char === '鼠') || 
					(strpos($component, '-p05-') !== false && $char === '走') || 
					(strpos($component, '-p05-') !== false && $char === '風')
					) {
						$chars[] = '⿺';
				}
				if (
					($char === '鬥' && strpos($component, '-p02-') === false) || 
					($char === '門' && strpos($component, '-p02-') === false)) {
						$chars[] = '⿵';
				}
				if ($char === '𦝠') {
					$chars[] = '⿵𦝠';
					continue;
				}
				if ($char === '龹') $char = '𠔉';
				if ($char === '訁') $char = '言';
				if ($char === '釒') $char = '金';
				if ($char === '㫃') $char = '𭤨';
				if ($char === '⻏') $char = '阝';
				if ($char === '牜') $char = '牛';
				if ($char === '㣺') $char = '⺗';
				if ($char === '氽') $char = '⿱人氺';
				$chars[] = $char;
			} else if (strlen($row) > 1) {
				if ($row === '1:0:2:20:165:108:165') continue;
				if ($row === '1:32:32:90:145:108:165') continue;
				if ($row === '1:32:32:90:185:108:165') continue;
				if ($row[0] === '1') {
					$chars[] = '??一丨';
				} else if ($row[0] === '2' || $row[0] === '3' || $row[0] === '4' || $row[0] === '5' || $row[0] === '6' || $row[0] === '7') {
					$chars[] = '??丿丶';
				}
			}
		}

		if (count($chars) === 5 && $chars[0] === '⿱' && $chars[1] === '艹' && $chars[3] === '⿱' && $chars[4] === '艹') {
			$mid = $chars[2];
			$chars = ['⿳', '艹', $mid, '艹'];
		}

		$ids = implode('', $chars);
		$cache[$cacheKey] = $ids;

		if (strpos($glyphData, 'hkcs_char-ff1d') !== false && isset($cache[$ids])) {
			return $ids . "\t~" . $cache[$ids];
		}

		if (strpos($glyphData, 'hkcs_char-2192') !== false && isset($cache[$ids])) {
			return $ids . "\t~" . $cache[$ids];
		}

		if (strpos($glyphData, 'hkcs_char-2245') !== false && isset($cache[$ids])) {
			$guess = $cache[$ids];
			$guess = str_replace('門', '门', $guess);
			$guess = str_replace('見', '见', $guess);
			$guess = str_replace('黽', '黾', $guess);
			$guess = str_replace('貝', '贝', $guess);
			$guess = str_replace('鹵', '卤', $guess);
			$guess = str_replace('闌', '阑', $guess);
			$guess = str_replace('車', '车', $guess);
			$guess = str_replace('金', '钅', $guess);
			$guess = str_replace('風', '风', $guess);
			$guess = str_replace('齊', '齐', $guess);
			$guess = str_replace('飠', '饣', $guess);
			$guess = str_replace('馬', '马', $guess);
			$guess = str_replace('巠', '𢀖', $guess);
			$guess = str_replace('韋', '韦', $guess);
			$guess = str_replace('頁', '页', $guess);
			$guess = str_replace('糸', '纟', $guess);
			$guess = str_replace('魚', '鱼', $guess);
			$guess = str_replace('言', '讠', $guess);
			$guess = str_replace('麥', '麦', $guess);
			$guess = str_replace('齒', '齿', $guess);
			$guess = str_replace('鳥', '鸟', $guess);
			return $ids . "\t~" . $guess;
		}

		return $cache[$cacheKey];
	}
}