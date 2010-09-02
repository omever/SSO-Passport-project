<?php
/**
 * @package ILPassport
 * @author Grigory Holomiev
 * @version 0.0
 * Этот файл содержит основной класс обработчик паспорта
 */
require_once 'ILSession.php';
require_once '../cachelib.php';
require_once 'ILAuthenticator.php';
require_once 'ILStorage.php';
 
class LoginFieldException extends Exception {};
class PasswordFieldException extends Exception {};
class InvalidSessionException extends Exception {};
class InternalErrorException extends Exception {};

/**
 * class ILPassport
 * @package ILPassport
 * @subpackage ILPassport
 */
class ILPassport implements ILStorage
{
	/**
	 * Атрибут хранящий экземпляр класса ILSession для данной сессии
	 * @access public
	 * @var ILSession
	 */
	public $session;
	/**
	 * Атрибут хранящий экземпляр класса ISGCache, который позволяет подключается к кэш-демону
	 * запущенному на стороне паспорта для взаимодействия с oracle и с кэшем.
	 * @var ISGCache
	 * @access private
	 */
	private $cache;
	
	/**
	 * Конструктор паспорта. Заполняет атрибуты необходимой для дальнейшей работы информацией.
	 * Исключений напрямую не выбрасывает, однако исключения могут генерироваться конструкторами
	 * классов ISGCache и ILSession
	 * 
	 */
	function __construct() {
		if(!isset($_SESSION)) {
		    session_start();
		}
		$this->cache = new ISGCache();		
		$this->session = new ILSession($this);
		if(array_key_exists('retpath', $_GET))
			$this->session->retpath = $_GET['retpath'];
		else if(array_key_exists('retpath', $_POST))
			$this->session->retpath = $_POST['retpath'];

		if(array_key_exists('salt', $_GET))
			$this->session->salt = $_GET['salt'];
		else if(array_key_exists('salt', $_POST))
			$this->session->salt = $_POST['salt'];
	}

	/**
	 * Возвращает текущий экземпляр класса ISGCache
	 */
	public function _connect_cache()
	{
	    return $this->cache;
	}

	/**
	 * 
	 * Процедура проверки авторизации. Проверяет правильность введённой пары логин/пароль.
	 * В случае успеха устанавливает необходимые сессионые параметры и возвращает идентификатор сессии,
	 * иначе возвращает false
	 * @param string $login
	 * @param string $password
	 * @throws LoginFieldException выбрасывается если логин не заполнен
	 * @throws PasswordFieldException выбрасывается если проль не заполнен
	 * @return integer
	 */
	protected function loggon( $login,  $password ) {
		$cache = $this->_connect_cache();
		if($cache == false)
			return $cache;
		$ok = false;
		
		if(empty($login)) {
			throw new LoginFieldException("Логин не может быть пустым");
		}
		
		if(empty($password)) {
			throw new PasswordFieldException("Пароль не может быть пустым");
		}
		
		$rv = $cache->sql("SELECT bu.user_id,
					    bu.login, 
					    COALESCE(o.org_name, ph.first_name || ' ' || ph.last_name, 'Неизвестный персонаж') name,
					    c.contact_mail
				FROM 
				    bill_user bu
				    , contract c
				    , client cl
				    LEFT JOIN organization o
					ON (cl.client_name = o.client_name)
				    LEFT JOIN phisical ph
					ON (cl.client_name = ph.client_name)
				WHERE bu.login=:login 
				  AND bu.passwd=:pass
				  AND bu.user_type = 'content'
				  AND bu.contract_id = c.contract_id
				  AND c.client_name = cl.client_name", array('login'=>$login, 'pass'=>$password));
		if(array_key_exists(0, $rv)) {
			if($rv[0]["USER_ID"][0]) {
				$this->session->user_id = $rv[0]["USER_ID"][0];
				$this->session->ts = time() + 86400;
				$this->session->login = $rv[0]["LOGIN"][0];
				$this->session->name = $rv[0]["NAME"][0];
				$this->session->mail = $rv[0]["CONTACT_MAIL"][0];
				$this->session->valid = 1;
				$ok = true;
				$this->session->deploy();
			}
		}
		
		if($ok) {
			return session_id();
		} else {
			return false;
		}
	} // end of member function loggon

	/**
	 * 
	 * Процедура регистрации пользователя в биллинге. Автоматически заводятся сущности:
	 * клиент, договор, лицевой счёт, пользователь статистики, пользователь content. 
	 * @param string $login
	 * @param string $password
	 * @param string $email
	 * @param string $fname
	 * @param string $lname
	 * @throws LoginFieldException Не заполнено поле логин
	 * @throws PasswordFieldException Не заполнено поле пароль
	 * @throws InternalErrorException Ошибка проведения части регистрационных процедур
	 * @throws Exception Неизвестная ошибка
	 */
	public function register( $login, $password, $email, $fname, $lname ) {
		$cache = $this->_connect_cache();
		if($cache == false) return $cache;

		if(empty($login)) {
			throw new LoginFieldException("Логин не может быть пустым");
		}
		
		if(empty($password)) {
			throw new PasswordFieldException('Пароль не может быть пустым');
		}
		
		$ok = false;
		$rv = $cache->sql("BEGIN
				PKG_BIS.REGISTRATION_PASSPORT(
					:lname,
					:fname,
					:mname,
					:login,
					:passwd,
					:email,
					'PASSPORT',
					:ip,
					:rc,
					:id);
				END;",
		array('lname' => $lname,
					'fname' => $fname,
					'mname' => '',
					'login' => $login,
					'passwd' => $password,
					'email' => $email,
					'ip' => ($_SERVER['REMOTE_ADDR'] ? $_SERVER['REMOTE_ADDR'] : 'console')));

		switch($rv['bind']['RC'][0]) {
				case 3:
					throw new InternalErrorException("Ошибка регистрации. Повторите попытку позже.");
					break;
				case 2:
				case 1:
					throw new LoginFieldException('Указанный логин существует');
					break;
				case 0:
					$this->loggon($login, $password);
					return $rv['bind']['ID'][0];
					break;
				default:
					throw new Exception('Неизвестная ошибка (' . $rv['bind']['RC'][0] . ')');
					break;
			}
		return 0;
	}
		
	/**
	 * 
	 * Процедура смены пароля в интернет-биллинге. Пароль меняется у пользователя content, если
	 * у пользователя статистики на данном договоре пароль совпадает с паролем пользователя content
	 * то и он будет изменён на новый пароль.
	 * @param string $password
	 * @param string $newpassword
	 * @throws PasswordFieldException Некорректно заполнено поле "Новый пароль"
	 */
	public function chpass( $password, $newpassword ) {
		if(!$this->validate()) {
			return false;
		}

		$cache = $this->_connect_cache();
		if($cache == false)
			return $cache;

		if(empty($newpassword)) {
			throw new PasswordFieldException('Пароль не может быть пустым');
		}
			
		$ok = false;
		$rv = $cache->sql("BEGIN
				PKG_BIS.CHANGE_PASSPORT_PASSWORD(
					:uid,
					:pass,
					:newpass,
					:ip,
					:rc);
				END;",
			array('uid' => $this->session->user_id,
					'pass' => $password,
					'newpass' => $newpassword,
					'ip' => ($_SERVER['REMOTE_ADDR'] ? $_SERVER['REMOTE_ADDR'] : 'console')
			)
		);
		return $rv;
	}

	/**
	 * 
	 * Процедура завершения текущего сеанса авторизации
	 */
	public function logout() {
		$this->session->destroy();
		
		$js = base64_encode($this->session->toJSON());
		$sig = $this->sign($js);
			  
		if($this->session->retpath) {
			header("Status: 301");
			header("Location: " . $this->session->retpath . "?" . http_build_query(array("action" => "loggedout", "data" => $js, "signature" => $sig)));
			$this->session->retpath = '';
		} else {
			return 0;
		}
	} // end of member function logout

	/**
	 * 
	 * Процедура возвращающая валидность текущей сессии.
	 */
	public function validate() {
		return $this->session->validate();
	} // end of member function validate

	/**
	 * 
	 * Процедура подписи ответа паспорта сервису с помощью закрытого ключа.
	 * (НЕ ПОКАЗЫВАТЬ ЭТО НИКОМУ ВООБЩЕ БЛИН СОВСЕМ!)
	 * Примечание: лучше всего передаваемые данные предварительно преобразовывать
	 * в base64, так как было обнаружено, что некоторые версии php по-разному представляют
	 * внутренние многобайтовые переменные и, соответственно, результат цифровой подписи
	 * может отличаться. Для однобайтовых последовательностей такого эффекта не обнаружено.
	 * @param string $data
	 */
	protected function sign( $data ) {
		$private_key = "-----BEGIN RSA PRIVATE KEY-----
		HERE COMES YOUR PRIVATE KEY
-----END RSA PRIVATE KEY-----
";

		openssl_sign($data, $binary_signature, $private_key, OPENSSL_ALGO_SHA1);
		return base64_encode($binary_signature);
	} // end of member function sign

	/**
	 * 
	 * Результат авторизации (как положительный, так и отрицательный) получен и может быть
	 * передан сервису.
	 */
	public function service_auth_ok()
	{
		if($this->session->retpath) {
			$js = base64_encode($this->session->toJSON());
			$sig = $this->sign($js);
			 
			header("Status: 301");
			header("Location: " . $this->session->retpath . "?" . http_build_query(array("action" => "auth", "data" => $js, "signature" => $sig)));
			$this->session->retpath = '';
			return 1;
		} else {
			return 0;
		}
	}

	/**
	 * 
	 * Проверить валидность текущей сессии, передать результат запрашивающему сервису
	 */
	public function authenticate()
	{
		$ok = false;
		if($this->validate()) {
			$ok = true;
		}

		if($ok == false) {
			return -1;
		} else {
			return $this->service_auth_ok();
		}
	}

	/**
	 * 
	 * Попытаться авторизовать пару логин/пароль в базе. Успешный результат передаётся сервису,
	 * неуспешный результат заново отображает форму ввода логина/пароля.
	 */
	public function authorize()
	{
		$ok = false;
		if(array_key_exists("login", $_POST) && array_key_exists("password", $_POST)) {
			$ok = $this->loggon($_POST["login"], $_POST["password"]);
		}

		if($ok == false) {
			return -2;
		} else {
			return $this->service_auth_ok();
		}

	}

	/**
	 * 
	 * Отправить сервису текущее состояние сессии
	 */
	public function srv_validate()
	{
		if($this->session->retpath) {
			$data = array();

			$js = base64_encode($this->session->toJSON());
			$sig = $this->sign($js);
						 
			header("Status: 301");
			header("Location: " . $this->session->retpath . "?" . http_build_query(array("action" => "validated", "data" => $js, "signature" => $sig)));
			$this->session->retpath = '';
			return 1;
		} else {
			return 0;
		}
	
	}

	/**
	 * 
	 * Автоматическая регистрация пользователя внешнего сервиса.
	 * Обязательны к заполнению поля service и extid структуры ILExternalIdentity.
	 * значение поля service должно содержаться в таблице EXTAUTH_TYPE.
	 * поле extid должно быть уникально для данного сервиса.
	 * @param ILExternalIdentity $info заполненная структура с информацией от внешнего сервиса
	 * @throws InternalErrorException внутренняя ошибка обработки запроса
	 * @throws Exception Не заполнены необходимые поля для регистрации
	 */
	public function register_external(ILExternalIdentity $info)
	{
		$ok = false;
		$cache = $this->_connect_cache();
			
		if($cache == false) {
			throw new InternalErrorException('Ошибка подключения. Повторите попытку позже');
		}

		if(empty($info->extid) || empty($info->service)) {
			throw new Exception('Ошибка логики обработки внешней авторизации');
		}
			
		$rv = $cache->sql("SELECT be.user_id
				FROM 
					bill_user_extauth be, 
					extauth_type et
				WHERE be.extauth_type_id = et.id
				  AND et.name = :service
				  AND be.external_id = :extid", array('extid' => $info->extid, 'service' => $info->service));
			
		if(! array_key_exists(0, $rv)) {
			$names = explode(' ', $info->fullname);

			if(empty($names[0])) {
				$names[0] = '';
			}
			if(empty($names[1])) {
				$names[1] = '';
			}
			
			$info->intid = $this->register($info->service . ':' . $info->extid,
			$this->session->genRandomString(),
			$info->email,
			$names[0], $names[1]);

			if($info->intid <= 0) {
				throw new InternalErrorException('Ошибка регистрации пользователя '. $info->service);
			}

			$rv = $cache->sql("INSERT INTO bill_user_extauth (user_id, extauth_type_id, external_id)
									SELECT :user_id, id, :extid FROM extauth_type WHERE name = :service",
					array('user_id' => $info->intid, 'extid' => $info->extid, 'service'=>$info->service));
		}

		$rv = $cache->sql("SELECT bu.user_id,
					    bu.login, 
					    COALESCE(o.org_name, ph.first_name || ' ' || ph.last_name, 'Неизвестный персонаж') name,
					    c.contact_mail
				FROM 
				    bill_user bu
				    , bill_user_extauth be
				    , extauth_type et
				    , contract c
				    , client cl
				    LEFT JOIN organization o
						ON (cl.client_name = o.client_name)
				    LEFT JOIN phisical ph
						ON (cl.client_name = ph.client_name)
				WHERE bu.user_id = be.user_id 
				  AND be.extauth_type_id = et.id
				  AND et.name = :service
				  AND be.external_id = :extid
				  AND bu.user_type = 'content'
				  AND bu.contract_id = c.contract_id
				  AND c.client_name = cl.client_name", array('service'=>$info->service, 'extid'=>$info->extid));
		if(array_key_exists(0, $rv)) {
			if($rv[0]["USER_ID"][0]) {
				$this->session->user_id = $rv[0]["USER_ID"][0];
				$this->session->ts = time() + 86400;
				$this->session->login = $rv[0]["LOGIN"][0];
				$this->session->name = $rv[0]["NAME"][0];
				$this->session->mail = $rv[0]["CONTACT_MAIL"][0];
				$this->session->valid = 1;
				$this->session->extauth = $info->service;
				$ok = true;
				$this->session->deploy();
			}
		}
		if($ok) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * 
	 * Внутренний диспетчер паспорта. Несколько упрощают работу интерфейсной части, не более.
	 */
	public function dispatch() {
		if(array_key_exists("action", $_GET)) {
			if($_GET["action"] == "authn") {
				return $this->authenticate();
			}
			if($_GET["action"] == "authz") {
				return $this->authorize();
			}
			if($_GET["action"] == "logout") {
				if($this->validate()) {
					return $this->logout();
				} else {
					return $this->show_logged_in_screen("Срок действия сессии истёк");
				}
			}
		}
		if($this->validate()) {
			$this->show_logged_in_screen();
		} else {
			$this->show_login_screen();
		}
	}

	/**
	 * 
	 * Сохранение данных сессии в памяти кэш-демона
	 * @param array $data
	 */
	public function store_session($data) {
		$cache = $this->_connect_cache();
		$rv = $cache->store('SID:'.$data->sid, json_encode($data), $data->timeout);
		
	}
	
	/**
	 * 
	 * Загрузка данных сессии из памяти кэш-демона
	 * @param array $sid
	 */
	public function load_session($sid) {
		$cache = $this->_connect_cache();
		$rv = $cache->restore('SID:'.$sid);
		if(count($rv) > 0) {
		    $data = json_decode($rv[0]['RESULT'][0], true);
		    return $data;
		}
		return false;
	}
} // end of ILPassport
?>
