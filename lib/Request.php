<?php
namespace BookApi;

class Request
{
	private static $_method;
	private static $_params = array();

	public static function init()
	{
		$path = self::_parse_url($_SERVER['REQUEST_URI']);
		$endpoint = array_pop($path);

		if(in_array(strtolower($endpoint), get_class_methods('BookApi\API')))
		{
			self::$_method = $_SERVER['REQUEST_METHOD'];
			Api::$endpoint();
		}
		else
		{
			header('HTTP/1.1 404 Not Found');
		}
	}

	private static function _parse_url($path)
	{
		return explode('/', parse_url(trim($path, '/'), PHP_URL_PATH));
	}

	public static function validate($request)
	{
		if(self::$_method !== $request['method'])
		{
			header('HTTP/1.1 405 Method Not Allowed');
			header('Allow: '.$request['method']);

			self::response(array(
				'success' => false,
				'message' => 'Must use '.$request['method'].' method'
			));
		}

		if(isset($request['auth_level']) && !Auth::is_signed_in() && (
			($request['auth_level'] == 'admin' && !Auth::is_admin()) ||
			($request['auth_level'] == 'user' && Auth::is_admin())
		))
		{
			header('HTTP/1.1 403 Forbidden');
			header('Allow: '.$request['method']);

			self::response(array(
				'success' => false,
				'message' => 'Must be logged in (as '.$request['auth_level'].').'
			));
		}

		switch(self::$_method)
		{
			case 'POST':
				$params = $_POST;
			break;
			default:
				$params = $_GET;
		}

		if(isset($request['rules']))
		{
			if(!Validate::parameters($params, $request['rules']))
			{
				self::response(array(
					'success' => false,
					'message' => ucfirst(implode(Validate::get_errors(), ', '))
				));
			}

			// filter out unecessary parameters (for database queries later on)
			self::$_params = array_intersect_key($params, $request['rules']);
		}
	}

	public static function get_params()
	{
		return self::$_params;
	}

	public static function response($data)
	{
		header('Content-type: application/json');
		echo json_encode($data);
		exit;
	}
}
?>