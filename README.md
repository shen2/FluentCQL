FluentCQL
=========

$query = Query::update('table_name')
    ->set('a = :a', 'b = :b')
    ->where('c = :c')
    ->and('d = :d')
    ->ifExists()
    ->exec(array('a' => $a, 'b' => $b, 'c' => $c, 'd' => $d));

$query->assemble();
 === 'UPDATE table_name SET a = :a, b = :b WHERE c = :c AND d = :d';


$post = new Post(); 
$post['post_id'] = 123;
$post['content'] = 'hello world';
$post->save();

Post::select()
    ->where('post_id = ')

