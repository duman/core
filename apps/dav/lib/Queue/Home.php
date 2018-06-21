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
namespace OCA\DAV\Queue;

use OCA\DAV\DAV\LazyOpsPlugin;
use Sabre\DAV\Collection;
use Sabre\DAV\Exception\Forbidden;
use Sabre\DAV\Exception\MethodNotAllowed;
use Sabre\DAV\Exception\NotFound;
use Sabre\DAV\SimpleFile;
use Sabre\HTTP\URLUtil;

class Home extends Collection {
	private $principalInfo;

	/**
	 * UploadHome constructor.
	 *
	 * @param array $principalInfo
	 */
	public function __construct($principalInfo) {
		$this->principalInfo = $principalInfo;
	}

	public function getChild($name) {
		$data = LazyOpsPlugin::getQueueInfo($this->getName(), $name);
		if ($data === null) {
			throw new NotFound();
		}
		// TODO: switch to own implementation so that we can delete a job
		return new SimpleFile($name, $data, 'application/json');
	}

	public function getChildren() {
		throw new MethodNotAllowed('Listing members of this collection is disabled');
	}

	public function getName() {
		list(, $name) = URLUtil::splitPath($this->principalInfo['uri']);
		return $name;
	}
}
