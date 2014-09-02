<?php 
namespace FluentCQL;

abstract class Table extends \ArrayObject
{
	/**
	 * Adapter object.
	 *
	 * @var \Cassandra\Connection
	 */
	protected static $_dbAdapter;

	/**
	 * The keyspace name (default null means current keyspace)
	 *
	 * @var array
	 */
	protected static $_keyspace;
	
	/**
	 * 
	 * @var string
	 */
	protected static $_name;
	
	/**
	 * 
	 * @var array
	 */
	protected static $_primary;
	
	/**
	 * 
	 * @param \Cassandra\Connection $adapter
	 */
	public static function setDefaultDbAdapter(\Cassandra\Connection $adapter){
		self::$_dbAdapter = $adapter;
	}
	
	/**
	 * 
	 * @return \Cassandra\Connection
	 */
    public static function getDefaultDbAdapter(){
        return self::$_dbAdapter;
    }
	
	/**
	 * 
	 * @param \Cassandra\Connection $adapter
	 */
	public static function setDbAdapter(\Cassandra\Connection $adapter){
		static::$_dbAdapter = $adapter;
	}
	
	/**
	 * 
	 * @return \Cassandra\Connection
	 */
    public static function getDbAdapter(){
        return static::$_dbAdapter;
    }
	
	/**
	 * 
	 */
	public static function find(){
		$args = func_get_args();
		$keyNames = array_values(static::$_primary);
		
		$whereList = array();
		
		$query = Query::select('*')
			->from(static::$_name);
		
		$conditions = array();
		foreach($args as $index => $arg){
			$conditions[] = static::$_primary[$index] . ' = ?'; 
		}
		
		$rows = $query->where(implode(' AND ', $conditions))
			->bind($args)
			->setAdapter(static::$_dbAdapter)
			->querySync();
		
		return static::_convertToObjects($rows);
	}
	
	/**
	 * 
	 * @param mixed $rows
	 */
	protected static function _convertToObjects($rows){
		$rowset = new \SplFixedArray(count($rows));
		
		foreach($rows as $index => $row)
			$rowset[$index] = new static($row);
		
		return $rowset;
	} 
	
	/**
	 * 
	 * @return Select
	 */
	public static function select($cols = null){
		return Query::select($cols ?: '*')
			->from(static::$_name)
			->setAdapter(static::$_dbAdapter);
	}
	
	/**
	 * 
	 * @return Insert
	 */
	public static function insertRow($data){
		$query = Query::insertInto(static::$_name)
			->clause('(' . \implode(', ', \array_keys($data)) . ')')
			->values('(' . \implode(', ', \array_fill(0, count($data), '?')) . ')')
			->bind(\array_values($data))
			->setAdapter(static::$_dbAdapter);
		
		return $query;
	}
	
	public static function insert(){
		return Query::insertInto(static::$_name)
			->setAdapter(static::$_dbAdapter);
	}
	
	/**
	 * 
	 * @return Update
	 */
	public static function update($data, $where){
		return Query::update(static::$_name)
			->setAdapter(static::$_dbAdapter);
	}
	
	/**
	 * 
	 * @return Delete
	 */
	public static function delete(){
		return Query::delete()
			->from(static::$_name)
			->setAdapter(static::$_dbAdapter);
	}
	
	protected $_cleanData = array();
	
	/**
	 * Tracks columns where data has been updated. Allows more specific insert and
	 * update operations.
	 *
	 * @var array
	 */
	protected $_modifiedData = array();
	
	/**
	 * 构造函数
	 * @param array $data
	 * @param int $timestamp
	 * @param int $ttl
	 */
	public function __construct($data = array(), $stored = null){
		parent::__construct($data);
	
		//$this->timestamp = $timestamp;
		//$this->ttl = $ttl;
	
		if ($stored === true) {
			$this->_cleanData = $this->getArrayCopy();
		}
	
		//$this->init();
	}
	
	public function offsetGet($columnName)
	{
		return parent::offsetExists($columnName) ? parent::offsetGet($columnName) : null;
	}
	
	/**
	 * Set row field value
	 *
	 * @param  string $columnName The column key.
	 * @param  mixed  $value	  The value for the property.
	 * @return void
	 */
	public function offsetSet($columnName, $value)
	{
		if (!in_array($columnName, static::$_primary)){
			$this->_modifiedData[$columnName] = $value;
		}
		
		parent::offsetSet($columnName, $value);
	}
	
	public function offsetUnset($columnName)
	{
		parent::offsetUnset($columnName);
		$this->_modifiedData[$columnName] = null;
	}
	
	public function save()
	{
		$bind = array();
		if (empty($this->_cleanData)) {
			$data = $this->getArrayCopy();
			$query = Query::insertInto(static::$_name)
				->clause('(' . \implode(', ', \array_keys($data)) . ')')
				->values('(' . \implode(', ', \array_fill(0, count($data), '?')) . ')')
				->bind(\array_values($data));
		}
		else{
			$assignments = array();
			
			foreach($this->_modifiedData as $key => $value){
				$assignments[] = $key . ' = ?';
				$bind[] = $value;
			}
			
			$conditions = array();
			foreach(static::$_primary as $key){
				$conditions[] = $key . ' = ?';
				$bind[] = $this[$key];
			}
			
			$query = Query::update(static::$_name)
				->set(implode(', ', $assignments))
				->where(implode(' AND ', $conditions))
				->bind($bind);
		}
		
		static::$_dbAdapter->querySync($query->assemble(), $query->getBind());
		
		return $this;
	}
	
	/**
	 * 删除一整行
	 * @return Model
	 */
	public function remove(){
		$query = Query::delete()
			->from(static::$_name);
		
		$conditions = array();
		$bind = array();
		
		foreach(static::$_primary as $key){
			$conditions[] = $key . ' = ?';
			$bind[] = $this[$key];
		}
		
		$query->where(implode(' AND ', $conditions))
			->bind($bind);
		
		return static::$_dbAdapter->querySync($query->assemble(), $query->getBind());
	}
}
