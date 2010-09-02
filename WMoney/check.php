<?php
require_once 'WMoney.php';

header("Content-Type: text/plain; charset=windows-1251");

$log = fopen('/tmp/webmoney.log', 'a');

$wm = new WMoney();

fwrite($log, print_r($_POST, true));

try {
	if($wm->checkPayment()) {
		echo 'YES';
		fwrite($log, "Yes, accepted \n");
	} else {
		fwrite($log, "Nope, rejecting\n");
	}
} 
catch(Exception $e)
{
	fwrite($log, $e->getMessage());

	echo $e->getMessage();
}

fclose($log);
