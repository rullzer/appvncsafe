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

namespace OCA\Appvncsafe\Controller;

use \OCP\IRequest;
use \OCP\AppFramework\ApiController;
use \OCP\Share;

class ServiceController extends ApiController {

	public function __construct($appName, IRequest $request) {
		parent::__construct(
			$appName,
			$request,
			'PUT, POST, GET, DELETE, PATCH',
			'Authorization, Content-Type, Accept'
		);
	}

	/**
	*	@NoCSRFRequired
	*/
	public function getList($name) {
		return $this->encodeData($this->formatFileInfos(\OC\Files\Filesystem::getDirectoryContent(urldecode($name))));
	}

	/**
	*	@NoCSRFRequired
	*/
	public function getTree($name) {
		return $this->encodeData($this->formatFileInfos(\OC\Files\Filesystem::getDirectoryContent(urldecode($name),"httpd/unix-directory")));
	}

	/**
	*	@NoCSRFRequired
	*/
	public function getShare($path) {
		return $this->encodeData($this->formatFileInfo(\OC\Files\Filesystem::getFileInfo(urldecode($path))));
	}

	/**
	*	@NoCSRFRequired
	*/
	public function getSearch($query) {
		return $this->encodeData($this->formatFileInfos(\OC\Files\Filesystem::search($query)));
	}

	/**
	*	@NoCSRFRequired
	*/
	public function getFileExists($file) {
		$fileNames  = explode(",",$file);
		return $this->encodeData($this->checkFileExists($fileNames));
	}

	/**
	*	@NoCSRFRequired
	*/
	public function deleteFile($names) {
		$fileNames  = explode(",",$names);
		foreach($fileNames as $file){
			\OC\Files\Filesystem::unlink(urldecode($file));
		}
		return \OCP\JSON::success();
	}

	/**
	*	@NoCSRFRequired
	*/
	public function copyFile($source,$destination) {
		$src = explode(",",$source);
		$dest = str_replace('+', '\\', $destination);
		foreach($src as $file) {
			$file = str_replace('\\','/',$file);
			$dfile = urldecode($file);
			$fdest = $dest . substr($dfile, strripos($dfile, "/"));
			if(!(\OC\Files\Filesystem::copy($dfile, $fdest))) {
				return $this->encodeData(array("status" => "success"));
			}
		}
		return $this->encodeData(array("status" => "success"));
	}

	/**
	*	@NoCSRFRequired
	*/
	public function moveFile($source,$destination) {
		$src = explode(",",$source);
		$dest = str_replace('+', '\\', $destination);
		foreach($src as $file){
			$file = str_replace('\\','/',$file);
			$dfile = urldecode($file);
			$fdest = $dest . substr($dfile, strripos($dfile, "/"));
			if(!(\OC\Files\Filesystem::rename($dfile, $fdest))) {
				return $this->encodeData(array("status" => "success"));
			}
		}
		return $this->encodeData(array("status" => "success"));
	}

	/**
	*	@NoCSRFRequired
	*/
	public function renameFile($oldname,$newname,$path) {
		$path = urldecode($path);
		$oldname = urldecode($oldname);
		$newname = urldecode($newname);
		$opath = $path.$oldname;
		$npath = $path.$newname;
		if(!(\OC\Files\Filesystem::rename($opath, $npath))) {
			return $this->encodeData(array("status" => "error"));
		}
		return $this->encodeData(array("status" => "success"));
	}

	/**
	*	@NoCSRFRequired
	*/
	public function createFolder($path) {
		$path = urldecode($path);
		if (!\OC\Files\Filesystem::file_exists($path)) {
			if(!\OC\Files\Filesystem::mkdir($path)) {
				return $this->encodeData(array("status" => "error"));
			}
		} else {
			return $this->encodeData(array("status" => "exist"));
		}
		return $this->encodeData(array("status" => "success"));
	}

	/**
	*	@NoCSRFRequired
	*/
	public function sendMail($toaddress,$type,$link) {
		$toaddressh = urldecode($toaddress);
		$link = urldecode($link);
		$type = urldecode($type);
		$defaults = new \OCP\Defaults();
		$mailNotification = new \OC\Share\MailNotifications(
			\OC::$server->getUserSession()->getUser(),
			\OC::$server->getL10N('lib'),
			\OC::$server->getMailer(),
			\OC::$server->getLogger(),
			$defaults
		);
		$result = $mailNotification->sendLinkShareMail($toaddressh, $type, $link,'');
		return $this->encodeData(array("status" => "success"));
	}

	private function encodeData($data) {
		if (is_array($data)) {
			array_walk_recursive($data, array('OC_JSON', 'to_string'));
		}
		return json_decode(json_encode($data, JSON_HEX_TAG));
	}


	function formatFileArray($fileArray) {
		$version = \OCP\Util::getVersion();
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
			$entry['owversion'] = $version[0];
			$dataArray[] = $entry;
		}
		return $dataArray;
	}

	/**
	*	@NoCSRFRequired
	*/
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

	/**
	*	@NoCSRFRequired
	*/
	public function formatFileInfos($fileInfos) {
		$shareList = \OCP\Share::getItemsShared("file", \OCP\Share::FORMAT_STATUSES);
		$files = array();
		foreach ($fileInfos as $fileInfo) {
			$files[] = $this->formatFileInfo($fileInfo,$shareList);
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

	function copyr($src, $dest){
		if (!\OC\Files\Filesystem::is_dir($src)) {
			return \OC\Files\Filesystem::copy($src, $dest);
		} else {
			if (($dh = \OC\Files\Filesystem::opendir($src)) !== false) {
				if (!\OC\Files\Filesystem::file_exists($dest)) {
					\OC\Files\Filesystem::mkdir($dest);
				}
				while (($file = readdir($dh)) !== false) {
					if ($file == "." || $file == "..") continue;
					if (!copyr($src.'/'.$file, $dest.'/'.$file)) return false;
				}
			}
			return true;
		}
	}
}
