--TEST--
Connection - Check Config parameter must contains port key

--SKIPIF--
<?php
include dirname(__FILE__)."/../../astestframework/astest-phpt-loader.inc";
aerospike_phpt_skipif("Connection", "testMissingNameKeyFromConfigArray");

--FILE--
<?php
include dirname(__FILE__)."/../../astestframework/astest-phpt-loader.inc";
aerospike_phpt_runtest("Connection", "testMissingNameKeyFromConfigArray");
--EXPECT--
ERR_PARAM