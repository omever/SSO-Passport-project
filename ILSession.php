<?php

require_once 'ILPassport.php';
/**
 * class ILSession
 *
 */
class ILSession
{
	/**
	 * 
	 * идентификатор текущей сессии
	 * @var string
	 */
	public $sid;

	/**
	 * 
	 * Идентификатор удалённой сессии. Пока не используется
	 * @var string
	 */
	public $remote_sid;
	
	/**
	 * 
	 * Идентификатор пользователя content в биллинге
	 * @var integer
	 */
	public $user_id;
	
	/**
	 * 
	 * Логин пользователя
	 * @var string
	 */
	public $login;
	
	/**
	 * 
	 * Имя пользователя
	 * @var string
	 */
	public $name;
	
	/**
	 * 
	 * "Соль" запроса - гарантирует однократность обработки ответа на запрос
	 * @var string
	 */
	public $salt;
	
	/**
	 * 
	 * Флаг отвечающий за валидность сессии. Даже если сессия существует, она может быть
	 * невалидная (например, ошибка авторизации на паспорте).
	 * @var bool
	 */
	public $valid;

	/**
	 * 
	 * EMail пользователя
	 * @var string
	 */
	public $mail;
	
	/**
	 * 
	 * тип внешнего авторизатора
	 * @var string
	 */
	public $extauth;
	
	/**
	 * 
	 * Путь возврата с паспорта на сервис
	 * @var string
	 */
	public $retpath;

	/**
	 * 
	 * Время "актуальности" сессии, устанавливается исходя из текущего времени и 
	 * атрибута session_timeout при каждом обновлении данных или запросе на валидность сессии
	 * тип - unix timestamp: количество секунд прошедших с 1 января 1970 года.
	 * @var integer
	 */
	public $ts = false;
	
	/**
	 * 
	 * Таймаут сессии в секундах
	 * @var integer
	 */
	public $session_timeout = 3600;
	
	/**
	 * 
	 * Ссылка на экземпляр объекта паспорт
	 * @var ILStorage
	 */
	private $pass;

	/**
	 * 
	 * Конструктор. Передаваемый параметр $pass должен реализовать интерфейс
	 * ILStorage. Например, это экземпляры классов
	 * ILPassporе и ILService, но могут быть и любые другие.
	 * @param $pass
	 */
	function __construct(ILStorage $pass) {
		$this->pass = $pass;
		if(empty($_COOKIE['PAUTHID'])) {
		    $this->sid = $this->genRandomString() . '_' . time();
		    // Десять лет, фигли!
		    setcookie('PAUTHID', $this->sid, time() + 315360000);
		} else {
		    $this->sid = $_COOKIE['PAUTHID'];
		}
		
		$data = $this->pass->load_session($this->sid);
		
		if($data) {
		    $this->login = $data['login'];
		    $this->name = $data['name'];
		    $this->user_id = $data['user_id'];
		    $this->remote_id = $data['remote_id'];
		    $this->ts = $data['ts'];
		    $this->salt = $data['salt'];
		    $this->valid = $data['valid'];
		    $this->retpath = $data['retpath'];
		    $this->mail = $data['mail'];
		    $this->extauth = $data['extauth'];
		}
	}
	
	/**
	 * 
	 * При уничтожении класса мы сохраняем все данные во внешнем хранилище.
	 */
	function __destruct() {
		$this->deploy();
	}

	/**
	 * 
	 * Сериализация данных перед отправкой на сервис
	 */
	public function toJSON() {
		$data = array();
		$data['sid'] = $this->sid;
		$data['user_id'] = $this->user_id;
		$data['login'] = $this->login;
		$data['name'] = $this->name;
		$data['valid'] = $this->validate();
		$data['mail'] = $this->mail;
		$data['extauth'] = $this->extauth;
		
		return json_encode($data);
	}
	
	/**
	 * 
	 * Десериализация данных от паспорта на сервисе.
	 * @param string $json
	 */
	public function fromJSON($json) {
		$mixed = json_decode($json);
		
		if(is_object($mixed)) {
			if(isset($mixed->sid))
				$this->sid = $mixed->sid;
			if(isset($mixed->user_id))
				$this->user_id = $mixed->user_id;
			if(isset($mixed->login))
				$this->login = $mixed->login;
			if(isset($mixed->name))
				$this->name = $mixed->name;
			if(isset($mixed->valid))
				$this->valid = $mixed->valid;
			if(isset($mixed->mail))
				$this->mail = $mixed->mail;
			if(isset($mixed->salt))
				$this->salt = $mixed->salt;
			if(isset($mixed->extauth))
				$this->extauth = $mixed->extauth;
		} else if(is_array($mixed)) {
			if(isset($mixed['sid']))
				$this->sid = $mixed['sid'];
			if(isset($mixed['user_id']))
				$this->user_id = $mixed['user_id'];
			if(isset($mixed['login']))
				$this->login = $mixed['login'];
			if(isset($mixed['name']))
				$this->name = $mixed['name'];
			if(isset($mixed['valid']))
				$this->valid = $mixed['valid'];
			if(isset($mixed['mail']))
				$this->mail = $mixed['mail'];
			if(isset($mixed['salt']))
				$this->salt = $mixed['salt'];
			if(isset($mixed['extauth']))
				$this->extauth = $mixed['extauth'];
		} else {
			return false;
		}
		$this->ts = time() + $this->session_timeout;
		return true;
	}
	
	/**
	 * 
	 * Уничтожение сессионных данных
	 */
	public function destroy() {
		$this->login = '';
		$this->name = '';
		$this->user_id = '';
		$this->remote_id = '';
		$this->ts = '';
		$this->valid = 0;
		$this->mail = '';
		$this->deploy();
	}

	/**
	 * 
	 * Обновление сессионной информации
	 * @param StdObject $response
	 */
	public function update( $response ) {
		$this->login = $response->LOGIN;
		$this->name = $response->NAME;
		$this->user_id = $response->UID;
		//$this->remote_sid = $response->SID;
		$this->ts = time() + $this->session_timeout;
		$this->valid = $response->VALID == 'FALSE' ? 0 : 1;
		$this->email = $response->MAIL;
		$this->deploy();
	}

	/**
	 * 
	 * Проверка на существование сессии. Сессия признаётся существующей,
	 * если установлен для неё таймаут в атрибуте ts и он ещё не истёк.
	 * 
	 * При успешном результате таймаут сдвигается от текущего времени на
	 * величину session_timeout
	 */
	public function exist() {
		if($this->ts) {
			if($this->ts > time()) {
				$this->ts = time() + $this->session_timeout;
				$this->deploy();

				return true;
			} else {
				return false;
			}
		} else {
			return false;
		}
	}

	/**
	 * 
	 * Проверка валидности сессии. Сессия валидна, если она существует
	 * и атрибут valid имеет значение истина.
	 */
	public function validate() {
		return ($this->exist() && $this->valid);
	}
    
	/**
	 * 
	 * Генерация "соли" для новго запроса к паспорту
	 */
	public function newsalt() {
		$this->salt = $this->genRandomString();
		$this->deploy();
		return $this->salt;
	}

	/**
	 * 
	 * Проверка соли в запросе паспорта. В любом случае текущее значение соли
	 * будет обнулено.
	 * @param StdObject $response
	 */
	public function checksalt($response) {
		$retval = true;

		if($this->salt == '' || $this->salt != $response->salt) {
			$retval = false;
		}

		$this->salt = '';
		$this->deploy();
		return $retval;
	}

	/**
	 * 
	 * Сохранение параметров сессии во внешней базе используя интерфейс ILStorage
	 * объекта хранящегося в атрибуте pass.
	 */
	public function deploy() {
		$data->login = $this->login;
		$data->name = $this->name;
		$data->user_id = $this->user_id;
		$data->remote_id = $this->remote_sid;
		$data->salt = $this->salt;
		$data->ts = $this->ts;
		$data->valid = $this->valid;
		$data->mail = $this->mail;
		$data->extauth = $this->extauth;
		$data->sid = $this->sid;
		$data->retpath = $this->retpath;
		$data->timeout = $this->session_timeout;
		$this->pass->store_session($data);
	}

	/**
	 * 
	 * Генерация случайной строки из 10 символов.
	 */
	public static function genRandomString() {
		$length = 10;
		$characters = '0123456789abcdefghijklmnopqrstuvwxyz';
		$string = '';

		for ($p = 0; $p < $length; $p++) {
			$string .= $characters[mt_rand(0, strlen($characters) - 1)];
		}

		return $string;
	}
} // end of ILSession
?>
