<?php
namespace BookApi;

class Registry
{
	protected static $_config;
	protected static $_data = array();

	public static function init(Config $config)
	{
		if(self::$_config === null)
		{
			self::$_config = $config;
		}
	}

	public static function conf()
	{
		return self::$_config;
	}
	
	public static function set($key, $value)
	{
		self::$_data[$key] = $value;
	}
 
	public static function get($key, $alt = null)
	{
		return array_key_exists($key, self::$_data) ? self::$_data[$key] : $alt;
	}

	public static function remove($key)
	{
		if(isset(self::$_data[$key]))
		{
			unset(self::$_data[$key]);
		}
	}
}
?>