<?php
// OpenID auth libs
require_once "Auth/OpenID/Consumer.php";
require_once "Auth/OpenID/MemcachedStore.php";
require_once "Auth/OpenID/SReg.php";
require_once "Auth/OpenID/PAPE.php";
require_once "ILAuthenticator.php";

class InvalidOpenIDException extends Exception {};
class RedirectionException extends Exception {};
class StoreException extends Exception {};
class ConsumerException extends Exception {};

class ILOpenID extends ILAuthenticator
{
	private $store = FALSE;
	private $consumer = FALSE;
	public $identity = FALSE;
	
	public function __construct(ILPassport $pass = null) {
		parent::__construct($pass);
		
		$memcache = new Memcache();
		$memcache->connect("127.0.0.1");
		$this->store = new Auth_OpenID_MemcachedStore($memcache);
		if(!$this->store) {
			throw new StoreException('Внутренняя ошибка данных. Повторите запрос');
		}
	
		$this->consumer = new Auth_OpenID_Consumer($this->store);
		if(!$this->consumer) {
			throw new ConsumerException('Внутренняя ошибка openid. Повторите запрос');
		}
	}
	
	public function getScheme() {
		$scheme = 'http';
		if (isset($_SERVER['HTTPS']) and $_SERVER['HTTPS'] == 'on') {
			$scheme .= 's';
		}
		return $scheme;
	}

	public function getReturnTo() {
		return 'https://passport.tlt.ru/?action=openid_finish';
	}

	public function getTrustRoot() {
		return 'https://passport.tlt.ru/';
	}

	public function openid_check($id)
	{
		$auth_request = $this->consumer->begin($id);
		if(!$auth_request) {
			throw InvalidOpenIDException("Ошибка авторизации: некорректный openid идентификатор");
		}
		
		$sreg_request = Auth_OpenID_SRegRequest::build(array('nickname'), array('fullname', 'email'));
		if($sreg_request) {
			$auth_request->addExtension($sreg_request);
		}
		
		if ($auth_request->shouldSendRedirect()) {
			$redirect_url = $auth_request->redirectURL($this->getTrustRoot(), $this->getReturnTo());

			if (Auth_OpenID::isFailure($redirect_url)) {
				throw RedirectionException("Ошибка переадресации на сервер: " . $redirect_url->message);
			} else {
				// Send redirect.
				header("Location: ".$redirect_url);
			}
		} else {
			// Generate form markup and render it.
			$form_id = 'openid_message';
			$form_html = $auth_request->htmlMarkup($this->getTrustRoot(), $this->getReturnTo(),
			false, array('id' => $form_id));

			// Display an error if the form markup couldn't be generated;
			// otherwise, render the HTML.
			if (Auth_OpenID::isFailure($form_html)) {
				throw RedirectionException("Ошибка переадресации на сервер: " . $form_html->message);
			} else {
				print $form_html;
			}
		}

	}
	
	public function openid_finish()
	{
		$response = $this->consumer->complete($this->getReturnTo());
		
		if($response->status == Auth_OpenID_CANCEL) {
			return 0;
		}
		
		if ($response->status == Auth_OpenID_FAILURE) {
			return -1;
		}
		
		if ($response->status == Auth_OpenID_SUCCESS) {
			$this->identity = new ILExternalIdentity();
			$this->identity->extid = $response->getDisplayIdentifier();
			$this->identity->service = 'openid';
			$sreg_resp = Auth_OpenID_SRegResponse::fromSuccessResponse($response);
			$sreg = $sreg_resp->contents();

			// Грязный хак из-за "модных" ребят из mail.ru, которые не осилили NS у sreg:
			if($response->endpoint->server_url == 'http://openid.mail.ru/login') {
				$this->identity->email = $response->getSigned('http://specs.openid.net/auth/2.0', 'sreg.email');
				$this->identity->nickname = $response->getSigned('http://specs.openid.net/auth/2.0', 'sreg.nickname');
				$this->identity->fullname = $response->getSigned('http://specs.openid.net/auth/2.0', 'sreg.fullname');
			} else {
				if (!empty($sreg['email'])) {
					$this->identity->email = $sreg['email'];
				} else {
					$this->identity->email = '';
				}
					
				if (!empty($sreg['nickname'])) {
					$this->identity->nickname = $sreg["nickname"];
				} else {
					$this->identity->nickname = $this->identity->extid;
				}
					
				if (!empty($sreg['fullname'])) {
					$this->identity->fullname = $sreg['fullname'];
				} else {
					$this->identity->fullname = '';
				}
			}
			
			return 1;
		}
	}
	
	public function hook_body_footer() {
		
	}
	
	public function hook_body_header() {
		
	}
	
	public function hook_footer() {
	
	}
	
	public function hook_header() {
		if(empty($_GET['action'])) {
			return false;
		}

		if($_GET["action"] === "openid_check") {
			return $this->openid_check($_POST['openid']);
		}

		if($_GET["action"] === "openid_finish") {
			if($this->openid_finish() === 1) {
				$this->register();
			}
		}
	}
	
	public function hook_post() {
		
	}
}
