<?php
namespace BookApi;

class Util
{
	// PHP considers "0" to empty
	public static function is_empty($str)
	{
		return (!isset($str) || ($str !== '0' && empty($str)));
	}

	public static function cast_guess(&$result)
	{
		foreach($result as &$row)
		{
			if(is_array($row))
			{
				foreach($row as &$value)
				{
					self::_cast($row);
				}
			}
			else
			{
				self::_cast($row);
			}
		}
	}

	private static function _cast(&$var)
	{
		if(is_numeric($var))
		{
			$var = (float) $var;
		}
	}
}
?>