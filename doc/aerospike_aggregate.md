
# Aerospike::aggregate

Aerospike::aggregate - Applies a stream UDF to a secondary index query

## Description

```
public int Aerospike::aggregate ( string $module, string $function, array $args, string $ns, string $set, array $where, mixed &$value )
```

**Aerospike::aggregate()** will apply the stream UDF *module*.*function* with
*args* to the result of running a secondary index query on *ns*.*set*.
The aggregated *value* is then filled, with its type depending on the UDF.
It may be a string, integer or associative array, and potentially an array of
those, such as in the case the UDF does not specify a reducer and there are
multiple nodes in the cluster (each sending back the result of its own
aggregation).

Currently the only UDF language supported is Lua.  See the
[UDF Developer Guide](http://www.aerospike.com/docs/udf/udf_guide.html) on the Aerospike website.

## Parameters

**module** the name of the UDF module registered against the Aerospike DB.

**function** the name of the function to be applied to the stream.

**args** an array of arguments for the UDF.

**ns** the namespace

**set** the set

**where** the predicate for the query, conforming to one of the following:
```
Associative Array:
  bin => bin name
  op => one of Aerospike::OP_EQ, Aerospike::OP_BETWEEN
  val => scalar integer/string for OP_EQ or array($min, $max) for OP_BETWEEN
```
*examples:*
```
array("bin"=>"name", "op"=>Aerospike::OP_EQ, "val"=>"foo")
array("bin"=>"age", "op"=>Aerospike::OP_BETWEEN, "val"=>array(35,50))
```

**value** filled by one or more of the supported types.

## Return Values

Returns an integer status code.  Compare to the Aerospike class status
constants.  When non-zero the **Aerospike::error()** and
**Aerospike::errorno()** methods can be used.

## Examples

### Example Stream UDF

Registered module **stream_udf.lua**
```lua
local function having_ge_threshold(bin_having, ge_threshold)
    return function(rec)
        debug("group_count::thresh_filter: %s >  %s ?", tostring(rec[bin_having]), tostring(ge_threshold))
        if rec[bin_having] < ge_threshold then
            return false
        end
        return true
    end
end

local function count(group_by_bin)
  return function(group, rec)
    if rec[group_by_bin] then
      local bin_name = rec[group_by_bin]
      group[bin_name] = (group[bin_name] or 0) + 1
      debug("group_count::count: bin %s has value %s which has the count of %s", tostring(bin_name), tostring(group[bin_name]))
    end
    return group
  end
end

local function add_values(val1, val2)
  return val1 + val2
end

local function reduce_groups(a, b)
  return map.merge(a, b, add_values)
end

function group_count(stream, group_by_bin, bin_having, ge_threshold)
  if bin_having and ge_threshold then
    local myfilter = having_ge_threshold(bin_having, ge_threshold)
    return stream : filter(myfilter) : aggregate(map{}, count(group_by_bin)) : reduce(reduce_groups)
  else
    return stream : aggregate(map{}, count(group_by_bin)) : reduce(reduce_groups)
  end
end
```


### Example of aggregating a stream UDF to the result of a secondary index query
```php
<?php

$config = array("hosts"=>array(array("addr"=>"localhost", "port"=>3000)));
$db = new Aerospike($config, 'prod-db');
if (!$db->isConnected()) {
   echo "Aerospike failed to connect[{$db->errorno()}]: {$db->error()}\n";
   exit(1);
}

// assuming test.users has a bin first_name, show the first name distribution
// for users in their twenties
$where = Aerospike::predicateBetween("age", 20, 29);
$res = $db->aggregate("stream_udf", "group_count", array("first_name"), "test", "users", $where, $names);
if ($res == Aerospike::OK) {
    var_dump($names);
} else {
    echo "An error occured while running the AGGREGATE [{$db->errorno()}] ".$db->error();
}

?>
```

We expect to see:

```
array(5) {
  ["Claudio"]=>
  int(1)
  ["Michael"]=>
  int(3)
  ["Jennifer"]=>
  int(2)
  ["Jessica"]=>
  int(3)
  ["Jonathan"]=>
  int(3)
}
```
