<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2016 Bjoern Schiessle <bjoern@schiessle.org>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */


namespace OCA\ServerInfo;

use OCP\IConfig;
use OCP\IDBConnection;
use Psr\Log\LoggerInterface;
use OC\Files\ObjectStore\S3;

class StorageStatistics {
	private IDBConnection $connection;
	private IConfig $config;

	public function __construct(IDBConnection $connection, IConfig $config) {
		$this->connection = $connection;
		$this->config = $config;
	}

	public function getStorageStatistics(): array {
		return [
			'num_users' => $this->countUserEntries(),
			'num_files' => $this->getCountOf('filecache'),
			'num_storages' => $this->getCountOf('storages'),
			'num_storages_local' => $this->countStorages('local'),
			'num_storages_home' => $this->countStorages('home'),
			'num_storages_other' => $this->countStorages('other'),
		];
	}

	/**
	 * count number of users
	 */
	protected function countUserEntries(): int {
		$query = $this->connection->getQueryBuilder();
		$query->selectAlias($query->createFunction('COUNT(*)'), 'num_entries')
			->from('preferences')
			->where($query->expr()->eq('configkey', $query->createNamedParameter('lastLogin')));
		$result = $query->executeQuery();
		$row = $result->fetch();
		$result->closeCursor();
		return (int) $row['num_entries'];
	}

	protected function getCountOf(string $table): int {
		return (int)$this->config->getAppValue('serverinfo', 'cached_count_' . $table, '0');
	}

	public function updateStorageCounts(): void {
		$storageCount = 0;
		$fileCount = 0;

		$fileQuery = $this->connection->getQueryBuilder();
		$fileQuery->select($fileQuery->func()->count())
			->from('filecache')
			->where($fileQuery->expr()->eq('storage', $fileQuery->createParameter('storageId')));

		$storageQuery = $this->connection->getQueryBuilder();
		$storageQuery->selectAlias('numeric_id', 'id')
			->from('storages');
		$storageResult = $storageQuery->executeQuery();
		while ($storageRow = $storageResult->fetch()) {
			$storageCount++;
			$fileQuery->setParameter('storageId', $storageRow['id']);
			$fileResult = $fileQuery->executeQuery();
			$fileCount += (int)$fileResult->fetchOne();
			$fileResult->closeCursor();
		}
		$storageResult->closeCursor();

		$this->config->setAppValue('serverinfo', 'cached_count_filecache', (string)$fileCount);
		$this->config->setAppValue('serverinfo', 'cached_count_storages', (string)$storageCount);
	}

	protected function countStorages(string $type): int {
		$query = $this->connection->getQueryBuilder();
		$query->selectAlias($query->createFunction('COUNT(*)'), 'num_entries')
			->from('storages');
		if ($type === 'home') {
			$query->where($query->expr()->like('id', $query->createNamedParameter('home::%')));
		} elseif ($type === 'local') {
			$query->where($query->expr()->like('id', $query->createNamedParameter('local::%')));
		} elseif ($type === 'other') {
			$query->where($query->expr()->notLike('id', $query->createNamedParameter('home::%')));
			$query->andWhere($query->expr()->notLike('id', $query->createNamedParameter('local::%')));
		}
		$result = $query->executeQuery();
		$row = $result->fetch();
		$result->closeCursor();
		return (int) $row['num_entries'];
	}

    public function getS3info():array
    {
        $values = ['s3' => ['code' => 0, 'messages' => [], 'status' => 'Unhealthy']];

        try {
            $systemConfig = \OC::$server->getSystemConfig();

            $objectStoreConfig = $systemConfig->getValue('objectstore', null);

            if ($objectStoreConfig === null) {
                $values['s3']['messages'][] = 'Primary object store is not configured.';

                return $values;
            }

            if ($objectStoreConfig['class'] !== '\\OC\\Files\\ObjectStore\\S3') {
                $values['s3']['messages'][] = 'Primary storage is not configured as S3.';

                return $values;
            }

            $s3              = new S3($objectStoreConfig['arguments']);
            $testFileName    = 'test-file.txt';
            $testFileContent = 'This is a test file to check S3 upload functionality.';

            $s3->writeObject($testFileName, $testFileContent);

            if ($s3->objectExists($testFileName)) {
                $s3->deleteObject($testFileName);
                $values['s3'] = [
                    'code'     => 1,
                    'status'   => 'Healthy',
                    'messages' => ['S3 is configured and accessible. Test file uploaded successfully.']
                ];
            } else {
                $values['s3']['messages'][] = 'S3 is configured but the test file is not accessible.';
            }

        } catch (\Exception $e) {
            \OCP\Server::get(LoggerInterface::class)->error("Error checking S3 status: " . $e->getMessage(),
                ['app' => 'serverinfo', 'exception' => $e]);
            $values['s3']['messages'][] = 'Error checking S3 status: '.$e->getMessage();
        }

        return $values;
    }

}
