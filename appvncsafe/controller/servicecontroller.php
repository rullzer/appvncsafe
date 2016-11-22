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
use \OCP\IDateTimeFormatter;

class ServiceController extends ApiController {

	private $tagservice;
	private $userSession;
	private $dateTimeFormatter;
	protected $appName;

	public function __construct($appName, IRequest $request,$tagservice,IUserSession $userSession,IDateTimeFormatter $dateTimeFormatter,$applicationName) {
		parent::__construct(
			$appName,
			$request,
			'PUT, POST, GET, DELETE, PATCH',
			'Authorization, Content-Type, Accept'
		);
		$this->tagservice = $tagservice;
                $this->userSession = $userSession;
		$this->dateTimeFormatter = $dateTimeFormatter;
		$this->appName = $applicationName;
	}

	/**
	*	@NoCSRFRequired
	*	@NoAdminRequired
	*/
	public function getList($name) {
		\OC::$server->getLogger()->debug($this->appName . ' [ getList() Method ]   [ Parameter : $name = ' . $name .' ]');
		return $this->encodeData($this->formatFileInfos(\OC\Files\Filesystem::getDirectoryContent($name)));
	}

	/**
	*	@NoCSRFRequired
	*	@NoAdminRequired
	*/
	public function getTree($name) {
		\OC::$server->getLogger()->debug($this->appName . ' [ getTree() Method ]   [ Parameter : $name = ' . $name .' ]');
		return $this->encodeData($this->formatFileInfos(\OC\Files\Filesystem::getDirectoryContent($name,"httpd/unix-directory")));
	}

	/**
	*	@NoCSRFRequired
	*	@NoAdminRequired
	*/
	public function getShare($path) {
		\OC::$server->getLogger()->debug($this->appName . ' [ getShare() Method ]   [ Parameter : $path = ' . $path .' ]');
		return $this->encodeData($this->formatFileInfo(\OC\Files\Filesystem::getFileInfo($path)));
	}

	/**
	*	@NoCSRFRequired
	*	@NoAdminRequired
	*/
	public function getSearch($query) {
		\OC::$server->getLogger()->debug($this->appName . ' [ getSearch() Method ]   [ Parameter : $query = ' . $query .' ]');
		return $this->encodeData($this->formatFileInfos(\OC\Files\Filesystem::search($query)));
	}

	/**
	*	@NoCSRFRequired
	*	@NoAdminRequired
	*/
	public function getFileExists($file) {
		\OC::$server->getLogger()->debug($this->appName . ' [ getFileExists() Method ]   [ Parameter : $file = ' . $file .' ]');
		$fileNames  = explode(",",$file);
		return $this->encodeData($this->checkFileExists($fileNames));
	}

	/**
	*	@NoCSRFRequired
	*	@NoAdminRequired
	*/
	public function deleteFile($names) {
		\OC::$server->getLogger()->debug($this->appName . ' [ deleteFile() Method ]   [ Parameter : $names = ' . $names .' ]');
		$fileNames  = explode(",",$names);
		foreach($fileNames as $file){
			\OC\Files\Filesystem::unlink($file);
		}
		return $this->encodeData(array("status" => "success"));
	}

	/**
	*	@NoCSRFRequired
	*	@NoAdminRequired
	*/
	public function copyFile($source,$destination) {
		\OC::$server->getLogger()->debug($this->appName . ' [ copyFile() Method ]   [ Parameter : $source = ' . $source . ', $destination = '.$destination.' ]');
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
		\OC::$server->getLogger()->debug($this->appName . ' [ moveFile() Method ]   [ Parameter : $source = ' . $source . ', $destination = '.$destination.' ]');
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
		\OC::$server->getLogger()->debug($this->appName . ' [ renameFile() Method ]   [ Parameter : $oldname = ' . $oldname . ', $newname = '. $newname . ' , $path = '. $path .' ]');
		$path = urldecode($path);
		$oldname = $oldname;
		$newname = $newname;
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
		\OC::$server->getLogger()->debug($this->appName . ' [ createFolder() Method ]   [ Parameter : $path = '. $path .' ]');
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
		\OC::$server->getLogger()->debug($this->appName . ' [ sendMail() Method ]   [ Parameter : $toaddress = ' . $toaddress . ', $type = '. $type . ' , $link = '. $link .' ]');
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
		\OC::$server->getLogger()->debug($this->appName . ' [ encodeData() Method ]');
		return json_decode(json_encode($data, JSON_HEX_TAG));
	}

	/**
	*	@NoCSRFRequired
	*	@NoAdminRequired
	*/
	function formatFileInfo($fileInfo,$shareList = null) {
		\OC::$server->getLogger()->debug($this->appName . ' [ formatFileInfo() Method ]');
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
		$entry['modifydate'] = $this->dateTimeFormatter->formatDate($fileInfo['mtime']);
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
		if($version[0]==9 && $version[1] >= 1){
			$entry['allow_edit_permission'] = 15;
		}else{
			$entry['allow_edit_permission'] = 7;
		}
		return $entry;
	}

	/**
	*	@NoCSRFRequired
	*	@NoAdminRequired
	*/
	function formatFileInfoFilter($fileInfo,$shareList = null,$pathValue) {
		\OC::$server->getLogger()->debug($this->appName . ' [ formatFileInfoFilter() Method ]   [ Parameter : $fileInfo = '. $fileInfo . ' , $shareList = '. $shareList  . ' , $pathValue = ' . $pathValue . ' ]');
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
		$entry['modifydate'] = $this->dateTimeFormatter->formatDate($fileInfo['mtime']);
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
		if($version[0]==9 && $version[1] >= 1){
			$entry['allow_edit_permission'] = 15;
		}else{
			$entry['allow_edit_permission'] = 7;
		}
		return $entry;
	}

	/**
	*	@NoCSRFRequired
	*	@NoAdminRequired
	*/
	public function formatFileInfos($fileInfos) {
		\OC::$server->getLogger()->debug($this->appName . ' [ formatFileInfos() Method ]');
		$shareList = \OCP\Share::getItemsShared("file", \OCP\Share::FORMAT_STATUSES);
		$files = array();
		foreach ($fileInfos as $fileInfo) {
			$files[] = $this->formatFileInfo($fileInfo,$shareList);
		}
		return $files;
	}

	function checkFileExists($files) {
		\OC::$server->getLogger()->debug($this->appName . ' [ checkFileExists() Method ]');
		foreach ($files as $file) {
			if(\OC\Files\Filesystem::file_exists(urldecode($file))) {
				return true;
			}
		}
		return false;
	}

	function copyr($src, $dest){
		\OC::$server->getLogger()->debug($this->appName . ' [ copyr() Method ]   [ Parameter : $src = '. $src . ' , $dest = '. $dest .' ]');
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
		\OC::$server->getLogger()->debug($this->appName . ' [ getShareWithYou() Method ] ');
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
		\OC::$server->getLogger()->debug($this->appName . ' [ getShareWithOthers() Method ] ');
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
		\OC::$server->getLogger()->debug($this->appName . ' [ getFavorites() Method ] ');
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
		\OC::$server->getLogger()->debug($this->appName . ' [ getShareWithLink() Method ] ');
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
