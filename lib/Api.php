<?php
/**
 * This holds the entirety of our web service.
 * Each public method simply represents an endpoint.
 * The code itself should be pretty self explanatory since the bulk of the 
 * functionality is abstracted into various classes.
 */
namespace BookApi;

class Api
{
	public static function login()
	{
		if(Auth::is_signed_in())
		{
			self::_response(false, 'Already signed in, please sign out first.');
		}

		Request::validate(
			array(
				'method' => 'POST',
				'rules'  => array(
					'username' => 'min_length:3,max_length:40',
					'password' => 'min_length:8,max_length:60',
				)
			)
		);

		$params = Request::get_params();

		$query = DB::getInstance()->prepare('
			SELECT username, password, is_admin
			FROM users 
			WHERE username = :username
		');
		$query->bindValue(':username', $params['username'], \PDO::PARAM_STR);

		if(!$query->execute())
		{
			self::_response(false, DB::genericError());
		}

		$result = $query->fetch(\PDO::FETCH_ASSOC);

		if(!$result ||
			!Auth::password_verify($params['password'], $result['password']))
		{
			self::_response(false, 'Password or username unknown.');
		}

		Auth::sign_in(
			array_intersect_key($params, array_flip(array('username')))
		);

		if($result['is_admin'])
		{
			Auth::elevate();
		}

		self::_response(true, 'Successfully logged in!');
	}

	public static function logout()
	{
		Request::validate(
			array(
				'method' => 'GET'
			)
		);

		$success = Auth::sign_out();

		self::_response(
			$success,
			$success ? 
				'Successfully logged out' : 
				'No user authenticated to log out'
		);
	}

	public static function user_create()
	{
		Request::validate(
			array(
				'method' => 'POST',
				'rules'  => array(
					'username' => 'username,min_length:3,max_length:40',
					'email'    => 'email,max_length:254',
					'password' => 'min_length:8,max_length:60',
				)
			)
		);

		$params = Request::get_params();

		$query = DB::getInstance()->prepare('
			SELECT username, password
			FROM users
			WHERE username = :username
		');
		$query->bindValue(':username', $params['username'], \PDO::PARAM_STR);

		if(!$query->execute())
		{
			self::_response(false, DB::genericError());
		}
		elseif($query->fetch(\PDO::FETCH_ASSOC))
		{
			self::_response(false, 'Sorry, this username is taken.');
		}

		$query = DB::getInstance()->prepare('
			INSERT INTO users(username, email, password)
			VALUES (:username, :email, :password)
		');
		$query->bindValue(':username', $params['username'], \PDO::PARAM_STR);
		$query->bindValue(':email', $params['email'], \PDO::PARAM_STR);
		$query->bindValue(
			':password',
			Auth::password_hash($params['password']),
			\PDO::PARAM_STR
		);

		$success = $query->execute();

		self::_response(
			$success,
			$success ? 'User created successfully!' : DB::genericError()
		);
	}

	public static function image()
	{
		Request::validate(
			array(
				'method' => 'GET',
				'rules'  => array(
					'book_id' => 'minimum:0'
				)
			)
		);

		$params = Request::get_params();

		$query = DB::getInstance()->prepare('SELECT image FROM books WHERE id = :id');
		$query->bindValue(':id', $params['book_id'], \PDO::PARAM_STR);
		
		if(!$query->execute() || !($result = $query->fetch(\PDO::FETCH_ASSOC)) ||
			!is_file(($file = Registry::conf()->data_folder.$result['image'])))
		{
			header('HTTP/1.1 404 Not Found');
			exit;
		}

		$finfo = new \finfo(FILEINFO_MIME_TYPE);
		header('Content-type: '.$finfo->file($file));
		readfile($file);
	}

	/* broken since the database was normalised */
	public static function books()
	{
		Request::validate(
			array(
				'method' => 'GET',
				'rules'  => array(
					'title'  => 'optional',
					'authors' => 'optional',
					'start'  => 'optional,numeric,minimum:0',
					'length' => 'optional,numeric,minimum:0,maximum:500',
				)
			)
		);

		$params = Request::get_params();

		$query = new QueryBuilder();

		$result = $query
			->start('
				SELECT id as book_id, title, authors, description, price
				FROM books ', false
			)
			->where(array_intersect_key(
				$params,
				array_flip(array('title', 'authors'))
			))
			->limit(
				!Util::is_empty(@$params['start']) ? $params['start'] : 0,
				!Util::is_empty(@$params['length']) ? $params['length'] : 500
			)
			->execute();
		
		if(!$result)
		{
			self::_response(false, DB::genericError());
		}

		$result = $result->fetchAll(\PDO::FETCH_ASSOC);

		if(!$result)
		{
			self::_response(false, 'No results found.');
		}

		// The PHP MySQL driver is dumb and casts everything to a string
		// so we need to covert it back
		Util::cast_guess($result);

		Request::response($result);
	}

	/* also broken since the database was normalised */
	public static function book()
	{
		Request::validate(
			array(
				'method' => 'GET',
				'rules'  => array(
					'book_id' => 'minimum:0'
				)
			)
		);

		$params = Request::get_params();

		$query = DB::getInstance()->prepare('
			SELECT id as book_id, title, authors, description, price
			FROM books
			WHERE id = :id
		');
		$query->bindValue(':id', $params['book_id'], \PDO::PARAM_STR);

		if(!$query->execute())
		{
			self::_response(false, DB::genericError());
		}

		$result = $query->fetch(\PDO::FETCH_ASSOC);

		if(!$result)
		{
			self::_response(false, 'No results found.');
		}

		Util::cast_guess($result);

		Request::response($result);
	}

	public static function review_create()
	{
		Request::validate(
			array(
				'method' => 'POST',
				'auth_level' => 'user',
				'rules'  => array(
					'book_id' => 'numeric,minimum:0',
					'user'    => 'min_length:3,max_length:40',
					'review'  => 'min_length:10,max_length:2000',
					'rating'  => 'numeric,minimum:0,maximum:5'
				)
			)
		);

		$params = Request::get_params();

		self::_restrict_user($params['user']);

		//INSERT … ON DUPLICATE KEY UPDATE

		$query = DB::getInstance()->prepare('
			INSERT INTO reviews(book_id, user_username, rating, review)
			VALUES (:book, :user, :rating, :review)
		');
		$query->bindValue(':book', $params['book_id'], \PDO::PARAM_STR);
		$query->bindValue(':user', $params['user'], \PDO::PARAM_STR);
		$query->bindValue(':rating', $params['rating'], \PDO::PARAM_STR);
		$query->bindValue(':review', $params['review'], \PDO::PARAM_STR);

		if(!$query->execute() &&
			($errorInfo = $query->errorInfo() && $errorInfo[0] == '23000'))
		{
			self::_response(
				false,
				'You have already submitted a review for this book,
				please use review_update.php.'
			);
		}
		else if(!$query->execute())
		{
			self::_response(false, DB::genericError());
		}

		self::_response(
			true,
			'Successfully added review.',
			array('review_id' => (int)DB::getInstance()->lastInsertId())
		);
	}

	public static function review_update()
	{
		Request::validate(
			array(
				'method' => 'POST',
				'auth_level' => 'user',
				'rules'  => array(
					'book_id' => 'numeric,minimum:0',
					'user'    => 'min_length:3,max_length:40',
					'review'  => 'min_length:10,max_length:2000',
					'rating'  => 'numeric,minimum:0,maximum:5'
				)
			)
		);

		$params = Request::get_params();

		self::_restrict_user($params['user']);

		$query = DB::getInstance()->prepare('
			UPDATE reviews
			SET rating = :rating, review = :review
			WHERE book_id = :book
			AND user_username = :user
		');
		$query->bindValue(':book', $params['book_id'], \PDO::PARAM_STR);
		$query->bindValue(':user', $params['user'], \PDO::PARAM_STR);
		$query->bindValue(':rating', $params['rating'], \PDO::PARAM_STR);
		$query->bindValue(':review', $params['review'], \PDO::PARAM_STR);

		if(!$query->execute())
		{
			self::_response(false, DB::genericError(), array($query->errorInfo()));
		}

		self::_response(true, 'Successfully updated review.');
	}

	public static function review()
	{
		Request::validate(
			array(
				'method' => 'GET',
				'rules'  => array(
					'review_id' => 'numeric,minimum:0',
				)
			)
		);

		$params = Request::get_params();

		$query = DB::getInstance()->prepare('
			SELECT book_id, user_username as user, review, rating
			FROM reviews
			WHERE id = :review
		');
		$query->bindValue(':review', $params['review_id'], \PDO::PARAM_STR);

		if(!$query->execute())
		{
			self::_response(false, DB::genericError());
		}

		$result = $query->fetch(\PDO::FETCH_ASSOC);

		if(!$result)
		{
			self::_response(false, 'No results found.');
		}

		Util::cast_guess($result);

		Request::response(array('success' => true) + $result);
	}

	public static function review_delete()
	{
		Request::validate(
			array(
				'method' => 'GET',
				'auth_level' => 'admin or user',
				'rules'  => array(
					'review_id' => 'numeric,minimum:0',
				)
			)
		);

		$params = Request::get_params();

		if(Auth::is_admin())
		{
			$query = DB::getInstance()->prepare(
				'DELETE FROM reviews WHERE id = :review'
			);
		}
		else
		{
			//get author id from DB
			self::_restrict_user($params['user']);
			$query = DB::getInstance()->prepare('
				DELETE FROM reviews
				WHERE id = :review
				AND user_username = :user
			');
			$query->bindValue(':user', Auth::get_user(), \PDO::PARAM_STR);
		}

		// can't deletes non-existing review

		$query->bindValue(':review', $params['review_id'], \PDO::PARAM_STR);

		if(!$query->execute())
		{
			self::_response(false, DB::genericError());
		}

		self::_response(true, 'Successfully deleted review.');
	}

	// TODO:
	// purchase_create()
	// purchase_activate()
	// purchase_cancel()

	public static function purchases()
	{
		Request::validate(
			array(
				'method' => 'GET',
				'auth_level' => 'user',
				'rules'  => array(
					'user'  => 'optional',
					'book_id' => 'optional,minimum:0',
					'start'  => 'optional,numeric,minimum:0',
					'length' => 'optional,numeric,minimum:0,maximum:500',
				)
			)
		);

		$params = Request::get_params();

		$query = new QueryBuilder();

		$result = $query
			->start('SELECT book_id, user_username as user FROM purchases ')
		    ->where(array_intersect_key($params, array_flip(array('book_id'))))
		    ->limit(
		    	!Util::is_empty(@$params['start']) ? $params['start'] : 0,
		    	!Util::is_empty(@$params['length']) ? $params['length'] : 500
		    )
		    ->execute();
		
		if(!$result)
		{
			self::_response(false, DB::genericError());
		}

		$result = $result->fetchAll(\PDO::FETCH_ASSOC);

		if(!$result)
		{
			self::_response(false, 'No results found.');
		}

		// The PHP MySQL driver is dumb and casts everything to a string
		// so we need to covert it back
		Util::cast_guess($result);

		Request::response($result);
	}

	public static function book_download()
	{
		Request::validate(
			array(
				'method' => 'GET',
				'auth_level' => 'user',
				'rules'  => array(
					'book_id' => 'numeric,minimum:0',
					'user'    => 'min_length:3,max_length:40'
				)
			)
		);

		$params = Request::get_params();

		self::_restrict_user($params['user']);

		$query = DB::getInstance()->prepare('
			SELECT b.content as download, p.id, downloads
			FROM books b, purchases p
			WHERE b.id = book_id
			AND user_id = (SELECT id FROM users WHERE username = :user)
			AND book_id = :book
		');
		$query->bindValue(':user', $params['user'], \PDO::PARAM_STR);
		$query->bindValue(':book', $params['book_id'], \PDO::PARAM_STR);

		if(!$query->execute())
		{
			self::_response(false, DB::genericError($query));
		}
		elseif(!($result = $query->fetch(\PDO::FETCH_ASSOC)))
		{
			header('HTTP/1.1 403 Forbidden');
		}
		elseif($result['downloads'] >= 100)
		{
			header('HTTP/1.1 403 Forbidden');
			self::_response(false, 'Book download limit reached.');
		}
		elseif(!is_file(($file = Registry::conf()->data_folder.$result['download'])))
		{
			header('HTTP/1.1 404 Not Found');
			self::_response(false, 'Book data not found.');
		}

		DB::getInstance()->query('
			UPDATE purchases
			SET downloads = downloads + 1
			WHERE id = '.$result['id']
		);

		$finfo = new finfo(FILEINFO_MIME_TYPE);
		header('Content-type: '.$finfo->file($file));
		header('Content-length: '.filesize($file));
		header('Content-disposition: attachment; filename='.$result['download']);
		readfile($file);
	}


	/*------ Admin only ------*/

	public static function book_create()
	{
		Request::validate(
			array(
				'method' => 'POST',
				'auth_level' => 'admin',
				'rules'  => array(
					'title'       => 'min_length:3',
					'authors'     => array(
						'name'    => 'min_length:3',
						'surname' => 'min_length:3'
					),
					'description' => 'min_length:3',
					'price'       => 'numeric'
				)
			)
		);

		$uploads = Validate::uploads(
			array(
				'image' => array(
					'mime' => array(
						'jpg' => 'image/jpeg',
						'png' => 'image/png'
					),
					'maxsize' => 1000,
					'check' => function($img) {
						if(!getimagesize($img))
						{
							throw new RuntimeException('Invalid image file');
						}
					}
				),
				'content' => array(
					'mime' => array(
						'pdf' => 'application/pdf'
					),
					'maxsize' => 6000
				)
			)
		);

		$params = Request::get_params();

		$db = DB::getInstance();
		$db->beginTransaction();

		$query = $db->prepare('
			INSERT INTO 
			books (title, price, description, image, content)
			VALUES (:title, :price, :description, :image, :content)
		');
		$query->bindValue(':title', $params['title'], \PDO::PARAM_STR);
		$query->bindValue(':price', $params['price'], \PDO::PARAM_STR);
		$query->bindValue(':description', $params['description'], \PDO::PARAM_STR);
		$query->bindValue(':image', $uploads['image']['name'], \PDO::PARAM_STR);
		$query->bindValue(':content', $uploads['content']['name'], \PDO::PARAM_STR);
		$query->execute();

		$bookid = (int)DB::getInstance()->lastInsertId();

		$query = $db->prepare('
			INSERT INTO authors (firstname, lastname)
			VALUES(:firstname, :lastname)
		');


		foreach($params['authors'] as $authors)
		{
			$query->bindParam(':firstname', $authors['name']);
			$query->bindParam(':lastname', $authors['surname']);
			$query->execute();
		}

		// if(!$query->execute())
		// {
		// 	self::_response(false, DB::genericError($query));
		// }

		$db->commit();

		// gosh darnit we're going to upload those files!
		foreach($uploads as $upload)
		{
			if(!move_uploaded_file(
				$_FILES[$upload['file']]['tmp_name'],
				Registry::conf()->data_folder.$upload['name']))
			{
				self::_response(false, 'Failed to upload files.');
			}
		}

		self::_response(
			true, 'Book successfully added.', array('book_id' => $bookid)
		);
	}

	// TODO:
	// log() (secure audit log)

	/* Private methods, not accessible via API */

	private static function _response($success, $message, $add = array())
	{
		Request::response(array(
			'success' => $success,
			'message' => $message
		) + $add);
	}

	private static function _restrict_user($user)
	{
		if(!Auth::check_user($user))
		{
			Request::response(array(
				'success' => false,
				'message' => 
					'Currently only the user "'.
					filter_var($user, FILTER_SANITIZE_STRING).
					'" may perform this action.'
			));
		}
	}
}
?>