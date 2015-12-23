<?php

/**
* ownCloud - App Template Example
*
* @author VNC Zimbra
* http://www.vnc.biz 
* Copyright 2015, VNC - Virtual Network Consult AG.
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

function formatFileArray($fileArray) {
	$shareList = OCP\Share::getItemsShared("file", OCP\Share::FORMAT_STATUSES);
	$dataArray = array();
	foreach($fileArray as $fileInfo) {
		$entry = array();
		if (OC\Files\Filesystem::getPath($fileInfo['fileid']) == null && $fileInfo['parent']==-1) {
			$path = $fileInfo['name'];
			$entry['mountType'] = "external-root";
		} else if ($fileInfo['name'] == "Shared" && $fileInfo["path"] == null) {
			$path = "/Shared";
		} else if ($fileInfo['usersPath']) {
			$path = $fileInfo["usersPath"];
			$reshare = \OCP\Share::getItemSharedWithBySource('file', $fileInfo['itemSource'], OCP\Share::FORMAT_NONE, null, true);
			$entry['shareOwner'] = $reshare['uid_owner'];
		} else {
			$path = OC\Files\Filesystem::getPath($fileInfo['fileid']);
		}
		$path = preg_replace("/^files/","",$path);
		$entry['fileid'] = $fileInfo['fileid'];
		$entry['parent'] = $fileInfo['parent'];
		$entry['modifydate'] = \OCP\Util::formatDate($fileInfo['mtime']);
		$entry['mtime'] = $fileInfo['mtime'] * 1000;
		$entry['name'] = $fileInfo['name'];
		$entry['permissions'] = $fileInfo['permissions'];
		$entry['type'] = $fileInfo['mimetype'];
		$entry['mimetype'] = $fileInfo['mimetype'];
		$entry['size'] = $fileInfo['size'];
		$entry['etag'] = $fileInfo['etag'];
		$entry['path'] = $path; 
		$entry['url'] = str_replace("%2F", "/",rawurlencode($path));
		if ($shareList != null) {
			if($shareList[$fileInfo['fileid']] != null){
				$entry['share'] = $shareList[$fileInfo['fileid']]["link"];
			}
		}
		$version = \OCP\Util::getVersion();
		$entry['owversion'] = $version[0];
		$dataArray[] = $entry;
	}
	return $dataArray;
}

function formatFileInfo($fileInfo,$shareList = null) {
	$entry = array();
	$mountType = null;
	if ($fileInfo->isShared()) {
		$mountType = 'shared';
	} else if ($fileInfo->isMounted()) {
		$mountType = 'external';
	}
	if ($mountType !== null) {
		if ($fileInfo->getInternalPath() === '') {
			$mountType .= '-root';
		}
		$entry['mountType'] = $mountType;
	}
	$path = preg_replace("/^files/","",$fileInfo['path']);
	if ($mountType == 'external' || $mountType == 'shared') {
		$path = \OC\Files\Filesystem::getPath($fileInfo['fileid']);
	}
	$entry['fileid'] = $fileInfo['fileid'];
	$entry['parent'] = $fileInfo['parent'];
	$entry['modifydate'] = \OCP\Util::formatDate($fileInfo['mtime']);
	$entry['mtime'] = $fileInfo['mtime'] * 1000;
	// only pick out the needed attributes
	$entry['icon'] = \OCA\Files\Helper::determineIcon($fileInfo);
	if (\OC::$server->getPreviewManager()->isMimeSupported($fileInfo['mimetype'])) {
		$entry['isPreviewAvailable'] = true;
	}
	$entry['name'] = $fileInfo->getName();
	$entry['permissions'] = $fileInfo['permissions'];
	$entry['type'] = $fileInfo['mimetype'];
	$entry['mimetype'] = $fileInfo['mimetype'];
	$entry['size'] = $fileInfo['size'];
	$entry['etag'] = $fileInfo['etag'];
	$entry['path'] = $path;
	$entry['url'] = str_replace("%2F", "/",rawurlencode($path));
	if (isset($fileInfo['displayname_owner'])) {
		$entry['shareOwner'] = $fileInfo['displayname_owner'];
	}
	if (isset($fileInfo['is_share_mount_point'])) {
		$entry['isShareMountPoint'] = $fileInfo['is_share_mount_point'];
	}
	if ($shareList != null) {
		if ($shareList[$fileInfo['fileid']] != null) {
			$entry['share'] = $shareList[$fileInfo['fileid']]["link"];
		}
	}
	$version = \OCP\Util::getVersion();
	$entry['owversion'] = $version[0];
	return $entry;
}

function formatFileInfos($fileInfos) {
	$shareList = OCP\Share::getItemsShared("file", OCP\Share::FORMAT_STATUSES);
	$files = array();
	foreach ($fileInfos as $fileInfo) {
		$files[] = formatFileInfo($fileInfo,$shareList);
	}
	return $files;
}

function checkFileExists($files) {
	foreach ($files as $file) {
		if(\OC\Files\Filesystem::file_exists(urldecode($file))) {
			return true;
		}
	}
	return false;
}


$version = \OCP\Util::getVersion();
// Check if user is logged in
\OCP\User::checkLoggedIn();
// Check if application is enabled.
\OCP\App::checkAppEnabled('appVNCsafe');
// Check for CSRF
\OCP\JSON::callCheck();

$op = $_GET["operation"];
if($op == null) {
	$op = $_POST["operation"];
}
if ($op == "tree") {
	if($version[0] == 7 || $version[0] == 8) {
		\OCP\JSON::success(formatFileInfos(\OC\Files\Filesystem::getDirectoryContent($_GET['directory'],"httpd/unix-directory")));
	} else {
		\OCP\JSON::success(\OC\Files\Filesystem::getDirectoryContent($_GET['directory'],"httpd/unix-directory"));
	}
} else if ($op == "list") {
	if($version[0] == 7 || $version[0] == 8) {
		\OCP\JSON::encodedPrint(formatFileInfos(\OC\Files\Filesystem::getDirectoryContent($_GET['directory'])));
	} else {
		\OCP\JSON::encodedPrint(formatFileArray(\OC\Files\Filesystem::getDirectoryContent($_GET['directory'])));
	}
} else if ($op == "delete") {
	foreach($_POST['files'] as $file){
		\OC\Files\Filesystem::unlink(urldecode($file));
	}
	\OCP\JSON::success();
} else if ($op == "getShare" ) {
	if($version[0] == 7 || $version[0] == 8) {
		\OCP\JSON::success(formatFileInfo(OC\Files\Filesystem::getFileInfo($_GET["path"])));
	} else {
		\OCP\JSON::success(OC\Files\Filesystem::getFileInfo($_GET["path"]));
	}
} else if ($op == "search" ) {
	if ($version[0] == 7 || $version[0] == 8) {
		\OCP\JSON::encodedPrint(formatFileInfos(OC\Files\Filesystem::search($_GET["query"])));
	} else {
		\OCP\JSON::encodedPrint(formatFileArray(OC\Files\Filesystem::search($_GET["query"])));
	}
} else if ($op == "exists" ) {
	if ($version[0] == 7 || $version[0] == 8) {
		\OCP\JSON::encodedPrint(checkFileExists($_POST["files"]));
	} else {
		\OCP\JSON::encodedPrint(checkFileExists($_POST["files"]));
	}
}

?>
