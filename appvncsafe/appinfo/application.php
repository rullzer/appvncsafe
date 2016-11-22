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

use \OCP\AppFramework\App;
use \OCA\Appvncsafe\Controller\ServiceController;
use \OCA\Appvncsafe\Service\TagService;
use \OCP\IContainer;


class Application extends App {
    public function __construct(array $urlParams=array()){
        parent::__construct('appvncsafe', $urlParams);

        $container = $this->getContainer();
	$server = $container->getServer();

        /**
         * Controllers
         */
        $container->registerService('ServiceController', function($c) use ($server) {
            return new ServiceController(
                $c->query('ServerContainer'),
                $c->query('Request'),
		$c->query('TagService'),
		$server->getUserSession(),
		\OC::$server->getDateTimeFormatter(),
		'[appvncsafe]'
            );
        });

	/**
		* Services
	*/
		$container->registerService('Tagger', function($c)  {
			return $c->query('ServerContainer')->getTagManager()->load('files');
		});
		$container->registerService('TagService', function($c)  {
			$homeFolder = $c->query('ServerContainer')->getUserFolder();
			return new TagService(
				$c->query('ServerContainer')->getUserSession(),
				$c->query('Tagger'),
				$homeFolder
			);
		});
	}

}

