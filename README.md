FluentCQL
=========

## Dependency

- **duoshuo/php-cassandra**

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
}
```

### Queries 
```php
$response = Friendship::find(321,123);		// synchronously query and get binary response 
$response->fetchAll();		// get all rows in a SplFixedArray
$response->fetchRow();		// get first row in a ArrayObject from response
```

```php
Friendship::select();
Friendship::insert();
Friendship::update();
Friendship::delete();
```

### ActiveObject-like Usage
```php
$post = new Friendship(); 
$post['from_id'] = 123;
$post['to_id'] = 321;
$post['updated_uuid'] = new Cassandra\Type\TimeUUID('2dc65ebe-300b-11e4-a23b-ab416c39d509');
$post->save();
```

### FluentCQL\TimeUUID
```php
$uuidString = FluentCQL\TimeUUID::getTimeUUID('127.0.0.1', time());
// '037ed880-34bf-11e4-8b5c-f528764d624d'
```
