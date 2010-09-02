<?php 
require_once 'WMoney.php';

$form = null;

if(!empty($_POST['summ'])) {
	$wm = new WMoney();
	$wm->makePayment($_POST['account'], $_POST['summ'], 'Infoline service payment');
	$form = $wm->constructFullHtmlForm(); 
}
?>
<!DOCTYPE unspecified PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html>
<head>
<META NAME="webmoney.attestation.label" CONTENT="webmoney attestation label#5BD35F93-7655-4DAE-BF42-95E808B9B5E5"> 
</head>
<body>
<?php 
	if(!empty($form)) {
		echo $form;
	}
?>
<form method='POST'>
<input name='account' value='omeg'/>
<input name='summ'/>
<input type='submit'/>
</form>
</body>
</html>
