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
namespace OCA\Appvncsafe\AppInfo;

$application = new Application();
$application->registerRoutes($this, array(
	'routes' => array(
		array(
			'name' => 'service#getTree',
			'url' => '/tree/{name}',
			'verb' => 'GET','requirements' => array('name' => '.+/')
		),
		array(
			'name' => 'service#getList',
			'url' => '/list/{name}',
			'verb' => 'GET',
			'requirements' => array('name' => '.+/')
		),
		array(
			'name' => 'service#deleteFile',
			'url' => '/delete/{names}',
			'verb' => 'POST',
			'requirements' => array('names' => '.+/')
		),
		array(
			'name' => 'service#getShare',
			'url' => '/getshare/{path}',
			'verb' => 'GET',
			'requirements' => array('path' => '.+/')
		),
		array(
			'name' => 'service#getSearch',
			'url' => '/search/{query}',
			'verb' => 'GET',
			'requirements' => array('query' => '.+')
		),
		array(
			'name' => 'service#getOwncloudInstanceId',
			'url' => '/getowncloudinstanceid',
			'verb' => 'GET'
		),
		array(
			'name' => 'service#getFileExists',
			'url' => '/fileexists/{file}',
			'verb' => 'POST',
			'requirements' => array('file' => '.+/')
		),
		array(
			'name' => 'service#copyFile',
			'url' => '/copyfile/{source}/{destination}',
			'verb' => 'POST',
			'requirements' => array('source' => '.+')
		),
		array(
			'name' => 'service#moveFile',
			'url' => '/movefile/{source}/{destination}',
			'verb' => 'POST','requirements' => array('source' => '.+')
		),
		array(
			'name' => 'service#renameFile',
			'url' => '/renamefile/{oldname}/{newname}/{path}',
			'verb' => 'POST','requirements' => array('path' => '.+')
		),
		array(
			'name' => 'service#createFolder',
			'url' => '/createfolder/{path}',
			'verb' => 'GET','requirements' => array('path' => '.+/')
		),
		array(
			'name' => 'service#sendMail',
			'url' => '/sendmail/{toaddress}/{type}/{link}',
			'verb' => 'POST','requirements' => array('link' => '.+/')
		),
		array(
			'name' => 'service#getShareWithYou',
			'url' => '/getsharewithme',
			'verb' => 'GET'
		),
		array(
			'name' => 'service#getShareWithOthers',
			'url' => '/getsharewithothers',
			'verb' => 'GET'
		),
		array(
			'name' => 'service#getFavorites',
			'url' => '/getfavorites',
			'verb' => 'GET'
		),
		array(
			'name' => 'service#getShareWithLink',
			'url' => '/getsharewithlink',
			'verb' => 'GET'
		),
		array(
			'name' => 'service#deleteFiles',
			'url' => '/deletefiles/{directory}/{files}',
			'verb' => 'POST','requirements' => array('path' => '.+/')
		),
		array(
			'name' => 'service#deleteFromFolder',
			'url' => '/deletefromfolder/{folder}',
			'verb' => 'POST'
		),
		array(
			'name' => 'service#setUnsetFavorites',
			'url' => '/tagfavorite/{path}/{tag}',
			'verb' => 'POST'
		),
		array(
			'name' => 'service#restoreFiles',
			'url' => '/restorefiles/{directory}/{files}',
			'verb' => 'POST','requirements' => array('path' => '.+/')
		),
		array(
			'name' => 'service#restoreFromFolder',
			'url' => '/restorefromfolder/{folder}',
			'verb' => 'POST'
		),
		array(
			'name' => 'service#getDeletedFileList',
			'url' => '/getdeletedfilelist/{dir}',
			'verb' => 'POST'
		)
	)
));
