<?php

set_exception_handler(function (Throwable $e) {
	echo '<p><b>Uncaught Exception</b> ('.get_class($e).')<br>';
	echo htmlspecialchars($e->getMessage());
	echo ' on line <b>'.$e->getLine().'</b> in file <b>'.$e->getFile().'</b>.</p>';
	echo '<div><b>Stack Trace:</b></div>';
	echo '<pre style="overflow:auto">';
	echo htmlspecialchars($e->getTraceAsString());
	echo '</pre>';
	exit;
});