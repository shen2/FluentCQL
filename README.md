FluentCQL
=========

## Dependency

- PHP 5.4+
- [duoshuo/php-cassandra](https://github.com/duoshuo/php-cassandra) (Required)
- [duoshuo/uuid](https://github.com/duoshuo/uuid) (Optional)

## Initialize

```php
$connection = new Cassandra\Connection(['127.0.0.1'], 'my_keyspace');
FluentCQL\Table::setDefaultDbAdapter($connection);
```

## FluentCQL\Query

- INSERT COMMAND
```php
$query = FluentCQL\Query::select('count(*)')
	->from'table_name')
    ->where('id = ?', 123);

// SELECT count(*) FROM table_name where id = 123

```

- UPDATE COMMAND
```php
$query = FluentCQL\Query::update('table_name')
    ->set('a = :a, b = :b', $a, $b)
    ->where('c = :c', $c)
    ->and('d = :d', $d)
    ->ifExists();

$query->assemble(); // 'UPDATE table_name SET a = :a, b = :b WHERE c = :c AND d = :d'

$query->querySync(); // Call querySync() on $dbAdapter
```


## FluentCQL\Table

### Table Class Definition
```php
class Friendship extends \FluentCQL\Table{

	protected static $_name = 'friendship';

	protected static $_primary = array('from_id', 'to_id');

	protected static $_columns = array(
			'from_id'	=>	Type\Base::BIGINT,
			'to_id'		=>	Type\Base::BIGINT,
			'updated_uuid'=>Type\Base::TIMEUUID,
	);
	
	protected static $_readConsistency = 0x0001;
	
	protected static $_writeConsistency = 0x0002;
}
```

### Queries
* Table::find() 
```php
$response = Friendship::find(321, 123);		// synchronously query and get binary response 
$response->fetchAll();		// get all rows in a SplFixedArray
$response->fetchRow();		// get first row in a ArrayObject from response
```

* Table::select()
```php
// this way totally equal to Friendship::find()
$response = Friendship::select()
	->where('from_id = ? AND to_id = ?', 321, 123)
	->querySync();
```

* Table::insert()
```php
$response = Friendship::insert()
    ->clause('(from_id, to_id, updated_uuid)')
    ->values('(?, ?, ?)', 321, 123, new \Cassandra\Type\Timeuuid('2dc65ebe-300b-11e4-a23b-ab416c39d509'))
    ->querySync();
```

* Table::update()
```php
$response = Friendship::update()
	->set('update_uuid = ?', new \Cassandra\Type\Timeuuid('2dc65ebe-300b-11e4-a23b-ab416c39d509'))
    ->where('from_id = ? AND to_id = ?', 321, 123)
    ->querySync();
```

* Table::delete()
```php
$response = Friendship::delete()
    ->where('from_id = ? AND to_id = ?', 321, 123)
    ->if('updated_uuid = ?', new \Cassandra\Type\Timeuuid('2dc65ebe-300b-11e4-a23b-ab416c39d509'))
    ->querySync();
```

* Table::insertRow()
```php
// Insert a row through an array.
$response = Friendship::insertRow([
    'from_id' => 123,
    'to_id'   => 321,
    'updated_uuid'=> '2dc65ebe-300b-11e4-a23b-ab416c39d509',
])->querySync();
```

### Custom Consistency and Options
```php
$query = new FluentCQL\Query();
$query->setConsistency(0x0001)
	->setOptions(['page_size' => 20]);
```

### ActiveObject-like Usage
```php
$post = new Friendship(); 
$post['from_id'] = 123;
$post['to_id'] = 321;
$post['updated_uuid'] = '2dc65ebe-300b-11e4-a23b-ab416c39d509';
$post->save();
```
