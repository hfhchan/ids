<?php

require_once 'char-utils.php';

$file = file('canonicalize.txt');
$normalizedEntries = [];
$differentiatedComponents = [];
foreach ($file as $row) {
    $row = trim($row);
    if (empty($row) || $row[0] === '#') continue;
    list($type, $key, $val) = explode("\t", $row, 3);
    if ($type === 'diff') {
        list($type, $key, $val1, $val2) = explode("\t", $row, 4);
        $differentiatedComponents[] = [trim($key), trim(substr($val1, 1)), $val2];
    } else if ($type === 'preferred') {
        $preferredVariants[] = [trim($key), trim(substr($val, 1))];
    } else {
        $normalizedEntries[$type][] = [trim($key), trim($val)];
    }
}

function charToCharWithCodepoint($char) {
    return $char . ' (' . charToCodepoint($char) . ')';
}

?>
<meta charset=utf-8>
<title>IDS Canonicalization Table</title>
<style>
body{margin:16px;font-family:sans-serif}
main{max-width:640px;margin:0 auto}
h1{margin:16px auto}
table{width:100%;table-layout:fixed;border-collapse:collapse}
td,th{border:1px solid #ccc;padding:4px 8px}
th{text-align:left}

.alignCenter{text-align:center}
</style>
<main>
<h1>IDS Canonicalization Table</h1>
<h2>Normalization Table</h2>
<p>The CJK Unified Ideographs are preferred over the CJK Radicals, unless the CJK Radicals have a more consistent form such as ⺗ (U+2E97). Extension B forms are also preferred over URO+ forms.</p>
<table>
    <col width="40%">
    <col width="20%">
    <col width="40%">
    <tr>
        <th>Variant Char</th>
        <td></td>
        <th>Normalized Form</th>
    </tr>
<? foreach ($normalizedEntries['identical'] as $entry) { ?>
    <tr>
        <td><?=htmlspecialchars(charToCharWithCodepoint($entry[1]))?></td>
        <td class=alignCenter> → </td>
        <td><?=htmlspecialchars(charToCharWithCodepoint($entry[0]))?></td>
    </tr>
<? } ?>
<? foreach ($normalizedEntries['exception'] as $entry) { ?>
    <tr>
        <td><?=htmlspecialchars(charToCharWithCodepoint($entry[1]))?>*</td>
        <td class=alignCenter> → </td>
        <td><?=htmlspecialchars(charToCharWithCodepoint($entry[0]))?></td>
    </tr>
<? } ?>
</table>

<p>The traditional print form is preferred over the Kai-style form for left hand side components such as 示 and 糸.</p>
<table>
    <col width="40%">
    <col width="20%">
    <col width="40%">
    <tr>
        <th>Variant Char</th>
        <td></td>
        <th>Normalized Form</th>
    </tr>
<? foreach ($normalizedEntries['print'] as $entry) { ?>
    <tr>
        <td><?=htmlspecialchars(charToCharWithCodepoint($entry[1]))?></td>
        <td class=alignCenter> → </td>
        <td><?=htmlspecialchars(charToCharWithCodepoint($entry[0]))?></td>
    </tr>
<? } ?>
</table>

<p>Left side forms with final H stroke turned to upwards slant are replaced by their base form (e.g. 𤣩).</p>
<p>Upper forms where the covering component have receeded are replaced by their base form (e.g. 雨、尙).</p>
<table>
    <col width="40%">
    <col width="20%">
    <col width="40%">
    <tr>
        <th>Variant Char</th>
        <td></td>
        <th>Normalized Form</th>
    </tr>
<? foreach ($normalizedEntries['variant'] as $entry) { ?>
    <tr>
        <td><?=htmlspecialchars(charToCharWithCodepoint($entry[1]))?></td>
        <td class=alignCenter> → </td>
        <td><?=htmlspecialchars(charToCharWithCodepoint($entry[0]))?></td>
    </tr>
<? } ?>
</table>

<p>Rejected forms</p>
<table>
    <col width="40%">
    <col width="20%">
    <col width="40%">
    <tr>
        <th>Rejected Char</th>
        <td></td>
        <th>Normalized Form</th>
    </tr>
<? foreach ($normalizedEntries['reject'] as $entry) { ?>
    <tr>
        <td><?
        $list = explode(',', $entry[0]);
        foreach ($list as $char) echo htmlspecialchars(charToCharWithCodepoint(trim($char)))
        ?></td>
        <td class=alignCenter> → </td>
        <td><?
        echo htmlspecialchars($entry[1]);
        ?></td>
    </tr>
<? } ?>
</table>

<h2>Preferred Variants</h2>
<table>
    <col width="40%">
    <col width="20%">
    <col width="40%">
    <tr>
        <th>Variant Char</th>
        <td></td>
        <th>Preferred Form</th>
    </tr>
<? foreach ($preferredVariants as $entry) { ?>
    <tr>
        <td><?=htmlspecialchars(charToCharWithCodepoint($entry[1]))?></td>
        <td class=alignCenter> → </td>
        <td><?=htmlspecialchars(charToCharWithCodepoint($entry[0]))?></td>
    </tr>
<? } ?>
</table>

<h2>Differentiated Components</h2>
<table>
    <col width="40%">
    <col width="20%">
    <col width="40%">
    <tr>
        <th>Component 0</th>
        <td></td>
        <th>Component 1</th>
    </tr>
<? foreach ($differentiatedComponents as $entry) { ?>
    <tr>
        <td><?=htmlspecialchars(charToCharWithCodepoint($entry[0]))?></td>
        <td class=alignCenter>≠</td>
        <td><?=htmlspecialchars(charToCharWithCodepoint($entry[1]))?></td>
    </tr>
    <tr>
        <td colspan=3><?=htmlspecialchars($entry[2])?></td>
    </tr>
<? } ?>
</table>

<h2>Enforced Structures</h2>
<p>These characters always use ⿺ instead of ⿰: 麥、鼠</p>
</main>
