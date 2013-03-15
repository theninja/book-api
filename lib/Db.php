<?php
namespace BookApi;

class DB
{
	private static $_db = null;
	private static $_query;

	public static function getInstance()
	{
		if(self::$_db == null)
		{
			try
			{
				$db = Registry::conf()->database;

				self::$_db = new \PDO(
					$db->adapter.':host='.$db->params->host.';dbname='.$db->params->dbname,
					$db->params->username,
					$db->params->password
				);
			}
			catch(\PDOException $e)
			{
				echo $e->getMessage();
			}
		}
		return self::$_db;
	}

	public static function genericError($query)
	{
		$info = $query->errorInfo();
		return 'A database error occured ('.$info[0].'), if this problem persists please contact the website administrator.';
	}

	public static function close()
	{
		self::$_db = null;
	}
}


/**
 * Class used to build the more complex queries.
 * It could certainly do with some improving.
 */
class QueryBuilder
{
	private $_where = array();
	private $_params = array();
	private $_query;
	private $_exact;

	public function start($query, $exact = true)
	{
		$this->_query = $query;
		$this->_exact = $exact;
		return $this;
	}

	public function where($w)
	{
		$this->_params = $w;
		foreach($w as $param => $v)
		{
			if(!Util::is_empty($v))
			{
				$this->_query .= 
					(count($this->_where) === 0 ? 'WHERE ' : 'AND ').
					$param.' '.($this->_exact ? '=' : 'LIKE').' :'.$param.' ';
			}
		}
		return $this;
	}

	public function add_custom($string)
	{
		$this->_query .= $string;
		return $this;
	}

	public function limit($start = 0, $length = 500)
	{
		$this->_query .= 'LIMIT '.$start.', '.$length;
		return $this;
	}

	public function execute()
	{
		$this->_query = DB::getInstance()->prepare($this->_query);

		foreach($this->_params as $p => $v)
		{
			if(!Util::is_empty($v))
			{
				$this->_query->bindValue(
					':'.$p,
					$this->_exact ? $v : '%'.$v.'%',
					PDO::PARAM_STR
				);
			}
		}

		return $this->_query->execute() ? $this->_query : false;
	}
}
?>