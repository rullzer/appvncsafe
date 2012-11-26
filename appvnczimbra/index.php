<?php

/*
    http://www.vnc.biz
    Copyright 2012, VNC - Virtual Network Consult GmbH
    Released under GPL Licenses.
*/

OCP\User::checkLoggedIn();
$id = OC_FileCache::getId($_GET["path"],false);
OCP\JSON::success(array('id' => $id));
?>
