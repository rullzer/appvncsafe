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
use \OCP\Constants;
use \OCP\Files\Cache\ICacheEntry;
use OC\Files\View;
use OC\Files\Filesystem;
use OC\Files\FileInfo;

class ServiceController extends ApiController {

	private $tagservice;
	private $userSession;
	private $dateTimeFormatter;
	protected $appName;
	private static $scannedVersions = false;

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
		return $this->encodeData($this->formatFileInfos(\OC\Files\Filesystem::search(urldecode($query))));
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
		$path = $path;
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
		$favoriteTag = '_$!<Favorite>!$_';
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
		$entry['isFavoriteTag'] = 'false';
		$allTags = [];
		$allTags = $this->tagservice->getIdsByTag($favoriteTag);
		if (in_array($entry['fileid'], $allTags)) {
			$entry['isFavoriteTag'] = 'true';
		}
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
		$entry['server'] = 'nextcloud';
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
		$arr =  \OCP\Share::getItemsSharedWith('file');
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
		return array_unique($dataArray,SORT_REGULAR);
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
	
	/**
	*	@NoCSRFRequired
	*	@NoAdminRequired
	*/
	public function deleteFiles($directory,$files){
		$list[] = $directory;
		$folder = dirname($directory);
		$folder =  str_replace('\\','/',$directory);
		$list = json_decode($files);
		$folder = rtrim($folder, '/') . '/';
		$error = array();
		$success = array();
		$i = 0;
		foreach ($list as $file) {
			if ($folder === '/') {
				$file = ltrim($file, '/');
				$delimiter = strrpos($file, '.d');
				$filename = substr($file, 0, $delimiter);
				$timestamp =  substr($file, $delimiter+2);
			} else {
				$filename = $folder . '/' . $file;
				$timestamp = null;
			}
			self::delete($filename, \OCP\User::getUser(), $timestamp);
		}
	}

	/**
	*	@NoCSRFRequired
	*	@NoAdminRequired
	*/
	public function deleteFromFolder($folder){
		$list[] = $folder;
		$folder = dirname($folder);
		$folder = rtrim($folder, '/') . '/';
		$error = array();
		$success = array();
		$i = 0;
		foreach ($list as $file) {
			if ($folder === '/') {
				$file = ltrim($file, '/');
				$delimiter = strrpos($file, '.d');
				$filename = substr($file, 0, $delimiter);
				$timestamp =  substr($file, $delimiter+2);
			} else {
				$filename = $folder . '/' . $file;
				$timestamp = null;
			}
			self::delete($filename, \OCP\User::getUser(), $timestamp);
		}
	}

	/**
	*	@NoCSRFRequired
	*	@NoAdminRequired
	*/
	public function delete($filename, $user, $timestamp = null) {
		$view = new \OC\Files\View('/' . $user);
		$size = 0;
		$filename = $filename;
		if ($timestamp) {
			$query = \OC_DB::prepare('DELETE FROM `*PREFIX*files_trash` WHERE `user`=? AND `id`=? AND `timestamp`=?');
			$query->execute(array($user, $filename, $timestamp));
			$file = $filename . '.d' . $timestamp;
		} else {
			$file = $filename;
		}
		\OC_Hook::emit('\OCP\Trashbin', 'preDelete', array('path' => '/files_trashbin/files/' . $file));
		$view->unlink('/files_trashbin/files/' . $file);
		\OC_Hook::emit('\OCP\Trashbin', 'delete', array('path' => '/files_trashbin/files/' . $file));
		return $size;
	}

	/**
	*	@NoCSRFRequired
	*	@NoAdminRequired
	*/
	public function setUnsetFavorites($path , $tag) {
		$path = str_replace('\\','/',$path);
		$tagName = '_%24!%3CFavorite%3E!%24_';
		$tagArray = array(urldecode($tagName));
		if($tag == "true"){
			$this->tagservice->updateFileTags($path,$tagArray);
		}else{
			$tagArray = [];
			$this->tagservice->updateFileTags($path,$tagArray);
		}
	}
	/**
	*	@NoCSRFRequired
	*	@NoAdminRequired
	*/
	public function restoreFiles($directory,$files){
		$folder =  str_replace('\\','/',$directory);
		$dir = rtrim($folder, '/'). '/';
		$list = json_decode($files);
		$error = array();
		$success = array();
		$i = 0;
		foreach ($list as $file) {
			$path = $dir . '/' . $file;
			if ($dir === '/') {
				$file = ltrim($file, '/');
				$delimiter = strrpos($file, '.d');
				$filename = substr($file, 0, $delimiter);
				$timestamp =  substr($file, $delimiter+2);
			} else {
				$path_parts = pathinfo($file);
				$filename = $path_parts['basename'];
				$timestamp = null;
			}
			if ( !self::restore($path, $filename, $timestamp) ) {
				$error[] = $filename;
				\OCP\Util::writeLog('trashbin', 'can\'t restore ' . $filename, \OCP\Util::ERROR);
			} else {
				$success[$i]['filename'] = $file;
				$success[$i]['timestamp'] = $timestamp;
				$i++;
			}
		}
	}

	/**
	*	@NoCSRFRequired
	*	@NoAdminRequired
	*/
	public function restoreFromFolder($folder){
		$folder =  str_replace('\\','/',$folder);
		$dir = rtrim($folder, '/'). '/';
		$list = array();
		$dirListing = true;
		if ($dir === '' || $dir === '/') {
			$dirListing = false;
		}
		foreach (self::getTrashFiles($dir, \OCP\User::getUser()) as $file) {
			$fileName = $file['name'];
			if (!$dirListing) {
				$fileName .= '.d' . $file['mtime'];
			}
			$list[] = $fileName;
		}
		$error = array();
		$success = array();
		$i = 0;
		foreach ($list as $file) {
			$path = $dir . '/' . $file;
			if ($dir === '/') {
				$file = ltrim($file, '/');
				$delimiter = strrpos($file, '.d');
				$filename = substr($file, 0, $delimiter);
				$timestamp =  substr($file, $delimiter+2);
			} else {
				$path_parts = pathinfo($file);
				$filename = $path_parts['basename'];
				$timestamp = null;
			}
			if ( !self::restore($path, $filename, $timestamp) ) {
				$error[] = $filename;
				\OCP\Util::writeLog('trashbin', 'can\'t restore ' . $filename, \OCP\Util::ERROR);
			} else {
				$success[$i]['filename'] = $file;
				$success[$i]['timestamp'] = $timestamp;
				$i++;
			}
		}
	}

	public function restore($file, $filename, $timestamp) {
		$user = \OCP\User::getUser();
		$view = new View('/' . $user);
		$location = '';
		if ($timestamp) {
			$location = self::getLocation($user, $filename, $timestamp);
			if ($location === false) {
				\OCP\Util::writeLog('files_trashbin', 'trash bin database inconsistent!', \OCP\Util::ERROR);
			} else {
				if ($location !== '/' &&
					(!$view->is_dir('files/' . $location) ||
					!$view->isCreatable('files/' . $location))
				) {
					$location = '';
				}
			}
		}
		$uniqueFilename = self::getUniqueFilename($location, $filename, $view);
		$source = Filesystem::normalizePath('files_trashbin/files/' . $file);
		$target = Filesystem::normalizePath('files/' . $location . '/' . $uniqueFilename);
		if (!$view->file_exists($source)) {
			return false;
		}
		$mtime = $view->filemtime($source);
		$restoreResult = $view->rename($source, $target);
		if ($restoreResult) {
			$fakeRoot = $view->getRoot();
			$view->chroot('/' . $user . '/files');
			$view->touch('/' . $location . '/' . $uniqueFilename, $mtime);
			$view->chroot($fakeRoot);
			\OCP\Util::emitHook('\OCA\Files_Trashbin\Trashbin', 'post_restore', array('filePath' => Filesystem::normalizePath('/' . $location . '/' . $uniqueFilename),'trashPath' => Filesystem::normalizePath($file)));
			self::restoreVersions($view, $file, $filename, $uniqueFilename, $location, $timestamp);
			if ($timestamp) {
				$query = \OC_DB::prepare('DELETE FROM `*PREFIX*files_trash` WHERE `user`=? AND `id`=? AND `timestamp`=?');
				$query->execute(array($user, $filename, $timestamp));
			}
			return true;
		}
		return false;
	}

	public  function getLocations($user) {
		$query = \OC_DB::prepare('SELECT `id`, `timestamp`, `location`' . ' FROM `*PREFIX*files_trash` WHERE `user`=?');
		$result = $query->execute(array($user));
		$array = array();
		while ($row = $result->fetchRow()) {
			if (isset($array[$row['id']])) {
				$array[$row['id']][$row['timestamp']] = $row['location'];
			} else {
				$array[$row['id']] = array($row['timestamp'] => $row['location']);
			}
		}
		return $array;
	}


	public function getUniqueFilename($location, $filename, View $view) {
		$ext = pathinfo($filename, PATHINFO_EXTENSION);
		$name = pathinfo($filename, PATHINFO_FILENAME);
		$l = \OC::$server->getL10N('files_trashbin');
		$location = '/' . trim($location, '/');
		if ($ext !== '') {
			$ext = '.' . $ext;
		}
		if ($view->file_exists('files' . $location . '/' . $filename)) {
			$i = 2;
			$uniqueName = $name . " (" . $l->t("restored") . ")" . $ext;
			while ($view->file_exists('files' . $location . '/' . $uniqueName)) {
				$uniqueName = $name . " (" . $l->t("restored") . " " . $i . ")" . $ext;
				$i++;
			}
			return $uniqueName;
		}
		return $filename;
	}


	public function getLocation($user, $filename, $timestamp) {
		$query = \OC_DB::prepare('SELECT `location` FROM `*PREFIX*files_trash`'. ' WHERE `user`=? AND `id`=? AND `timestamp`=?');
		$result = $query->execute(array($user, $filename, $timestamp))->fetchAll();
		if (isset($result[0]['location'])) {
			return $result[0]['location'];
		} else {
			return false;
		}
	}

	public function restoreVersions(View $view, $file, $filename, $uniqueFilename, $location, $timestamp) {
		if (\OCP\App::isEnabled('files_versions')) {
			$user = \OCP\User::getUser();
			$rootView = new \OC\Files\View('/');
			$target = Filesystem::normalizePath('/' . $location . '/' . $uniqueFilename);
			list($owner, $ownerPath) = self::getUidAndFilename($target);
			if (empty($ownerPath)) {
				return false;
			}
			if ($timestamp) {
				$versionedFile = $filename;
			} else {
				$versionedFile = $file;
			}
			if ($view->is_dir('/files_trashbin/versions/' . $file)) {
				$rootView->rename(Filesystem::normalizePath($user . '/files_trashbin/versions/' . $file), Filesystem::normalizePath($owner . '/files_versions/' . $ownerPath));
			} else if ($versions = self::getVersionsFromTrash($versionedFile, $timestamp, $user)) {
				foreach ($versions as $v) {
					if ($timestamp) {
						$rootView->rename($user . '/files_trashbin/versions/' . $versionedFile . '.v' . $v . '.d' . $timestamp, $owner . '/files_versions/' . $ownerPath . '.v' . $v);
					} else {
						$rootView->rename($user . '/files_trashbin/versions/' . $versionedFile . '.v' . $v, $owner . '/files_versions/' . $ownerPath . '.v' . $v);
					}
				}
			}
		}
	}

	public  function getUidAndFilename($filename) {
		$uid = Filesystem::getOwner($filename);
		$userManager = \OC::$server->getUserManager();
		if (!$userManager->userExists($uid)) {
			$uid = \OCP\User::getUser();
		}
		if (!$uid) {
			return [null, null];
		}
		Filesystem::initMountPoints($uid);
		if ($uid != \OCP\User::getUser()) {
			$info = Filesystem::getFileInfo($filename);
			$ownerView = new \OC\Files\View('/' . $uid . '/files');
			try {
				$filename = $ownerView->getPath($info['fileid']);
			} catch (NotFoundException $e) {
				$filename = null;
			}
		}
		return [$uid, $filename];
	}

	public  function getVersionsFromTrash($filename, $timestamp, $user) {
		$view = new \OC\Files\View('/' . $user . '/files_trashbin/versions');
		$versions = array();
		if (!self::$scannedVersions) {
			list($storage,) = $view->resolvePath('/');
			$storage->getScanner()->scan('files_trashbin/versions');
			self::$scannedVersions = true;
		}
		if ($timestamp) {
			$matches = $view->searchRaw($filename . '.v%.d' . $timestamp);
			$offset = -strlen($timestamp) - 2;
		} else {
			$matches = $view->searchRaw($filename . '.v%');
		}
		if (is_array($matches)) {
			foreach ($matches as $ma) {
				if ($timestamp) {
					$parts = explode('.v', substr($ma['path'], 0, $offset));
					$versions[] = (end($parts));
				} else {
					$parts = explode('.v', $ma);
					$versions[] = (end($parts));
				}
			}
		}
		return $versions;
	}

	public function getTrashFiles($dir, $user, $sortAttribute = '', $sortDescending = false) {
		$result = array();
		$timestamp = null;

		$version = \OCP\Util::getVersion();
		if($version[0]==8){
			$view = new \OC\Files\View('/' . $user . '/files_trashbin/files');

			if (ltrim($dir, '/') !== '' && !$view->is_dir($dir)) {
				throw new \Exception('Directory does not exists');
			}

			$dirContent = $view->opendir($dir);
			if ($dirContent === false) {
				return $result;
			}

			$mount = $view->getMount($dir);
			$storage = $mount->getStorage();
			$absoluteDir = $view->getAbsolutePath($dir);
			$internalPath = $mount->getInternalPath($absoluteDir);

			if (is_resource($dirContent)) {
				$originalLocations = self::getLocations($user);
				while (($entryName = readdir($dirContent)) !== false) {
					if (!\OC\Files\Filesystem::isIgnoredDir($entryName)) {
						$id = $entryName;
						if ($dir === '' || $dir === '/') {
							$size = $view->filesize($id);
							$pathparts = pathinfo($entryName);
							$timestamp = substr($pathparts['extension'], 1);
							$id = $pathparts['filename'];

						} else if ($timestamp === null) {
							$size = $view->filesize($dir . '/' . $id);
							$parts = explode('/', ltrim($dir, '/'));
							$timestamp = substr(pathinfo($parts[0], PATHINFO_EXTENSION), 1);
						}
						$originalPath = '';
						if (isset($originalLocations[$id][$timestamp])) {
							$originalPath = $originalLocations[$id][$timestamp];
							if (substr($originalPath, -1) === '/') {
								$originalPath = substr($originalPath, 0, -1);
							}
						}
						$i = array(
							'name' => $id,
							'mtime' => $timestamp,
							'mimetype' => $view->is_dir($dir . '/' . $entryName) ? 'httpd/unix-directory' : \OC_Helper::getFileNameMimeType($id),
							'type' => $view->is_dir($dir . '/' . $entryName) ? 'dir' : 'file',
							'directory' => ($dir === '/') ? '' : $dir,
							'size' => $size,
						);
						if ($originalPath) {
							$i['extraData'] = $originalPath.'/'.$id;
						}
						$result[] = new FileInfo($absoluteDir . '/' . $i['name'], $storage, $internalPath . '/' . $i['name'], $i, $mount);
					}
				}
				closedir($dirContent);
			}
			if ($sortAttribute !== '') {
				return \OCA\Files\Helper::sortFiles($result, $sortAttribute, $sortDescending);
			}
			return $result;
		}else{
			$view = new \OC\Files\View('/' . $user . '/files_trashbin/files');

			if (ltrim($dir, '/') !== '' && !$view->is_dir($dir)) {
				throw new \Exception('Directory does not exists');
			}
			$mount = $view->getMount($dir);
			$storage = $mount->getStorage();
			$absoluteDir = $view->getAbsolutePath($dir);
			$internalPath = $mount->getInternalPath($absoluteDir);

			$originalLocations = \OCA\Files_Trashbin\Trashbin::getLocations($user);
			$dirContent = $storage->getCache()->getFolderContents($mount->getInternalPath($view->getAbsolutePath($dir)));
			foreach ($dirContent as $entry) {
				$entryName = $entry->getName();
				$id = $entry->getId();
				$name = $entryName;
				if ($dir === '' || $dir === '/') {
					$pathparts = pathinfo($entryName);
					$timestamp = substr($pathparts['extension'], 1);
					$name = $pathparts['filename'];
				} else if ($timestamp === null) {
					$parts = explode('/', ltrim($dir, '/'));
					$timestamp = substr(pathinfo($parts[0], PATHINFO_EXTENSION), 1);
				}
				$originalPath = '';
				if (isset($originalLocations[$id][$timestamp])) {
					$originalPath = $originalLocations[$id][$timestamp];
					if (substr($originalPath, -1) === '/') {
						$originalPath = substr($originalPath, 0, -1);
					}
				}
				$i = array(
					'name' => $name,
					'mtime' => $timestamp,
					'mimetype' => $entry->getMimeType(),
					'type' => $entry->getMimeType() === ICacheEntry::DIRECTORY_MIMETYPE ? 'dir' : 'file',
					'directory' => ($dir === '/') ? '' : $dir,
					'size' => $entry->getSize(),
					'etag' => '',
					'permissions' => Constants::PERMISSION_ALL - Constants::PERMISSION_SHARE
				);
				if ($originalPath) {
					$i['extraData'] = $originalPath . '/' . $id;
				}
				$result[] = new FileInfo($absoluteDir . '/' . $i['name'], $storage, $internalPath . '/' . $i['name'], $i, $mount);
			}
			if ($sortAttribute !== '') {
				return \OCA\Files\Helper::sortFiles($result, $sortAttribute, $sortDescending);
			}
			return $result;
		}
	}


	/**Get deleted files list*/
	/**
	*	@NoCSRFRequired
	*	@NoAdminRequired
	*/
	public function getDeletedFileList($dir){
		\OC::$server->getLogger()->debug($this->appName . ' [ getDeletedFileList() Method ]   [ Parameter : $dir = ' . $dir .' ]');
		$dir = str_replace('\\','/',$dir);
		try {
			$files = self::getTrashFiles($dir, \OCP\User::getUser(), 'mtime', false);
		} catch (Exception $e) {
		}
		$encodedDir = \OCP\Util::encodePath($dir);
		$data['permissions'] = 0;
		$data['directory'] = $dir;
		$data['files'] = self::formatFileInformation($files);
		return $this->encodeData(array('data' => $data));
	}

	/**
	*	@NoCSRFRequired
	*	@NoAdminRequired
	*/
	public function formatFileInformation($fileInfos) {
		$files = array();
		$id = 0;
		foreach ($fileInfos as $i) {
			$entry = \OCA\Files\Helper::formatFileInfo($i);
			$entry['id'] = $id++;
			$entry['etag'] = $entry['mtime'];
			$entry['permissions'] = \OCP\Constants::PERMISSION_READ;
			$files[] = $entry;
		}
		return $files;
	}

	/**
	*       @NoCSRFRequired
	*       @NoAdminRequired
	*/
	public function getOwncloudInstanceId() {
		$instanceId = \OC::$server->getSystemConfig()->getValue('instanceid', null);
		return str_replace('"', '', $instanceId);
	}
}
