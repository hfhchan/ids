<!doctype html>
<style>body{font-family:Arial}</style>
<?php

require_once 'lookup-lib.php';

if (!file_exists('./dump_newest_only.txt')) {
?>
<div><b>Error</b>: dump_newest_only.txt not found. Please download <a href="http://glyphwiki.org/dump.tar.gz">http://glyphwiki.org/dump.tar.gz</a>, unzip it, then copy the dump_newest_only.txt into this directory.</div>
<?
	exit;
}

date_default_timezone_set('UTC');

$db = new DB();
$db->db->exec('
	CREATE TABLE IF NOT EXISTS "cache" (
		"id"	TEXT NOT NULL,
		"data"	TEXT,
		"lastmod"	DATETIME,
		PRIMARY KEY("id")
	);
');

// Check to see if updating needed
$oldest = 0;
try {
	$q = $db->db->query('SELECT MIN(lastmod) FROM "cache"');
	$date = $q->fetchColumn();
	$oldest = strtotime($date);
} catch (Exception $e) {
	$oldest = 0;
}

$filemodInt = filemtime('./dump_newest_only.txt');

$filemod = date('Y-m-d H:i:s', $filemodInt);
$daysAgo = (time() - $filemodInt) / 24 / 60 / 60;
if ($daysAgo >= 2) {
	echo '<div><b>Warning</b>: dump_newest_only.txt seems too old: ' . $filemod . '</div>';
	echo '<div>Download here: <a href="http://glyphwiki.org/dump.tar.gz">http://glyphwiki.org/dump.tar.gz</a></div>';
} else {
	echo '<div>dump_newest_only.txt was modified on: ' . $filemod . ' (' . number_format($daysAgo, 2) . ' days ago)</div>';
}

if ($filemodInt <= $oldest) {
	echo '<div><b>Notice</b>: dump_newest_only.txt seems to be older than all imported entries; skipping import.</div>';
	exit;
}

$db->db->beginTransaction();
$db->db->exec('DELETE FROM "cache" WHERE 1 = 1');
$q = $db->db->prepare('INSERT INTO "cache" VALUES (?, ?, ?)');
$contents = file_get_contents('./dump_newest_only.txt');
$lines = 0;
strtok($contents, "\n");
strtok("\n");
while ($line = strtok("\n")) {
	if (strpos($line, ' hkcs_') !== 0) continue;
	list($glyphName, $relatedChar, $data) = explode('|', $line, 3);
	$glyphName = trim($glyphName);
	$data = trim($data);
	$lines++;
	$q->execute([
		$glyphName,
		$data,
		$filemod
	]);
}
$db->db->commit();

echo '<div>Wrote ' . $lines . ' lines.</div>';
