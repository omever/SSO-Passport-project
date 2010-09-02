<?
ini_set("display_errors", 1);
require_once 'ILPassport.php';

$message = '';
$logged_in = 0;
$login_notice = '';
$password_notice = '';
$error = '';
$action = '';
$pass;
$twitter;
$facebook;
$pass = new ILPassport();

$workers = array();

try {
	require_once 'ILTwitter.php';
	require_once 'ILFacebook.php';
	require_once 'ILVK.php';
	require_once 'ILOpenID.php';

	$twitter = new ILTwitter($pass);
	if(!empty($twitter)) {
		array_push($workers, $twitter);
	}
	
	$facebook = new ILFacebook($pass);
	if(!empty($facebook)) {
		array_push($workers, $facebook);
	}
	
	$vk = new ILVK($pass);
	if(!empty($vk)) {
		array_push($workers, $vk);
	}
	
	$openid = new ILOpenID($pass);
	if(!empty($openid)) {
		array_push($workers, $openid);
	}
}
catch(Exception $e) {
	$error = $e->getMessage();
}

function current_value($arg)
{
	$current_value = '';
	if(array_key_exists($arg, $_POST))
		$current_value = "value='" . htmlspecialchars($_POST[$arg]) . "'";
	else if(array_key_exists($arg, $_GET))
		$current_value = "value='" . htmlspecialchars($_GET[$arg]) . "'";
		
	return $current_value;
}

if(array_key_exists("action", $_GET)) {
	$action = $_GET['action'];
	if($_GET["action"] == "authn") {
		try {
			$rv = $pass->authenticate();
			if($rv >= 0) {
				$message = 'Добро пожаловать, ' . $pass->session->name;
				$logged_in = 1;
			}
		}
		catch(Exception $e) {
			$message = $e->getMessage();
			$logged_in = 0;
		}
	} else if($_GET["action"] == "login_check") {
		try {
			$rv = $pass->authorize();
			if($rv >= 0) {
				$message = 'Добро пожаловать, ' . $pass->session->name;
				$logged_in = 1;
			} else {
				$error = 'Ошибка авторизации';
			}
		}
		catch(Exception $e) {
			$error = $e->getMessage();
			$message = $e->getMessage();
		}
	} else if($_GET["action"] == "registration_check") {
		try {
			$rv = $pass->register($_POST['login'], $_POST['password'], $_POST['email'], $_POST['fname'], $_POST['lname']);
			$message = 'Регистрация успешно завершена.';
		}
		catch (LoginFieldException $e) {
			$login_notice = $e->getMessage();
			$action = 'registration';
		}
		catch (PasswordFieldException $e) {
			$password_notice = $e->getMessage();
			$action = 'registration';
		}
		catch (Exception $e) {
			$error = $e->getMessage();
			$action = 'registration';
		}
	} else if($_GET["action"] == "chpass_check") {
		if($_POST['password'] != $_POST['password2']) {
			$error = 'Пароли не совпадают';
			$action = 'chpass';
		} else {
			try {
				$rv = $pass->chpass($_POST['current_password'], $_POST['password']);
				if($rv == false) {
					$error = 'Ошибка обработки запроса.';
				} else {
					switch ($rv['bind']['RC'][0]) {
						case 0:
							$message = 'Пароль успешно изменён';
							$action = '';
							break;
						case 1:
							$error = 'Текущий пароль указан неверно';
							$action = 'chpass';
							$_POST['current_password'] = '';
							break;
						default:
							$error = 'Неизвестная ошибка (' . $rv['bind']['RC'][0] . ')';
							$action = 'chpass';
							break;
					}
				}
			}
			catch (PasswordFieldException $e) {
				$password_notice = $e->getMessage();
				$action = 'chpass';
			}
			catch(Exception $e) {
				$message = $e->getMessage();
				$action = 'chpass';
			}
		}
	} else if($_GET["action"] == "logout") {
		$rv = $pass->logout();
		if(! $pass->validate()) {
			$message = 'Сессия завершена';
			$logged_in = 0;
		}
	} else if($_GET["action"] == "validate") {
		$pass->srv_validate();
	}		
}

try {
	foreach ($workers as $worker) {
		$worker->hook_header();
	}
}
catch (Exception $e) {
	$error = $e->getMessage();
}

if($pass->validate()) {
	$logged_in = 1;
} else {
	$logged_in = 0;
}
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>

	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <meta content="Паспорт TLT.ru - единая учетная запись для всех наших сайтов" http-equiv="description" name="description" />
    <meta content="Паспорт авторизация сайты тлт.ру" http-equiv="keywords" name="keywords" >
     
	<title>Паспорт TLT.ru - единая учетная запись для всех наших сайтов</title>
    
	<link href="/css/style.css" rel="stylesheet" type="text/css" />
    
    <? /*         
    <!--[if IE 6]>
        <link href="/css/style.ie6.css" rel="stylesheet" type="text/css" />
    <![endif]-->
    */ ?> 
	 
</head><body>
<div id="wrapper">

	<div id="logo"><a href="/"><img src="/images/logo-tlt.gif" /></a></div>

	<div id="column-left">
<?
	try {
		foreach ($workers as $worker) {
			$worker->hook_body_header();
		}
	}
	catch(Exception $e) {
		$error = $e->getMessage();
	}
?>    
    	<h1>Впервые здесь?</h1>
        <p>Паспорт дает возможность создать единую учетную запись для всех сервисов портала TLT.ru, в том числе личного кабинета <a href="http://infolada.ru/">ИнфоЛады</a>. Вам больше не нужно запоминать 10 логинов и паролей от всех наших сервисов.</p>
        <p>Сервис находится в разработке, поэтому временно единая учетная запись действует не везде. В дальней все наши сервисы и сайты будут авторизироваться через паспорт. </p>     
    </div>
    
	<div id="column-right">

        <div class="passport-box">
            
            <div class="bg-top"></div>
            <div class="bg-center container">

<? 
if($logged_in == 0) {
	if ($action == 'registration') { 
	?>            
	            
	                <h1>Регистрация</h1>
	                <form action="?action=registration_check" method="post">
	                	<table>
	                    	<tr>
	                        	<td class="name"><label for="input-fname">Имя:</label></td> 
	                            <td><input id="input-fname" type="text" name="fname" <?= current_value('fname') ?>/></td>
	                        </tr>
	                    	<tr>
	                        	<td class="name"><label for="input-lname">Фамилия:</label></td> 
	                            <td><input id="input-lname" type="text" name="lname" <?= current_value('lname') ?>/></td>
	                        </tr>
	                        <tr>
	                        	<td class="name"><label for="input-login">Логин:</label></td>
	                            <td><input id="input-login" type="text" name="login" <?= current_value('login') ?>/></td>
	                        </tr>
<?php
		if(!empty($login_notice)) {
?>
	                        <tr>
	                        	<td colspan=2><?= $login_notice?></td>
	                        </tr>
<?php 
		}
?>
	                    	<tr>
	                        	<td class="name"><label for="input-password">Пароль:</label></td> 
	                            <td><input id="input-password" type="password" name="password" /></td>
	                        </tr>
<?php
		if(!empty($password_notice)) {
?>
	                        <tr>
	                        	<td colspan=2><?= $password_notice?></td>
	                        </tr>
<?php 
		}
?>
	                    	<tr>
	                        	<td class="name"><label for="input-password-check">Еще раз:</label></td> 
	                            <td><input id="input-password-check" type="password" name="password-check" /></td>
	                        </tr>
	                    	<tr>
	                        	<td class="name"><label for="input-email">E-mail:</label></td> 
	                            <td><input id="input-email" type="text" name="email" <?= current_value('email') ?>/></td>
	                        </tr>
	                    </table>
	                    <input type="submit" value="Зарегистрироваться" class="button" />
	                    <a href="?action=login" class="registration-or-login-link">Вход</a>
	                </form>

			        <? /* социалки */ include 'includes/social-login.php'; ?>
	                
	<? 
	} else if($action == 'openid') {
	?>
	                <h1>Вход по OpenID</h1>
	                <h2><?=$error?></h2>
	                <form action="?action=openid_check" method="post">
	                	<table>
	                    	<tr>
	                        	<td class="name"><label for="input-openid">OpenID:</label></td> 
	                            <td><input id="input-openid" type="text" name="openid" <?= current_value('openid') ?>/></td>
	                        </tr>
	                    </table>
	                    <input type="submit" value="Войти" class="button" />
	                    <a href="?" class="registration-or-login-link">Вход по паролю</a>
	                    <a href="?action=registration" class="registration-or-login-link">Зарегистрироваться</a>
	                </form>
	<?php 
	} else {
	?>
	                <h1>Вход</h1>
	                <h2><?=$error?></h2>
	                <form action="?action=login_check" method="post">
	                	<table>
	                    	<tr>
	                        	<td class="name"><label for="input-login">Логин:</label></td> 
	                            <td><input id="input-login" type="text" name="login" <?= current_value('login') ?>/></td>
	                        </tr>
	                        <tr>
	                        	<td class="name"><label for="input-password">Пароль:</label></td>
	                            <td><input id="input-password" type="password" name="password" /></td>
	                        </tr>
	                    </table>
	                    <input type="submit" value="Войти" class="button" />
	                    <a href="?action=registration" class="registration-or-login-link">Зарегистрироваться</a>
	                </form>
                    
					<? /* социалки */ include 'includes/social-login.php'; ?>
                                        
	<?php
	}
} else if($action == "chpass") {
	?>            
	                <h1>Смена пароля</h1>
	                <h2><?=$error?></h2>
	                <form action="?action=chpass_check" method="post">
	                	<table>
	                    	<tr>
	                        	<td class="name"><label for="input-current">Текущий пароль:</label></td>
	                            <td><input id="input-current" type="password" name="current_password" <?= current_value('current_password') ?>/></td>
	                        </tr>
	                        <tr>
	                        	<td class="name"><label for="input-password">Новый пароль:</label></td>
	                            <td><input id="input-password" type="password" name="password"  <?= current_value('password') ?>/></td>
	                        </tr>
<?php
if(!empty($password_notice)) {
?>
	                        <tr>
	                        	<td colspan=2><?= $password_notice?></td>
	                        </tr>
<?php 
}
?>
	                        <tr>
	                        	<td class="name"><label for="input-password2">Ещё раз:</label></td>
	                            <td><input id="input-password2" type="password" name="password2"  <?= current_value('password2') ?>/></td>
	                        </tr>
	                    </table>
	                    <input type="submit" value="Сменить" class="button" />
	                    <a href="/" class="registration-or-login-link">Отмена</a>
	                </form>
	<? 
} else {
?>
                <h1>Паспорт</h1>
<?
		if($message) {
		    echo '<h2>' . $message .  '</h2>';
		}
		if($error) {
		    echo '<h2>' . $error .  '</h2>';
		}
?>
		<table>
			<tr>
				<td class="name">Владелец:</td>
				<td><?=$pass->session->name?></td>
			</tr>
			<tr>
				<td class="name">Логин:</td>
				<td><?=$pass->session->login?></td>
			</tr>
		</table>
		<a href="?action=chpass" id="change-password-link">Изменить пароль</a>
		<a href="?action=logout" id="logout-link">Выйти</a>

<?		
}
?>                
				<div class="clear"></div>
            </div>
            <div class="bg-bottom"></div>
            
        </div>
    
    </div>
    
    <div class="clear"></div>
    
    <div id="footer">
    	<div class="contacts"><a href="http://forum.tlt.ru/index.php?action=profile;u=4613">Веб-мастер</a></div>
    	&copy; 2010, ООО "ИнфоЛада"
    </div>

</div>
<pre>
<?php
try {
	foreach ($workers as $worker) {
		$worker->hook_body_footer();
	}
} 
catch (Exception $e) {
	$error = $e->getMessage();
}
?>
</pre>
</body></html>
<?php 
try {
	foreach ($workers as $worker) {
		$worker->hook_footer();
	}
}
catch(Exception $e) {
	$error = $e->getMessage();
}
?>
<!-- Afterword error: <?=$error?> -->