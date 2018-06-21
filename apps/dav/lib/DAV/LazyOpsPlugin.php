<?php
/**
 * @author Thomas Müller <thomas.mueller@tmit.eu>
 *
 * @copyright Copyright (c) 2018, ownCloud GmbH
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program.  If not, see <http://www.gnu.org/licenses/>
 *
 */

namespace OCA\DAV\DAV;

use OCA\DAV\Connector\Sabre\Exception\Forbidden;
use OCA\DAV\Connector\Sabre\File;
use OCA\DAV\Files\ICopySource;
use OCP\Files\ForbiddenException;
use OCP\Lock\ILockingProvider;
use Sabre\DAV\Exception;
use Sabre\DAV\IFile;
use Sabre\DAV\Server;
use Sabre\DAV\ServerPlugin;
use Sabre\DAV\UUIDUtil;
use Sabre\HTTP\RequestInterface;
use Sabre\HTTP\Response;
use Sabre\HTTP\ResponseInterface;

/**
 * Class LazyOpsPlugin
 *
 * @package OCA\DAV\DAV
 */
class LazyOpsPlugin extends ServerPlugin {

	/** @var Server */
	private $server;
	/** @var string */
	private $jobId;

	public static function getQueueInfo(string $userId, string $jobId) {
		return \OC::$server->getConfig()->getUserValue($userId, 'dav', "lazy-ops-job.$jobId", null);
	}

	/**
	 * @param Server $server
	 */
	public function initialize(Server $server) {
		$this->server = $server;
		$server->on('method:MOVE', [$this, 'httpMove'], 90);
//		$server->on('afterResponse', [$this, 'afterResponse']);
	}

	public function httpMove(RequestInterface $request, ResponseInterface $response) {
		if (!$request->getHeader('OC-LazyOps')) {
			return true;
		}

		$this->jobId = UUIDUtil::getUUID();
		$this->setJobStatus([
			'status' => 'init'
		]);
		$userId = \OC::$server->getUserSession()->getUser()->getUID();
		// TODO: url decode ?
		$location = \OC::$server->getURLGenerator()->linkTo('', 'remote.php') . "/dav/{$userId}/queue/{$this->jobId}";

		$response->setStatus(202);
		$response->addHeader('Connection', 'close');
		$response->addHeader('OC-Location', $location);

		\register_shutdown_function(function () use ($request, $response) {
			return $this->afterResponse($request, $response);
		});

		return false;
	}

	public function afterResponse(RequestInterface $request, ResponseInterface $response) {
		if (!$request->getHeader('OC-LazyOps')) {
			return true;
		}

		\flush();
		$request->removeHeader('OC-LazyOps');
		$responseDummy = new Response();
		try {
			$this->setJobStatus([
				'status' => 'started'
			]);
			$this->server->emit('method:MOVE', [$request, $responseDummy]);

			$this->setJobStatus([
				'status' => 'finished',
				'fileId' => $response->getHeader('OC-FileId'),
				'ETag' => $response->getHeader('ETag')
			]);
		} catch (\Exception $ex) {
			\OC::$server->getLogger()->logException($ex);

			$this->setJobStatus([
				'status' => 'error',
				'errorCode' => 500,
				'errorMessage' => $ex->getMessage()
			]);
		} finally {
			// TODO: fix shutdown function execution order
			\OC::$server->getLockingProvider()->releaseAll();
		}
	}

	private function setJobStatus(array $status) {
		//
		// TODO: store in a true database table - POC uses user config
		//
		$userId = \OC::$server->getUserSession()->getUser()->getUID();
		\OC::$server->getConfig()->setUserValue($userId, 'dav', "lazy-ops-job.{$this->jobId}", \json_encode($status));
	}
}
