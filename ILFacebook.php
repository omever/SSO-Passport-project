<?php
require_once 'ILAuthenticator.php';
require_once 'facebook-php-sdk-08909f3/src/facebook.php';

class ILFacebook extends ILAuthenticator
{
	private $facebook = false;
	private $me = false;
	
	function __construct(ILPassport $pass = null) {
		parent::__construct($pass);
		$this->facebook = new Facebook(array(
			'appId'  => '147917501886695',
			'secret' => 'd9d4fadf543c3dcc2dc00bcc331edc62',
			'cookie' => true,
		)); 
	}
	
	function hook_post() {
		
	}
	
	function hook_body_footer() {
		
	}
	
	function hook_footer() {
		
	}
	
	function hook_header() {
		if(isset($_GET['action']) && $_GET['action'] === 'auth_facebook') {
			$session = $this->facebook->getSession();

			$this->me = null;
			// Session based API call.
			if ($session) {
				$uid = $this->facebook->getUser();
				$this->me = $this->facebook->api('/me');
				if(!empty($this->me['id'])) {
					$this->identity = new ILExternalIdentity();
					$this->identity->extid = $this->me['id'];
					$this->identity->nickname = preg_replace('/^http:\/\/www.facebook.com\//', $this->me['link'], '');;
					$this->identity->fullname = $this->me['first_name'] . ' ' . $this->me['last_name'];
					$this->identity->email = '';
					$this->identity->service = 'facebook';
					
					$this->register();
				}
			} else {
				header("Location: " . $this->facebook->getLoginUrl());
			}
		}
	}

	function hook_body_header() {
	}
};