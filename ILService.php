<?php
/**
 * @package ILPassport
 * @author Grigory Holomiev
 */

require_once 'ILSession.php';
require_once 'ILStorage.php';

/**
 * class ILService
 * @package ILPassport
 * @subpackage ILService
 */
class ILService implements ILStorage
{
	/**
	 * 
	 * Атрибут хранящий ссылку на местонахождение паспорта. Все запросы
	 * будут направляться по этой ссылке.
	 * @var string
	 */
	public $passport_url = "https://passport.ru/";
	/**
	 * 
	 * Атрибут хранящий ссылку на местонахождение "точки возврата данного сервиса".
	 * Все ответы паспорта будут направляться по этой ссылке.
	 * @var string
	 */
	public $service_retpath = false;
	
	/**
	 * 
	 * Атрибут хранящий экземпляр класса ILSession.
	 * @var ILSession
	 */
	public $session = false;

	/**
	 * 
	 * Конструктор класса устанавливающий атрибуты.
	 * @param string $service_retpath URL возврата результата с паспорта.
	 * @throws Exception не указан путь возврата
	 */
	public function __construct($service_retpath)
	{
		if(!$service_retpath) {
		    throw new Exception("Service return path must be there");
		}
		$this->session = new ILSession();
		$this->service_retpath = $service_retpath;
	}

	/**
	 * 
	 * Проверка валидности сессии. В случае, если информация о валидности
	 * недоступна или устарела, запросить данные у паспорта (переадресация броузера
	 * клиента на паспорт и автоматический возврат с результатом без
	 * загрузки каких-либо форм на паспорте)
	 */
	public function check_session( ) {
		if($this->session->exist()) {
			return $this->session->valid;
		} else {
			return $this->validate_passport();
		}
	} // end of member function check_session

	/**
	 * 
	 * Возвращает булевое значение истина только если сессия существует и валидна.
	 */
	public function is_session_ok() {
		return ($this->session->exist() && $this->session->valid);
	}

	/**
	 * 
	 * Проверка цифровой подписи ответа паспорта с помощью публичного ключа паспорта. 
	 * @param string $data переданные паспортом данные
	 * @param string $sig переданная паспортом подпись этих данных
	 */
	public function check_sign( $data, $sig ) {
		$pubkey = "-----BEGIN PUBLIC KEY-----
		YOUR PUBLIC KEY GOES HERE
-----END PUBLIC KEY-----";
		$ok = openssl_verify($data, base64_decode($sig), $pubkey, OPENSSL_ALGO_SHA1);
		if($ok) {
			return base64_decode($data);
		} else {
			return false;
		}
	} // end of member function check_sign

	/**
	 * 
	 * Выполнить аутентификацию на паспорте. В случае, если сессия на паспорте не авторизована, то пользователю
	 * будет предложено пройти авторизацию.
	 */
	public function auth_passport()
	{
		$salt = $this->session->newsalt();
		header("Status: 301");
		header("Location: " . $this->passport_url . "?" . http_build_query(array("action" => "authn", "salt" => $salt, "retpath" => $this->service_retpath)));
	}

	/**
	 * 
	 * Выполнить валидацию сессии на паспорте. В случае, если сессия на паспорте не авторизована, то
	 * пользователю не будет выдана никакая информация и броузер пользователя будет направлен обратно
	 * на сервисом с сигнализирующим флагом VALID.
	 */
	public function validate_passport()
	{
		$salt = $this->session->newsalt();
		header("Status: 301");
		header("Location: " . $this->passport_url . "?" . http_build_query(array("action" => "validate", "salt" => $salt, "retpath" => $this->service_retpath)));
	}

	/**
	 * 
	 * Завершить сессию на паспорте и вернуться
	 */
	public function logout_passport()
	{
		$salt = $this->session->newsalt();
		header("Status: 301");
		header("Location: " . $this->passport_url . "?" . http_build_query(array("action" => "logout", "salt" => $salt, "retpath" => $this->service_retpath)));
	}

	/**
	 * 
	 * Обработчик результата авторизации на паспорте. Десериализирует структуру информации о сессии в 
	 * экземпляре класса ILSession атрибута $this->session.
	 */
	public function authenticated()
	{
		$data = $_GET['data'];
		$sig = $_GET['signature'];
		$rv = $this->check_sign($data, $sig);
		if($rv == false)
			return false;
			
		$tmp = new ILSession();
		$tmp->fromJSON($rv);
		if($this->session->checksalt($tmp)) {
			$this->session->fromJSON($rv);
			$this->session->deploy();
			return true;
		} else {
			return false;
		}
	}

	/**
	 * 
	 * Обработчик результата валидации на паспорте. Десериализирует структуру информации о сессии в 
	 * экземпляре класса ILSession атрибута $this->session.
	 */
	public function validated()
	{
		$data = $_GET['data'];
		$sig = $_GET['signature'];
		$rv = $this->check_sign($data, $sig);
		if($rv == false)
			return false;
			
		$tmp = new ILSession();
		$tmp->fromJSON($rv);
		if($this->session->checksalt($tmp)) {
			$this->session->fromJSON($rv);
			$this->session->deploy();
			return true;
		} else {
			return false;
		}

	}

	/**
	 * 
	 * Обработчик результата завершения сеанса авторизации на паспорте.
	 */
	public function loggedout()
	{
		$data = $_GET['data'];
		$sig = $_GET['signature'];
		$rv = $this->check_sign($data, $sig);
		if($rv == false)
			return false;
			
		$tmp = new ILSession();
		$tmp->fromJSON($rv);
		if($this->session->checksalt($tmp)) {
			$this->session->fromJSON($rv);
			$this->session->deploy();
			return true;
		} else {
			return false;
		}

	}

	/**
	 * 
	 * Вспомогательный диспетчер вспомогательный для облегчения разработки сервиса
	 */
	public function dispatch() {
		$retval = false;
		$done = false;
		if( !array_key_exists('action', $_GET) ) return;
			
		if($_GET["action"] == "auth") {
			$retval = $this->authenticated();
			$done = true;
		} else if($_GET["action"] == "validated") {
			$retval = $this->validated();
			$done = true;
		} else if($_GET["action"] == "loggedout") {
			$retval = $this->loggedout();
			$done = true;
		} else if($_GET["action"] == "login") {
			$retval = $this->auth_passport();
		} else if($_GET["action"] == "logout") {
			$retval = $this->logout_passport();
		}

		if($done == true) {
			header("Status: 301");
			header("Location: " . $this->service_retpath);
		}
	}

	/**
	 * 
	 * Сериализатор структуры данных экземпляра класса ILSession в сессионных данных PHP
	 * @param array $data
	 */
	public function store_session($data) {
		$_SESSION['PASS_DATA'] = $data;
	}
	
	/**
	 * 
	 * Десериализатор структуры данных экземпляра класса ILSession в сессионных данных PHP
	 * @param array $data
	 */
	public function load_session($sid) {
		if(array_key_exists("PASS_DATA", $_SESSION)) {
		    return $_SESSION['PASS_DATA'];
		} else {
		    return false;
		}
	}

} // end of ILService
?>
