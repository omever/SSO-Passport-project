<?php

require_once 'ILAuthenticator.php';

class ILVK extends ILAuthenticator
{
	function hook_body_footer() {
	}
	
	function hook_body_header() {
?>
		<!-- Подключение API ВКонтакте -->
		<div id="vk_api_transport"></div>
		<script
			src="http://vkontakte.ru/js/api/openapi.js" type="text/javascript"
			charset="windows-1251"></script>
		<script type="text/javascript">
		  VK.init({
		    apiId: 1917256,
		    nameTransportPath: "/xd_receiver.html"
		  });
		</script>
<?php 
	}
	 
	function hook_footer() {
		
	}
	
	function hook_header() {
		
	}
	
	function hook_post() {
		
	}
}