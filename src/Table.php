<?php
namespace FluentCQL;
use Cassandra\Type;

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
     * @var string
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
     * @var array
     */
    protected static $_columns;
    
    /**
     * 
     * @var int
     */
    protected static $_writeConsistency;
    
    /**
     * 
     * @var int
     */
    protected static $_readConsistency;
    
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
     * @return \Cassandra\Response\Response
     */
    public static function find(){
        $args = func_get_args();
        $keyNames = array_values(static::$_primary);
        
        $whereList = [];
        
        $query = Query::select('*')
            ->from(static::$_name);
        
        $conditions = [];
        foreach($args as $index => $arg){
            $conditions[] = static::$_primary[$index] . ' = ?'; 
        }
        
        $bind = [];
        foreach($args as $index => $arg){
            $type = static::$_columns[static::$_primary[$index]];
            $bind[] = Type\Base::getTypeObject($type, $arg);
        }
        
        $response = $query->where(implode(' AND ', $conditions))
            ->bind($bind)
            ->setDbAdapter(static::$_dbAdapter)
            ->setConsistency(static::$_readConsistency)
            ->querySync()
            ->setRowClass(get_called_class());
        
        return $response;
    }
    
    public static function getColumnsDefinition(){
        $query = Query::select('*')->from(static::$_name);
        $prepared = $query->prepare();
        $def = [];
        foreach($prepared['result_metadata']['columns'] as $col){
            $def[$col['name']] = $col['type'];
        }
        
        return $def;
    }
    
    /**
     * 
     * @return Query
     */
    public static function select($cols = null){
        return Query::select($cols ?: '*')
            ->from(static::$_name)
            ->setDbAdapter(static::$_dbAdapter)
            ->setConsistency(static::$_readConsistency);
    }
    
    public static function insert(){
        return Query::insertInto(static::$_name)
            ->setDbAdapter(static::$_dbAdapter)
            ->setConsistency(static::$_writeConsistency);
    }
    
    /**
     * 
     * @return Query
     */
    public static function update(){
        return Query::update(static::$_name)
            ->setDbAdapter(static::$_dbAdapter)
            ->setConsistency(static::$_writeConsistency);
    }
    
    /**
     * 
     * @return Query
     */
    public static function delete(){
        return Query::delete()
            ->from(static::$_name)
            ->setDbAdapter(static::$_dbAdapter)
            ->setConsistency(static::$_writeConsistency);
    }
    
    /**
     * 
     * @return Query
     */
    public static function insertRow(array $data){
        $bind = [];
        foreach($data as $key => $value)
            $bind[] = Type\Base::getTypeObject(static::$_columns[$key], $value);
        
        $query = Query::insertInto(static::$_name)
            ->clause('(' . \implode(', ', \array_keys($data)) . ')')
            ->values('(' . \implode(', ', \array_fill(0, count($data), '?')) . ')')
            ->bind($bind)
            ->setDbAdapter(static::$_dbAdapter)
            ->setConsistency(static::$_writeConsistency);
        
        return $query;
    }
    
    /**
     *
     * @param array $primary
     * @param array $data
     * @return Query
     */
    public static function updateRow($primary, array $data){
        $assignments = [];
        foreach($data as $columnName => $value){
            $assignments[] = $columnName . ' = ?';
            $bind[] = Type\Base::getTypeObject(static::$_columns[$columnName], $value);
        }
        
        $conditions = [];
        foreach((array)$primary as $index => $value){
            $columnName = static::$_primary[$index];
            $conditions[] = $columnName . ' = ?';
            $bind[] = Type\Base::getTypeObject(static::$_columns[$columnName], $value);
        }
        
        $query = Query::update(static::$_name)
            ->set(implode(', ', $assignments))
            ->where(implode(' AND ', $conditions))
            ->bind($bind)
            ->setDbAdapter(static::$_dbAdapter)
            ->setConsistency(static::$_writeConsistency);
        
        return $query;
    }
    
    /**
     * 
     * @param array $primary
     * @return Query
     */
    public static function deleteRow($primary){
        $conditions = [];
        $bind = [];
        
        foreach((array)$primary as $index => $value){
            $columnName = static::$_primary[$index];
            $conditions[] = $columnName . ' = ?';
            $bind[] = Type\Base::getTypeObject(static::$_columns[$columnName], $value);
        }
        
        $query = Query::delete()
            ->from(static::$_name)
            ->where(implode(' AND ', $conditions))
            ->bind($bind)
            ->setDbAdapter(static::$_dbAdapter)
            ->setConsistency(static::$_writeConsistency);
        
        return $query;
    }
    
    /**
     * 
     * @var array
     */
    protected $_cleanData = [];
    
    /**
     * Tracks columns where data has been updated. Allows more specific insert and
     * update operations.
     *
     * @var array
     */
    protected $_modifiedData = [];
    
    /**
     * 构造函数
     * @param array $data
     * @param int $timestamp
     * @param int $ttl
     */
    public function __construct($data = [], $stored = null){
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
     * @param  mixed  $value      The value for the property.
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
    
    /**
     * 
     * @return self
     */
    public function save()
    {
        $bind = [];
        if (empty($this->_cleanData)) {
            $data = $this->getArrayCopy();
            $bind = [];
            foreach($data as $key => $value)
                $bind[] = Type\Base::getTypeObject(static::$_columns[$key], $value);
            
            $query = Query::insertInto(static::$_name)
                ->clause('(' . \implode(', ', \array_keys($data)) . ')')
                ->values('(' . \implode(', ', \array_fill(0, count($data), '?')) . ')')
                ->bind($bind);
        }
        else{
            $assignments = [];
            
            foreach($this->_modifiedData as $key => $value){
                $assignments[] = $key . ' = ?';
                $bind[] = Type\Base::getTypeObject(static::$_columns[$key], $value);
            }
            
            $conditions = [];
            foreach(static::$_primary as $key){
                $conditions[] = $key . ' = ?';
                $bind[] = Type\Base::getTypeObject(static::$_columns[$key], $this[$key]);
            }
            
            $query = Query::update(static::$_name)
                ->set(implode(', ', $assignments))
                ->where(implode(' AND ', $conditions))
                ->bind($bind);
        }
        
        $query->setDbAdapter(static::$_dbAdapter)
            ->setConsistency(static::$_writeConsistency)
            ->querySync();
        
        return $this;
    }
    
    /**
     * 删除一整行
     * @return \Cassandra\Response\Response
     */
    public function remove(){
        $query = Query::delete()
            ->from(static::$_name);
        
        $conditions = [];
        $bind = [];
        
        foreach(static::$_primary as $key){
            $conditions[] = $key . ' = ?';
            $bind[] = Type\Base::getTypeObject(static::$_columns[$key], $this[$key]);
        }
        
        $query->where(implode(' AND ', $conditions))
            ->bind($bind)
            ->setDbAdapter(static::$_dbAdapter)
            ->setConsistency(static::$_writeConsistency);
        
        return $query->querySync();
    }
    
    public static function getKeyspace()
    {
        return static::$_keyspace;
    }

    public static function getColumns()
    {
        return static::$_columns;
    }

    public static function getTableName()
    {
        return static::$_name;
    }

    public static function getPrimary()
    {
        return static::$_primary;
    }

    public static function getReadConsistency()
    {
        return static::$_readConsistency;
    }

    public static function getWriteConsistency()
    {
        return static::$_writeConsistency;
    }
    
}
