<?php

require_once 'ILOAuth.php';

class ILTwitter extends ILOAuth
{
	function __construct(ILPassport $pass = null) {
		parent::__construct($pass);
		$this->acc_url = "https://api.twitter.com/oauth/access_token";
		$this->api_url = "https://api.twitter.com/1";
		$this->auth_type = 0;
		$this->authurl = "https://api.twitter.com/oauth/authorize";
		$this->conskey = "H495f962IvSC9SiuVrNTrQ";
		$this->conssec = "poqSUnfW0fl8oXjDXF7OUBbKvpXD4paeazZV7LAun8";
		$this->req_url = "https://api.twitter.com/oauth/request_token";
		$this->sig_method = OAUTH_SIG_METHOD_HMACSHA1;
		$this->vrfy = 'account/verify_credentials.json';
	}
	
	function hook_header() {
		if(isset($_GET['action']) && $_GET['action'] === 'auth_twitter') {
			ILOAuth::hook_header();
		}
	}
	
	function handleInfo($jsonData)
	{
		$this->identity = new ILExternalIdentity;
		$this->identity->service = 'twitter';
		$this->identity->extid = $jsonData->id;
		$this->identity->email = '';
		$this->identity->nickname = $jsonData->screen_name;
		$this->identity->fullname = $jsonData->name;
		
		$this->register();
	}
};