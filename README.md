CrushCache-PHP-LookAside-Cache-Layer
====================================

CrushCache is a PHP Framework that implements an intelligent LookAside cache model.


```php
<?php
  require_once('CrushCache.class.php');
  
  // We keep one object of the CrushCache
  $cache = new CrushCache();
  
  // If user:555 is in the cache, we'll return it super quick!
  // otherwise, we'll run "SELECT * FROM user WHERE user_id = 555 LIMIT 1;"
  // We'll cache the results, and return them
  $user = $cache->("user",555);
  
  // this is the first time we run this query, it will get the data from MySQL and cache it.
  $sql = "SELECT COUNT(*) AS total FROM pageviews WHERE date_viewed='2013-07-06'";
  $total = $cache->getFromQuery($sql);
  $total["total"];
  
  // 1 second later, there's same query ran by another request!
  // This one is will be lot faster, it's all cached!
  $total = $cache->getFromQuery($sql);
  $total["total"];
?>
```
