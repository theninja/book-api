<?php
namespace BookApi;

class Config
{
	private $data = array();

	public function __construct(array $array)
	{
		foreach($array as $option => $value)
		{
			if(is_array($value))
			{
				$this->data[$option] = new static($value);
			}
			else
			{
				$this->data[$option] = $value;
			}
		}
	}

	public function get($name, $alt = null)
	{
		return array_key_exists($name, $this->data) ? $this->data[$name] : $alt;
	}

	public function __get($name)
	{
		return $this->get($name);
	}

	public function __isset($name)
	{
		return isset($this->data[$name]);
	}

	public function __unset($name)
	{
		if(isset($this->$name))
		{
			unset($this->data[$name]);
		}
	}
}
?>