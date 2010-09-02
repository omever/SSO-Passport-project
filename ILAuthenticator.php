<?php

class ILExternalIdentity
{
	public $extid;
	public $nickname;
	public $fullname;
	public $intid;
	public $email;
	public $service;
}

abstract class ILAuthenticator
{
	public $identity = false;
	protected $passport;
	
	public function __construct(ILPassport $passport = null) {
		$this->passport = $passport;
	}
	
	public function init(ILPassport $passport) {
		$this->passport = $passport;
	}
	
	abstract public function hook_post();
	abstract public function hook_header();
	abstract public function hook_body_header();
	abstract public function hook_body_footer();
	abstract public function hook_footer();
	
	protected function register() {
		if($this->passport->register_external($this->identity)) {
			$this->passport->service_auth_ok();
		}
	}
}
