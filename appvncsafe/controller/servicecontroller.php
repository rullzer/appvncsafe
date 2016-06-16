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
use \OCA\Appvncsafe\Service\TagService;
use \OCP\IUserSession;
use \OCP\Files\Node;

class ServiceController extends ApiController {

	private $tagservice;
	private $userSession;

	public function __construct($appName, IRequest $request,$tagservice,IUserSession $userSession) {
		parent::__construct(
			$appName,
			$request,
			'PUT, POST, GET, DELETE, PATCH',
			'Authorization, Content-Type, Accept'
		);
		$this->tagservice = $tagservice;
                $this->userSession = $userSession;
	}

	/**
	*	@NoCSRFRequired
	*	@NoAdminRequired
	*/
	public function getList($name) {
		return $this->encodeData($this->formatFileInfos(\OC\Files\Filesystem::getDirectoryContent(urldecode($name))));
	}

	/**
	*	@NoCSRFRequired
	*	@NoAdminRequired
	*/
	public function getTree($name) {
		return $this->encodeData($this->formatFileInfos(\OC\Files\Filesystem::getDirectoryContent(urldecode($name),"httpd/unix-directory")));
	}

	/**
	*	@NoCSRFRequired
	*	@NoAdminRequired
	*/
	public function getShare($path) {
		return $this->encodeData($this->formatFileInfo(\OC\Files\Filesystem::getFileInfo(urldecode($path))));
	}

	/**
	*	@NoCSRFRequired
	*	@NoAdminRequired
	*/
	public function getSearch($query) {
		return $this->encodeData($this->formatFileInfos(\OC\Files\Filesystem::search($query)));
	}

	/**
	*	@NoCSRFRequired
	*	@NoAdminRequired
	*/
	public function getFileExists($file) {
		$fileNames  = explode(",",$file);
		return $this->encodeData($this->checkFileExists($fileNames));
	}

	/**
	*	@NoCSRFRequired
	*	@NoAdminRequired
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
	*	@NoAdminRequired
	*/
	public function copyFile($source,$destination) {
		$src = explode(",",$source);
		$dest = str_replace('+++', '\\', $destination);
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
	*	@NoAdminRequired
	*/
	public function moveFile($source,$destination) {
		$src = explode(",",$source);
		$dest = str_replace('+++', '\\', $destination);
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
	*	@NoAdminRequired
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
	*	@NoAdminRequired
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
	*	@NoAdminRequired
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
		/*if (is_array($data)) {
			array_walk_recursive($data, array('OC_JSON', 'to_string'));
		}*/
		return json_decode(json_encode($data, JSON_HEX_TAG));
	}

	/**
	*	@NoCSRFRequired
	*	@NoAdminRequired
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
	*	@NoAdminRequired
	*/
	function formatFileInfoFilter($fileInfo,$shareList = null,$pathValue) {
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
		$path = preg_replace("/^files/","",$pathValue);
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
	*	@NoAdminRequired
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

	/**
	*	@NoCSRFRequired
	*	@NoAdminRequired
	*/
	public function getShareWithYou() {
		$arr =  \OCP\Share::getItemSharedWithBySource('file');
		$dataArray = array();
		$version = \OCP\Util::getVersion();
		foreach ($arr as $value) {
			$userId = $this->userSession->getLoginName();
			\OC\Files\Filesystem::initMountPoints($userId);
			$view = new \OC\Files\View('/' . $userId . '/files');
			$pathId = $value['file_source'];
			$path = $view->getPath($pathId);
			$fileInfo = $view->getFileInfo($path);
			$shareList = \OCP\Share::getItemsShared("file", \OCP\Share::FORMAT_STATUSES);
			$dataArray [] = $this->formatFileInfoFilter($fileInfo,$shareList,$value['file_target']);
		}
		return $dataArray;
	}

	/**
	*	@NoCSRFRequired
	*	@NoAdminRequired
	*/
	public function getShareWithOthers() {
		$arr =  \OCP\Share::getItemShared('file', null);
		$dataArray = array();
		$version = \OCP\Util::getVersion();
		foreach ($arr as $value) {
			$userId = $this->userSession->getLoginName();
			\OC\Files\Filesystem::initMountPoints($userId);
			$view = new \OC\Files\View('/' . $userId . '/files');
			$pathId = $value['file_source'];
			$path = $view->getPath($pathId);
			$pathInfo = $view->getFileInfo($path);
			$shareList = \OCP\Share::getItemsShared("file", \OCP\Share::FORMAT_STATUSES);
			$dataArray [] = $this->formatFileInfoFilter($pathInfo,$shareList,$value['path']);
		}
		return $dataArray;
	}

	/**
	*	@NoCSRFRequired
	*	@NoAdminRequired
	*/
	public function getFavorites(){
		$version = \OCP\Util::getVersion();
		$names = "_%24!%3CFavorite%3E!%24_";
		$nodes = $this->tagservice->getFilesByTag(urldecode($names));
		$files = array();
		$dataArray = array();
		foreach ($nodes as &$node) {
			$fileInfo = $node->getFileInfo();
			$file = \OCA\Files\Helper::formatFileInfo($fileInfo);
			$parts = explode('/', dirname($fileInfo->getPath()), 4);
			if(isset($parts[3])) {
				$file['path'] = '/' . $parts[3];
			} else {
				$file['path'] = '/';
			}
			$file['tags'] = [urldecode($names)];
			if (!empty($shareTypes)) {
				$file['shareTypes'] = $shareTypes;
			}
			$path =  \OC\Files\Filesystem::getPath($fileInfo['fileid']);
			$shareList = \OCP\Share::getItemsShared("file", \OCP\Share::FORMAT_STATUSES);
			$dataArray [] = $this->formatFileInfoFilter($fileInfo,$shareList,$path);
		}
		return $this->encodeData($dataArray);
	}

	/**
	*	@NoCSRFRequired
	*	@NoAdminRequired
	*/
	public function getShareWithLink() {
		$arr =  \OCP\Share::getItemShared('file', null);
		$dataArray = array();
		$version = \OCP\Util::getVersion();
		foreach ($arr as $value) {
			$type = '';
			$entry = array();
			if($value['share_type']==3){
				$userId = $this->userSession->getLoginName();
				\OC\Files\Filesystem::initMountPoints($userId);
				$view = new \OC\Files\View('/' . $userId . '/files');
				$pathId = $value['file_source'];
				$path = $view->getPath($pathId);
				$pathInfo = $view->getFileInfo($path);
				$shareList = \OCP\Share::getItemsShared("file", \OCP\Share::FORMAT_STATUSES);
				$dataArray [] = $this->formatFileInfoFilter($pathInfo,$shareList,$value['path']);

			}
		}
		return $dataArray;
	}
}
