<?php

//var_dump($GLOBALS);

$config_file = '';
$verbose = false;

// WORKAROUND... non mi passa argv
//$argv = array_keys($_GET);

$i = 1;
while ($i < sizeof($argv)) {
	switch ($argv[$i]) {
		case '-c':
			$i++;
			$config_file = $argv[$i];
			break;
		case '-v':
			$verbose = true;
			break;
	}
	$i++;
}

// WORKAROUND... Prendendo i parametri con _GET mi sostituiste . con _
$config_file=str_replace('_', '.', $config_file);

$opt = parse_ini_file($config_file, true);
$string = rtrim(file_get_contents('php://stdin'));


if ($verbose) {
	print "DEBUG: loaded ini\n";
	var_dump($opt);
}


print doAction($string, $opt['Default']['action'], $opt);

function doAction($string, $act, $actList) {
	global $verbose;
	
	if ($verbose) print "DEBUG: Parsing set of " . sizeof($act) . " rules.\n";
	
	for ($i = 0; $i < sizeof($act); $i++) {
		if ($verbose) print "DEBUG: Parsing rule ".($i+1)." of " . sizeof($act) . " \"$act[$i]\".\n";
		$matches = array();
		$n_match = preg_match('/([^\t]+)\t+([^\t]+)(\t+([^\t]+))?/', $act[$i], $matches);

		if ($n_match) {
			$regex = $matches[1];
			$action = $matches[2];
			$param = '';
			if (isset($matches[4])) {
				$param = $matches[4];
			}
			
			if ($verbose) print "DEBUG: Checking $string against rule $act[$i]\n";

			if ($action == 'goto') {
				if (preg_match("/$regex/", $string)) {
					if ($verbose) print "DEBUG: Match, Going to $param\n";
					return doAction($string, $actList[$param]['action'], $actList);
				}
			} elseif ($action == 'gosub') {
				if (preg_match("/$regex/", $string)) {
					if ($verbose) print "DEBUG: Match, Calling subroutine $param\n";
					$string = doAction($string, $actList[$param]['action'], $actList);
				}

			} elseif ($action == 'return') {
				if (preg_match("/$regex/", $string)) {
					if ($verbose) print "DEBUG: Match, Stopping execution\n";
					return $string;
				}
			} elseif (substr($action, 0, 7) == 'replace') {
				$reg_opt = explode('_', $action);
				$limit = 1;
				$insensitive = false;
				$loop = false;
				for ($j = 1; $j < sizeof($reg_opt); $j++) {
					switch($reg_opt[$j]) {
						case 'i':
							$insensitive = true;
							break;
						case 'all':
							$limit = -1;
							break;
						case 'loop':
							$loop = true;
							break;
					}
				}

				$pattern = "/$regex/";
				if ($insensitive) $pattern .= 'i';

				$count = 0;
				do {
					if ($verbose) print "DEBUG: Replacing $limit occurencies of $pattern with $param\n";
					$string = preg_replace($pattern, $param, $string, $limit, $count);
					if ($verbose) print "DEBUG: result - $string\n\n";
				} while ($loop && $count > 0);
			} else {
				print "ERROR: Unknown action $action\n";
			}
		} else {
			print "ERROR: Invalid command format\n";
		}
	}
	return $string;
}

?>
