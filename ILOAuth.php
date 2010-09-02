<?php

require_once 'ILAuthenticator.php';

class ILOAuth extends ILAuthenticator
{
	protected $req_url;
	protected $authurl;
	protected $acc_url;
	protected $api_url;
	protected $conskey;
	protected $conssec;
	protected $sig_method;
	protected $auth_type;
	protected $oauth;
	protected $json;
	protected $vrfy;

	function __construct(ILPassport $pass) {
		parent::__construct($pass);
		$this->oauth = false;
	}

	function hook_post() {

	}

	function hook_body_footer() {

	}

	function hook_body_header() {
		print_r($this->json);
	}

	function hook_footer() {

	}

	function hook_header() {
		if(get_class($this) === 'ILOAuth') {
			throw new Exception('Ошибка наследования. Класс ILOauth нельзя использовать напрямую');
		}

		if(!isset($_SESSION['state'])) {
			$_SESSION['state'] = false;
		}
		
		if(!isset($_GET['oauth_token']) && $_SESSION['state']==1) $_SESSION['state'] = 0;
		$oauth = new OAuth($this->conskey,$this->conssec,$this->sig_method,$this->auth_type);
		$oauth->enableDebug();
		
		if(!isset($_GET['oauth_token']) && !$_SESSION['state']) {
			$request_token_info = $oauth->getRequestToken($this->req_url);
			$_SESSION['secret'] = $request_token_info['oauth_token_secret'];
			$_SESSION['state'] = 1;
			header('Location: '.$this->authurl.'?oauth_token='.$request_token_info['oauth_token']);
			exit;
		} else if($_SESSION['state']==1) {
			$oauth->setToken($_GET['oauth_token'],$_SESSION['secret']);
			$access_token_info = $oauth->getAccessToken($this->acc_url);
			$_SESSION['state'] = 2;
			$_SESSION['token'] = $access_token_info['oauth_token'];
			$_SESSION['secret'] = $access_token_info['oauth_token_secret'];
		}
		$oauth->setToken($_SESSION['token'],$_SESSION['secret']);
		$oauth->fetch("$this->api_url/$this->vrfy");
		$this->json = json_decode($oauth->getLastResponse());
		$this->handleInfo($this->json);
		header("Location: https://passport.tlt.ru/");
		exit;
	}
	
	function handleInfo($jsonData)
	{
		throw new Exception('Ошибка авторегистрации: сервис не специализирован');
	}
}
