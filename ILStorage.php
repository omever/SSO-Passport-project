<?php
/**
 * @package ILPassport
 * @author Grigory Holomiev
 */

/**
 * @subpackage ILStorage
 * Интерфейс для создания механизмов хранения данных объекта сессии авторизации
 */
 
interface ILStorage
{
	/**
	 * 
	 * Сохранить сессию во внешней памяти 
	 * @param $data
	 */
	function store_session($data);
	
	/**
	 * 
	 * Восстановить сессию из внешней памяти
	 * @param $sid
	 */
	function load_session($sid);
}