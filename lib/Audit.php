<?php
/*
	TODO: This whoel class (Audit trail).
 */
namespace BookApi;

class Audit
{
	/**
	 * Creates an audit log entry.
	 * @param  string $message Message/details to add to database.
	 */
	public static function log($message)
	{
		$query = DB::getInstance()->prepare(
			'SELECT hash FROM auditlogs ORDER BY id DESC'
		);

		if($query->execute() && isset($query->fetch(PDO::FETCH_ASSOC)['hash']))
		{
			$lastHash = $query->fetch(PDO::FETCH_ASSOC)['hash'];
		}
		else
		{
			$lastHash = '';
		}

		$now = date('Y-m-d H:i:s');

		$query = DB::getInstance()->prepare('SELECT akey FROM auditkey WHERE 1');

		if($query->execute() && isset($query->fetch(PDO::FETCH_ASSOC)['akey']))
		{
			$currentKey = $query->fetch(PDO::FETCH_ASSOC)['akey'];
		}
		else
		{
			$query = DB::getInstance()->prepare(
				'INSERT INTO auditkey(akey) VALUES (:key)'
			);
			$query->bindValue(':key', AUDIT_LOG_START_KEY, PDO::PARAM_STR);
			$query->execute();

			$currentKey = AUDIT_LOG_START_KEY;
		}

		$nextKey = sha1($currentKey.$now.$message);
		$entryHash = sha1($lastHash.$now.$message);

		$query = DB::getInstance()->prepare(
			'INSERT INTO auditlogs(datetime, details, hash, signature)
			VALUES (:datetime, :details, :hash, AES_ENCRYPT(:hash2, :currentKey))'
		);

		$query->bindValue(':datetime', $now, PDO::PARAM_STR);
		$query->bindValue(':details', $message, PDO::PARAM_STR);
		$query->bindValue(':hash', $entryHash, PDO::PARAM_STR);
		$query->bindValue(':hash2', $entryHash, PDO::PARAM_STR);
		$query->bindValue(':currentKey', $currentKey, PDO::PARAM_STR);

		$query->execute();
		
		$query = DB::getInstance()->prepare(
			'UPDATE auditkey SET akey = :nextKey WHERE akey = :oldKey'
		);
		$query->bindValue(':nextKey', $nextKey, PDO::PARAM_STR);
		$query->bindValue(':oldKey', $currentKey, PDO::PARAM_STR);

		var_dump($query);

		if(!$query->execute())
		{
			print_r($query->errorInfo());
		}
		else
		{
			echo 'success';
		}
	}

	/**
	 * Unimplemented method, for verifyling integrity of audit logs.
	 * @return boolean Whether or not the audit logs have been tampered with.
	 */
	public static function verify()
	{
		/*
			- Get inital key from config file.
			- Go through database generating hashes and next keys,
			  verifying they match what is expected. 
			- As soon as no match then this entry has been 
			  tampered with, or entries have been deleted.
		*/
	}

	/**
	 * Gets all audit log entries as an array.
	 * @return array Array containing all entries.
	 */
	public static function getAll()
	{
		return array();
	}

}
?>