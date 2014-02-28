<?php

/**
* ownCloud - App Template Example
*
* @author VNC Zimbra
* http://www.vnc.biz 
* Copyright 2012, VNC - Virtual Network Consult GmbH
* Released under GPL Licenses.
*
* This library is free software; you can redistribute it and/or
* modify it under the terms of the GNU AFFERO GENERAL PUBLIC LICENSE
* License as published by the Free Software Foundation; either
* version 3 of the License, or any later version.
*
* This library is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
* GNU AFFERO GENERAL PUBLIC LICENSE for more details.
*
* You should have received a copy of the GNU Affero General Public
* License along with this library.  If not, see <http://www.gnu.org/licenses/>.
*
*/

// Check if user is logged in
OCP\User::checkLoggedIn();
// Check if application is enabled.
OCP\App::checkAppEnabled('appVNCsafe');
//return the ID
	$op = $_GET["operation"];
	if ($op == "tree") {
		OCP\JSON::success(OC\Files\Filesystem::getDirectoryContent($_GET["directory"],'httpd/unix-directory'));
	} else if ($op == "getShare" ) {
		OCP\JSON::success(OC\Files\Filesystem::getFileInfo($_GET["path"]));
	}
?>
