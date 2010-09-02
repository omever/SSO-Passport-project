<?php

require_once('../cachelib.php');

class requestTypeException extends Exception {};
class invalidIdException extends Exception {};
class invalidKeyException extends Exception {};

class WMoney
{
	private $retpath = null;
	private $id = null;
	private $summ = null;
	private $desc = null;
	private $purse = '';
	private $secretkey = '';
	
	function __construct($retpath = null) {
		$this->retpath = $retpath;
	}

	function makePayment($account, $summ, $description) {
		$sql = "BEGIN
			:id := PKG_PAYMENT.WEBMONEY_REQUEST(:account, :summ, :description);
		END;";
		
		$cache = new ISGCache();
		$rv = $cache->sql($sql, array('account' => $account, 'summ' => $summ, 'description' => $description));
		
		$this->id = $rv['bind']['ID'][0];
		$this->summ = $summ;
		$this->desc = $description;
	}

	function constructFullHtmlForm() {
		if($this->id == null) {
			throw new invalidIdException('Invalid identificator. First you must call a makePayment method');
		}
		
		$form = "
<form id='webmoney_request_" .htmlspecialchars($this->id). "' 
	action='https://merchant.webmoney.ru/lmi/payment.asp' method='post'>
<input type='hidden' name='LMI_PAYEE_PURSE' value='" .htmlspecialchars($this->purse). "'/>
<input type='hidden' name='LMI_PAYMENT_AMOUNT' value='".htmlspecialchars($this->summ). "' />
<input type='hidden' name='LMI_PAYMENT_NO' value='" .htmlspecialchars($this->id). "' />
<input type='hidden' name='LMI_PAYMENT_DESC' value='" .htmlspecialchars($this->desc). "' />
<input type='hidden' name='LMI_SIM_MODE' value=0 />
<input type='submit' />
</form>
<script type='text/javascript'>
	var form = document.getElementById('webmoney_request_".htmlspecialchars($this->id)."');
	form.submit();
</script>		
		";
		
		return $form;
	}
	
	function checkPaymentPre1()
	{
		 $payee_purse = $_POST['LMI_PAYEE_PURSE'];
		 $amount = $_POST['LMI_PAYMENT_AMOUNT'];
		 $payno = $_POST['LMI_PAYMENT_NO'];
		 $mode = $_POST['LMI_MODE'];
		 $wmid = $_POST['LMI_PAYER_WM'];
		 $purse = $_POST['LMI_PAYER_PURSE'];
		 $desc = $_POST['LMI_PAYMENT_DESC'];
		 
		 if(!empty($_POST['LMI_CAPITALLER_WMID'])) {
		 	$info .= 'Capitaller WMID: ' . $_POST['LMI_CAPITALLER_WMID'];
		 	$info .= ";";
		 }
		 
		 if(!empty($_POST['LMI_PAYMER_NUMBER'])) {
		 	$info .= 'Paymer num:' . $_POST['LMI_PAYMER_NUMBER'] . ', email:' . $_POST['LMI_PAYMER_EMAIL'];
		 	$info .= ";";
		 }
		 
		 if(!empty($_POST['LMI_EURONOTE_NUMBER'])) {
		 	$info .= 'Euronote num:' . $_POST['LMI_EURONOTE_NUMBER'] . ', email:' . $_POST['LMI_EURONOTE_EMAIL'];
		 	$info .= ";";
		 }
		 
		 if(!empty($_POST['LMI_TELEPAT_ORDERID'])) {
		 	$info .= 'Telepat id:' . $_POST['LMI_TELEPAT_ORDERID'] . ', phone:' . $_POST['LMI_TELEPAT_PHONENUMBER'];
		 	$info .= ";";
		 }
		 
		 if(!empty($_POST['LMI_PAYMENT_CREDITDAYS'])) {
		 	$info .= 'Credit days:' . $_POST['LMI_PAYMENT_CREDITDAYS'];
		 	$info .= ";";
		 }
		 
		 if(!empty($_POST['LMI_CASHIER_ATMNUMBERINSIDE'])) {
		 	$info .= 'Terminal type:' . $_POST['LMI_CASHIER_ATMNUMBERINSIDE'];
		 	$info .= ";";
		 }
		 
		 $sql = 'BEGIN
		 	:result := PKG_PAYMENT.WEBMONEY_CHECK_V1(:id, :info);
		 END;';
		 
		 $cache = new ISGCache();
		 $rv = $cache->sql($sql, array('id'=>$payno, 'info'=>$info));
		 
		 if($rv['bind']['RESULT'][0] == -2) {
		 	throw new Exception('Request already served');
		 }

		 if($rv['bind']['RESULT'][0] == -1) {
		 	throw new Exception('No such id exist: ' . $payno);
		 }
		 
		 if($rv['bind']['RESULT'][0] != 0) {
		 	throw new Exception('Unknown error code');
		 }
		 
		 return true;
	}

	function checkPaymentPre2()
	{
		 $payee_purse = $_POST['LMI_PAYEE_PURSE'];
		 $amount = $_POST['LMI_PAYMENT_AMOUNT'];
		 $payno = $_POST['LMI_PAYMENT_NO'];
		 $mode = $_POST['LMI_MODE'];
		 $wmid = $_POST['LMI_PAYER_WM'];
		 $purse = $_POST['LMI_PAYER_PURSE'];
		 $desc = $_POST['LMI_PAYMENT_DESC'];
		 $date = $_POST['LMI_SYS_TRANS_DATE'];
		 $hash = $_POST['LMI_HASH'];
		 $info = '';
		 
		 $checkstring = $payee_purse 
		 	. $amount 
		 	. $payno 
		 	. $mode 
		 	. $_POST['LMI_SYS_INVS_NO'] 
		 	. $_POST['LMI_SYS_TRANS_NO'] 
		 	. $date 
		 	. $_POST['LMI_SECRET_KEY']
		 	. $purse
		 	. $wmid;
		 	
		 if($hash != strtoupper(md5($checkstring))) {
		 	throw new invalidKeyException('Invalid signature: ' . $hash . ' != ' . strtoupper(md5($checkstring))); 
		 }
		 
		 if($_POST['LMI_SECRET_KEY'] != $this->secretkey) {
		 	throw new invalidKeyException('Invalid secret key');
		 }
		 
		 $info .= 'DEST:' . $payee_purse . ';';
		 $info .= 'MODE:' . $mode . ';';
		 $info .= 'WMID:' . $wmid . ';';
		 $info .= 'PURSE:' . $purse . ';';
		 $info .= 'DESC:' . $desc . ';';
		 
		 if(!empty($_POST['LMI_SYS_INVS_NO'])) {
		 	$info .= 'INVSNO:' . $_POST['LMI_SYS_INVS_NO'];
		 	$info .= ';';
		 }
		 
		 if(!empty($_POST['LMI_SYS_TRANS_NO'])) {
		 	$info .= 'TRANSNO:' . $_POST['LMI_SYS_TRANS_NO'];
		 	$info .= ';';
		 }
		 
		 if(!empty($_POST['LMI_CAPITALLER_WMID'])) {
		 	$info .= 'Capitaller WMID: ' . $_POST['LMI_CAPITALLER_WMID'];
		 	$info .= ";";
		 }
		 
		 if(!empty($_POST['LMI_PAYMER_NUMBER'])) {
		 	$info .= 'Paymer num:' . $_POST['LMI_PAYMER_NUMBER'] . ', email:' . $_POST['LMI_PAYMER_EMAIL'];
		 	$info .= ";";
		 }
		 
		 if(!empty($_POST['LMI_EURONOTE_NUMBER'])) {
		 	$info .= 'Euronote num:' . $_POST['LMI_EURONOTE_NUMBER'] . ', email:' . $_POST['LMI_EURONOTE_EMAIL'];
		 	$info .= ";";
		 }
		 
		 if(!empty($_POST['LMI_TELEPAT_ORDERID'])) {
		 	$info .= 'Telepat id:' . $_POST['LMI_TELEPAT_ORDERID'] . ', phone:' . $_POST['LMI_TELEPAT_PHONENUMBER'];
		 	$info .= ";";
		 }
		 
		 if(!empty($_POST['LMI_PAYMENT_CREDITDAYS'])) {
		 	$info .= 'Credit days:' . $_POST['LMI_PAYMENT_CREDITDAYS'];
		 	$info .= ";";
		 }
		 
		 if(!empty($_POST['LMI_ATM_WMTRANSID'])) {
		 	$info .= 'Terminal transid:' . $_POST['LMI_ATM_WMTRANSID'];
		 	$info .= ';';
		 }
		 
		 if(!empty($_POST['LMI_CASHIER_ATMNUMBERINSIDE'])) {
		 	$info .= 'Terminal type:' . $_POST['LMI_CASHIER_ATMNUMBERINSIDE'];
		 	$info .= ";";
		 }
		 
		 $sql = 'BEGIN
		 	:result := PKG_PAYMENT.WEBMONEY_CHECK_V2(:id, :info, TO_TIMESTAMP_TZ(:date || \' Europe/Moscow\', \'YYYYMMDD HH24:MI:SS TZR\'));
		 END;';
		 
		 $cache = new ISGCache();
		 $rv = $cache->sql($sql, array('id'=>$payno, 'info'=>$info, 'date'=>$date));
		 
		 if(count($rv) == 0) {
		 	throw new Exception('Error executing query');
		 }
		 if($rv['bind']['RESULT'][0] == -2) {
		 	throw new Exception('Request already served');
		 }

		 if($rv['bind']['RESULT'][0] == -1) {
		 	throw new Exception('No such id exist');
		 }
		 
		 if($rv['bind']['RESULT'][0] != 0) {
		 	throw new Exception('Unknown error code');
		 }
		 
		 return true;
	}
	
	function checkPayment()
	{
		if(!empty($_POST['LMI_PREREQUEST']) && $_POST['LMI_PREREQUEST'] == 1) {
			return $this->checkPaymentPre1();
		}
		
		return $this->checkPaymentPre2();
	}
}

?>