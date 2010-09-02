<?php

class alreadyConnectedException extends Exception {};

class ISGCache
{
	public $socketPath;
	private $socket = null;
	private $parser = null;
	private $rv = FALSE;
	private $tmp = 0;
	private $tmp_stack = array();
	private $level = 0;
	private $current = null;
	private $codepage;

	function __construct($codepage = "utf8", $socketPath = null)
	{
		$this->codepage = $codepage;
		if($socketPath == null) {
			$conf = parse_ini_file('/etc/cached.ini', true);
			$this->socketPath = $conf["MAIN"]["socket"];
		}
	}
	
	function putlog($text)
	{
		$fd = fopen("/var/log/isg/cachelib_test.log", "a");
		fwrite($fd, $text . "\n");
		fclose($fd);
	}

	function connect($cp = "koi8-r")
	{
		if($this->socket != false) {
			throw alreadyConnectedException("Already connected. Disconnect first");
		}
		
		$this->codepage = $cp;
		$this->rv = FALSE;
		$this->tmp = 0;
		$this->tmp_stack = array();
		$this->level = 0;
		$this->current = null;
		
		if(isset($this) && get_class($this) == 'ISGCache') {
			$this->socket = fsockopen("unix://" . $this->socketPath, -1, $errno, $errstr);
			if($this->socket === FALSE) {
				fwrite(2, "Error connecting to cache socket\n");
				return false;
			}
			return true;
		} else {
			fwrite(2, "Connect should be called from instance\n");
			return false;
		}
	}

	function disconnect()
	{
		if(isset($this) && get_class($this) == 'ISGCache') {
			fclose($this->socket);
			$this->socket = false;
			return true;
		} else {
			fwrite(2, "Disconnect should be called from instance\n");
			return false;
		}
	}

	function tag_open($parser, $tag, $attributes)
	{
		$this->level++;
		if($this->level == 2) {
			if($this->rv === FALSE) {
				$this->rv = array();
			}

			if(strtoupper($tag) == "BIND") {
				array_push($this->tmp_stack, $this->tmp);
				$this->tmp = 'bind';
			}

			$this->rv[$this->tmp] = array();
		}
		if($this->level == 3) {
			$this->rv[$this->tmp][$tag][] = "";
			$this->current = $tag;
		}
	}

	function tag_close($parser, $tag)
	{
		$this->level--;
		if($this->level == 1) {
			if($this->tmp == 'bind') {
				$this->tmp = array_shift($this->tmp_stack);
			} else {
				$this->tmp++;
			}
			$this->current = null;
		} else if($this->level == 2) {
			$i = count($this->rv[$this->tmp][$this->current]) - 1;
			$this->rv[$this->tmp][$this->current][$i] = trim($this->rv[$this->tmp][$this->current][$i]);
		}
	}

	function cdata($parser, $cdata)
	{
		if($this->current != null && $this->level == 3) {
			$i = count($this->rv[$this->tmp][$this->current]) - 1;
			if(strtolower($this->codepage) != "utf8" && strtolower($this->codepage) != "utf-8") {
				$this->rv[$this->tmp][$this->current][$i] .= iconv('utf8', $this->codepage, $cdata);
			} else {
				$this->rv[$this->tmp][$this->current][$i] .= $cdata;
			}
		}
	}

	function query($xml)
	{
		$this->connect($this->codepage);
		
		fwrite($this->socket, $xml);
		fflush($this->socket);

		$this->parser = xml_parser_create("UTF-8");
		xml_parser_set_option($this->parser, XML_OPTION_SKIP_WHITE, 1);
		xml_set_object($this->parser, $this);
		xml_set_element_handler($this->parser, "tag_open", "tag_close");
		xml_set_character_data_handler($this->parser, "cdata");

		while(1) {
			$data = fgets($this->socket);
			if(!$data) {
				break;
			}
			if(xml_parse($this->parser, $data, FALSE) == 0) {
				fwrite(STDERR, "Error parsing result while parsing stream at line ".
				xml_get_current_line_number($this->parser).
				" column ".
				xml_get_current_column_number($this->parser).
				": " . xml_error_string(xml_get_error_code($this->parser)) . "\n");
				fwrite(STDERR, $data);
				$this->rv = FALSE;
				break;
			}
		}
		xml_parse($this->parser, "", TRUE);
		xml_parser_free($this->parser);
		
		$this->disconnect();
	}

	function getOnlineStatus($pbhk, $force = 0)
	{
		$dom = new DOMDocument("1.0", "UTF-8");
		$dom->formatOutput = true;

		$root = $dom->createElement("query");
		$dom->appendChild($root);

		$status = $dom->createElement("status");
		$root->appendChild($status);

		$attrib = $dom->createAttribute("pbhk");
		$status->appendChild($attrib);

		if($force) {
			$attrib_f = $dom->createAttribute("force");
			$status->appendChild($attrib_f);
			$attrib_f->appendChild($dom->createTextNode($force));
		}

		$attrib->appendChild($dom->createTextNode($pbhk));

		$this->query($dom->saveXML());

		return $this->rv;
	}

	function getIsgServices($pbhk)
	{
		$dom = new DOMDocument("1.0", "UTF-8");
		$dom->formatOutput = true;

		$root = $dom->createElement("query");
		$dom->appendChild($root);

		$status = $dom->createElement("get_isg_services");
		$root->appendChild($status);

		$attrib = $dom->createAttribute("pbhk");
		$status->appendChild($attrib);

		$attrib->appendChild($dom->createTextNode($pbhk));

		$this->query($dom->saveXML());

		return $this->rv;
	}

	function isgPing($pbhk)
	{
		$dom = new DOMDocument("1.0", "UTF-8");
		$dom->formatOutput = true;

		$root = $dom->createElement("query");
		$dom->appendChild($root);

		$status = $dom->createElement("isg_ping");
		$root->appendChild($status);

		$attrib = $dom->createAttribute("pbhk");
		$status->appendChild($attrib);

		$attrib->appendChild($dom->createTextNode($pbhk));

		$this->query($dom->saveXML());

		return $this->rv;
	}

	function isgLoggon($pbhk, $login, $password)
	{
		$dom = new DOMDocument("1.0", "UTF-8");
		$dom->formatOutput = true;

		$root = $dom->createElement("query");
		$dom->appendChild($root);

		$status = $dom->createElement("isg_logon");
		$root->appendChild($status);

		$attrib = $dom->createAttribute("pbhk");
		$status->appendChild($attrib);
		$attrib->appendChild($dom->createTextNode($pbhk));

		$attrib = $dom->createAttribute("login");
		$status->appendChild($attrib);
		$attrib->appendChild($dom->createTextNode($login));

		$attrib = $dom->createAttribute("password");
		$status->appendChild($attrib);
		$attrib->appendChild($dom->createTextNode($password));

		$this->query($dom->saveXML());

		return $this->rv;
	}

	function isgLogout($pbhk)
	{
		$dom = new DOMDocument("1.0", "UTF-8");
		$dom->formatOutput = true;

		$root = $dom->createElement("query");
		$dom->appendChild($root);

		$status = $dom->createElement("isg_logout");
		$root->appendChild($status);

		$attrib = $dom->createAttribute("pbhk");
		$status->appendChild($attrib);
		$attrib->appendChild($dom->createTextNode($pbhk));

		$this->query($dom->saveXML());

		return $this->rv;
	}

	function getStaticIP($user_ip, $server_ip)
	{

		$dom = new DOMDocument("1.0", "UTF-8");
		$dom->formatOutput = true;

		$root = $dom->createElement("query");
		$dom->appendChild($root);

		$status = $dom->createElement("get_static_ip");
		$root->appendChild($status);

		$attrib = $dom->createAttribute("ip");
		$status->appendChild($attrib);
		$attrib->appendChild($dom->createTextNode($user_ip));

		$attrib = $dom->createAttribute("server");
		$status->appendChild($attrib);
		$attrib->appendChild($dom->createTextNode($server_ip));

		$this->query($dom->saveXML());

		return $this->rv;
	}

	function sql($query, $args = array())
	{

		$dom = new DOMDocument("1.0", "UTF-8");
		$dom->formatOutput = true;

		$root = $dom->createElement("query");
		$dom->appendChild($root);

		$sql = $dom->createElement("raw_sql");
		$root->appendChild($sql);

		$attrib = $dom->createAttribute("sql");
		$sql->appendChild($attrib);
		$attrib->appendChild($dom->createTextNode($query));

		foreach ($args as $n => $v) {
			$ch = $dom->createElement("param");
			$sql->appendChild($ch);

			$name = $dom->createAttribute("name");
			$ch->appendChild($name);
			$name->appendChild($dom->createTextNode($n));

			$value = $dom->createAttribute("value");
			$ch->appendChild($value);
			$value->appendChild($dom->createTextNode($v));
		}

		$this->query($dom->saveXML());

		return $this->rv;
	}
}

?>