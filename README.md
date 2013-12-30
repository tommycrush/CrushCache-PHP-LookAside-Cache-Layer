CrushCache-PHP-LookAside-Cache-Layer
====================================


CrushCache is a PHP Framework that implements an intelligent LookAside cache model.


You're currently running a lot of SQL queries. CrushCache caches those results. For example:
```php
  $get_comments_sql = "SELECT * FROM comments WHERE post_id='5' ORDER BY date_written";
```

The results will stored in memcached under a key that contains a hash of the SQL
Example: "query:d8e8fca2dc0f896fd7cb4cb0031ba249"

Next time this is run, we'll check to see if the key exists. If it does, no need to run the query again.




```php
<?php
  require_once('CrushCache.class.php');
  
  // We keep one object of the CrushCache
  $cache = new CrushCache();
  
  // this is the first time we run this query, it will get the data from MySQL and cache it.
  $post_id = 5;
  $get_comments_sql = "SELECT * FROM comments WHERE post_id='".$post_id."' ORDER BY date_written";
  $comments = $cache->getFromQuery($get_comments_sql);
  
  // 1 second later, there's same query ran by another request!
  // This one is will be lot faster, it's all cached!
  $total = $cache->getFromQuery($get_comments_sql);
  $total["total"];
  
  
  // The Trade-off:
  // We have to invalidate the cache when we get new data for that query.
  
  // We can do this more efficiently. Let's get everything from the comment table where post_id = 5
  $comments = $cache->get("comments", array("post_id" => 5));
  // this will be cached in comments:post_id:5
  $cache->delete("comments", array("post_id" => 5));
  $cache->update("comments", array("post_id" => 5));
  
?>
```
