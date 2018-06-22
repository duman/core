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
namespace OCA\DAV\JobStatus;

use OCA\DAV\DAV\LazyOpsPlugin;
use Sabre\DAV\File;

class JobStatus extends File {

	/** @var string */
	private $jobId;
	/** @var string */
	private $userId;
	/** @var string */
	private $data;

	public function __construct($userId, $jobId, $data) {
		$this->userId = $userId;
		$this->jobId = $jobId;
		$this->data = $data;
	}

	/**
	 * Returns the name of the node.
	 *
	 * This is used to generate the url.
	 *
	 * @return string
	 */
	public function getName() {
		return $this->jobId;
	}

	public function get() {
		if ($this->data !== null) {
			return $this->data;
		}
		return LazyOpsPlugin::getQueueInfo($this->userId, $this->jobId);
	}

	public function getETag() {
		return '"' . \sha1($this->get()) . '"';
	}

	public function getSize() {
		return \strlen($this->get());
	}

	public function refreshStatus() {
		$this->data = null;
	}
}
