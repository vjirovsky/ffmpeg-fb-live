<?php

namespace App;

use Nette;
use Nette\Application\Routers\RouteList;
use Nette\Application\Routers\Route;


class RouterFactory
{

	/**
	 * @return Nette\Application\IRouter
	 */
	public static function createRouter()
	{
		$router = new RouteList;
		$router[] = new Route('js-login[/<id>]', 'Homepage:jsLogin');
		$router[] = new Route('generate-new-token[/<id>]', 'Homepage:generateNewTokenForFbId');
		$router[] = new Route('list-accounts[/<id>]', 'Homepage:listAccounts');
		$router[] = new Route('list-resources[/<id>]', 'Homepage:listResourcesToBroadcast');
		$router[] = new Route('<presenter>/<action>[/<id>]', 'Homepage:default');
		return $router;
	}

}
