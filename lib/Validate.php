<?php
/**
 * This class is used as a central place for validation.
 */
namespace BookApi;

class Validate
{
	private static $_errors = array();

	public static function parameters($params, $check)
	{
		foreach($check as $param => $rule)
		{
			if(is_array($rule))
			{
				foreach($params[$param] as $arrayparam)
				{
					self::parameters($arrayparam, $rule);
				}
			}
			else
			{
				if(strpos(strtolower($rule), 'optional') !== false &&
					Util::is_empty(@$params[$param]))
				{
					continue;
				}
				elseif(Util::is_empty(@$params[$param]))
				{
					self::$_errors[] = $param.' is required';
					continue;
				}

				$rules = explode(',', $rule);

				foreach($rules as $rule)
				{
					if(isset($params[$param]))
					{
						self::_filter($rule, $param, $params[$param]);
					}
				}
			}
		}

		return count(self::$_errors) === 0 ? true : false;
	}

	private static function _filter($rule, $name, $value)
	{
		if(strstr($rule, ':'))
		{
			$words = explode(':', $rule);
			$rule = $words[0];
			$rule_value = $words[1];
		}

		switch(strtolower(trim($rule)))
		{
			case 'numeric' :
				if(!is_numeric($value))
				{
					self::$_errors[] = $name.' must be a number';
				}
			break;
			case 'minimum' :
				if(!is_numeric($value) || $value < $rule_value)
				{
					self::$_errors[] = $name.' must be more than '.$rule_value;
				}
			break;
			case 'maximum' :
				if(!is_numeric($value) || $value > $rule_value)
				{
					self::$_errors[] = $name.' must be less than '.$rule_value;
				}
			break;
			case 'between' :
				$rule_value = explode('-', $rule_value);
				if(!is_numeric($value) || $value < $rule_value[0] ||
					$value > $rule_value[1])
				{
					self::$_errors[] = $name.' must be between '.$rule_value[0].' and '.$rule_value[1];
				}
			break;
			case 'min_length' :
				if(strlen($value) < $rule_value)
				{
					self::$_errors[] = $name.' must be at least '.$rule_value.' characters';
				}
			break;
			case 'max_length' :
				if(strlen($value) > $rule_value)
				{
					self::$_errors[] = $name.' must not exceed '.$rule_value.' characters';
				}
			break;
			case 'email' :
				if(!filter_var($value, FILTER_VALIDATE_EMAIL))
				{
					self::$_errors[] = 'invalid email address supplied';
				}
			break;
			case 'username' :
				if(!filter_var(
						$value, FILTER_VALIDATE_REGEXP,
						array('options' => array('regexp'=>'/^[A-Za-z0-9\-]+$/'))
					)
				)
				{
					self::$_errors[] = $name.' may only contain alpha-numeric characters and dashes';
				}
			break;
		}
	}

	public static function uploads($files)
	{
		$uploads = array();

		foreach($files as $file => $rules)
		{
			try
			{
				if(!isset($_FILES[$file]['error']) ||
					is_array($_FILES[$file]['error']))
				{
					throw new \RuntimeException('Invalid files given.');
				}

				switch($_FILES[$file]['error'])
				{
					case UPLOAD_ERR_OK:
						break;
					case UPLOAD_ERR_NO_FILE:
						throw new \RuntimeException($file.' input empty.');
					case UPLOAD_ERR_INI_SIZE:
					case UPLOAD_ERR_FORM_SIZE:
						throw new \RuntimeException(
							$file.' exceeded filesize limit.'
						);
					default:
						throw new \RuntimeException(
							'An unknown error occured while uploading.'
						);
				}

				if($_FILES[$file]['size'] > $rules['maxsize'] * 1000)
				{
					throw new \RuntimeException(
						'Exceeded filesize limit of '.$rules['maxsize'].'KB.'
					);
				}

				$finfo = new \finfo(FILEINFO_MIME_TYPE);

				if(($ext = array_search(
						$finfo->file($_FILES[$file]['tmp_name']),
						$rules['mime'],
						true
					)) === false)
				{
					throw new \RuntimeException(
						'File type for '.$file.' must be: '.implode(
							array_keys($rules['mime']), ', '
						)
					);
				}

				if(isset($rules['check']))
				{
					$rules['check']($_FILES[$file]['tmp_name']);
				}

				$uploads[$file] = array(
					'file' => $file,
					'name' => sha1_file($_FILES[$file]['tmp_name']).'.'.$ext
				);
			}
			catch(\RuntimeException $e)
			{
				Request::response(array(
					'success' => false,
					'message' => $e->getMessage()
				));
			}
		}

		return $uploads;
	}

	public static function get_errors()
	{
		return self::$_errors;
	}
}
?>