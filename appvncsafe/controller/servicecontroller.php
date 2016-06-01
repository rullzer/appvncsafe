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
use \OCP\Share\IManager;
use \OCP\IUserSession;
use \OCP\Files\Node;

class ServiceController extends ApiController {

	private $tagservice;
	private $userSession;
	private $shareManager;

	public function __construct($appName, IRequest $request,$tagservice,IUserSession $userSession,IManager $shareManager) {
		parent::__construct(
			$appName,
			$request,
			'PUT, POST, GET, DELETE, PATCH',
			'Authorization, Content-Type, Accept'
		);
		$this->tagservice = $tagservice;
                $this->userSession = $userSession;
                $this->shareManager = $shareManager;
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

	/**
	*	@NoCSRFRequired
	*/
	public function getShareWithYou() {
		$arr =  \OCP\Share::getItemsSharedWith('file');
		$dataArray = array();
		$version = \OCP\Util::getVersion();
		foreach ($arr as $value) {
			$type = '';
			$entry = array();
			$mimetype = \OC::$server->getMimeTypeDetector()->detectPath(substr($value['file_target'],1));
			$mimetypeDetector = \OC::$server->getMimeTypeDetector();
			$mimeTypeIcon = $mimetypeDetector->mimeTypeIcon($mimetype);
			if($value['item_type']=='folder'){
				$type = 'httpd/unix-directory';
				$entry['mimetype'] = $type;
				$entry['type'] = $type;
			}else{
				$type = '';
				$entry['mimetype'] = $mimetype;
				$entry['type'] = $mimetype;
			}
			$entry['mountType'] = 'shared-root';
			$entry['shareOwner'] = $value['displayname_owner'];
			$entry['fileid'] = $value['id'];
			$entry['parent'] = $value['parent'];
			$entry['modifydate'] = '';
			$entry['mtime'] = $value['stime'];
			$entry['icon'] = $mimeTypeIcon;
			$entry['name'] = substr($value['file_target'],1);
			$entry['permissions'] = $value['permissions'];
			$entry['size'] = '';
			$entry['etag'] = '';
			$entry['path'] = $value['file_target'];
			$entry['url'] = str_replace("%2F", "/",rawurlencode($value['path']));
			$entry['version'] = $version[0];
			$dataArray [] = $entry;
		}
		return $dataArray;
	}

	/**
	*	@NoCSRFRequired
	*/
	public function getShareWithOthers() {
		$arr =  \OCP\Share::getItemShared('file', null);
		$dataArray = array();
		$version = \OCP\Util::getVersion();
		foreach ($arr as $value) {
			$type = '';
			$entry = array();
			$mimetype = \OC::$server->getMimeTypeDetector()->detectPath(substr($value['file_target'],1));
			$mimetypeDetector = \OC::$server->getMimeTypeDetector();
			$mimeTypeIcon = $mimetypeDetector->mimeTypeIcon($mimetype);
			if($value['item_type']=='folder'){
				$type = 'httpd/unix-directory';
				$entry['mimetype'] = $type;
				$entry['type'] = $type;
			}else{
				$type = '';
				$entry['mimetype'] = $mimetype;
				$entry['type'] = $mimetype;
			}
			$entry['mountType'] = 'shared-root';
			$entry['shareOwner'] = $value['displayname_owner'];
			$entry['fileid'] = $value['id'];
			$entry['parent'] = $value['parent'];
			$entry['modifydate'] = '';
			$entry['mtime'] = $value['stime'];
			$entry['icon'] = $mimeTypeIcon;
			$entry['name'] = substr($value['file_target'],1);
			$entry['permissions'] = $value['permissions'];
			$entry['size'] = '';
			$entry['etag'] = '';
			$entry['path'] = $value['file_target'];
			$entry['url'] = str_replace("%2F", "/",rawurlencode($value['path']));
			$entry['version'] = $version[0];
			$dataArray [] = $entry;
		}
		return $dataArray;
	}

	/**
	*	@NoCSRFRequired
	*/
	public function getFavorites(){
		$version = \OCP\Util::getVersion();
		$names = "_%24!%3CFavorite%3E!%24_";
		$nodes = $this->tagservice->getFilesByTag(urldecode($names));
		$files = array();
		$dataArray = array();
		foreach ($nodes as &$node) {
			$shareTypes = $this->getShareTypes($node);
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
			$files[] = $file;
		}
		foreach($files as $value ){
			$type = '';
			$entry = array();
			$mimetypeDetector = \OC::$server->getMimeTypeDetector();
			$mimeTypeIcon = $mimetypeDetector->mimeTypeIcon($mimetype);
			$entry['fileid'] = $value['id'];
			$entry['mountType'] = 'shared-root';
			$entry['shareOwner'] = '';
			$entry['parent'] = $value['parentId'];
			$entry['modifydate'] = '';
			$entry['mtime'] = $value['mtime'];
			$entry['icon'] = $mimeTypeIcon;
			$entry['name'] = $value['name'];
			$entry['permissions'] = $value['permissions'];
			$entry['size'] = $value['size'];
			$entry['etag'] = $value['tags'];
			$entry['mimetype'] = $value['mimetype'];
			$entry['type'] = $value['mimetype'];
			$entry['path'] = $value['path'].$value['name'];
			$entry['url'] = str_replace("%2F", "/",rawurlencode($value['path'].$value['name']));
			$entry['version'] = $version[0];
			$dataArray [] = $entry;
		}
		return $this->encodeData($dataArray);
	}

	public function getShareTypes(Node $node) {
		$userId = $this->userSession->getUser()->getUID();
		$shareTypes = [];
		$requestedShareTypes = [
			\OCP\Share::SHARE_TYPE_USER,
			\OCP\Share::SHARE_TYPE_GROUP,
			\OCP\Share::SHARE_TYPE_LINK,
			\OCP\Share::SHARE_TYPE_REMOTE
		];
		foreach ($requestedShareTypes as $requestedShareType) {
			// one of each type is enough to find out about the types
			$shares = $this->shareManager->getSharesBy(
				$userId,
				$requestedShareType,
				$node,
				false,
				1
			);
			if (!empty($shares)) {
				$shareTypes[] = $requestedShareType;
			}
		}
		return $shareTypes;
	}
}
