<?php
/**
 * This class is used for all functionality associated
 * with user authentication and session management.
 */
namespace BookApi;

class Auth
{
	/**
	 * Give session a name and start it.
	 */
	public static function init()
	{
		session_name('book-api');
		session_start();
	}

	/**
	* PHP v5.3.17 is ancient, we need solid password hashing!
	* - Generates [./0-9A-Za-z]{22} random salt
	* - Hashes using Blowfish, the default password hashing algo for PHP 5.5
	* - Fails if hash is less than 13 characters (invalid)
	*
	* @param   string $password      password to hash
	* @param   int $cost             optional algorithmic cost
	* @return  boolean|string $hash  false on failure, hash on success
	*/
	public static function password_hash($password, $cost = 12)
	{
		$salt = substr(strtr(
			base64_encode(mcrypt_create_iv(16, MCRYPT_DEV_RANDOM)),
		'+', '.'), 0, 22);

		$hash = crypt($password, '$2a$'.$cost.'$'.$salt.'$');

		if(strlen($hash) <= 13)
		{
			return false;
		}

		return $hash;
	}

	/**
	* Strong salt used so time safe comparison may bot be necessary
	* but using one just to be safe.
	*
	* @param   string $password  supplied password
	* @param   string $hash      hash of password to check against
	* @return  boolean           whether the passwords match
	*/
	public static function password_verify($password, $hash)
	{
		$password = crypt($password, $hash);

		$res = $password ^ $hash;
		$ret = strlen($password) ^ strlen($hash);

		for($i = strlen($res) - 1; $i >= 0; $i--)
		{
			$ret |= ord($res[$i]);
		}

		return !$ret;
	}

	/**
	 * Signs a user in, but not before regenerating the session id.
	 * 
	 * @param  array $data Any data we want to set in session variables.
	 */
	public static function sign_in($data)
	{
		// avoid session fixation after user level changes + delete old session
		$_SESSION['signed_in'] = session_regenerate_id(true);

		foreach($data as $key => $value)
		{
			$_SESSION[$key] = $value;
		}
	}

	/**
	 * Sign a user out of their current session.
	 * 
	 * @return boolean Success or failure if no user is signed in.
	 */
	public static function sign_out()
	{
		if(self::is_signed_in())
		{
			session_destroy();
			return true;
		}
		return false;
	}

	/**
	 * Elevates a user, assigning them admin privileges.
	 */
	public static function elevate()
	{
		$_SESSION['is_admin'] = true;
	}

	/**
	 * Checks if a user is signed in.
	 * 
	 * @return boolean Whether or not the signed_in session variable exists and is
	 * set to true.
	 */
	public static function is_signed_in()
	{
		return isset($_SESSION['signed_in']) && $_SESSION['signed_in'];
	}

		/**
	 * Checks if a user is signed in.
	 * 
	 * @return boolean Whether or not the signed_in session variable exists and is
	 * set to true.
	 */
	public static function get_user()
	{
		return self::is_signed_in() ? $_SESSION['username'] : null;
	}

	/**
	 * Check if a user is an admin.
	 * 
	 * @return boolean Whether or not the is_admin session variable exists and is
	 * set to true.
	 */
	public static function is_admin()
	{
		return isset($_SESSION['is_admin']) && $_SESSION['is_admin'];
	}

	/**
	 * Makes sure the user is the same as the supplied name.
	 * 
	 * @param  string $user A username
	 */
	public static function check_user($user)
	{
		return $user === $_SESSION['username'];
	}
}
?>