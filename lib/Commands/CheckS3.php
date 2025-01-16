<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2020 Arthur Schiwon <blizzz@arthur-schiwon.de>
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
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

namespace OCA\ServerInfo\Commands;

use OC\Core\Command\Base;
use OCA\ServerInfo\StorageStatistics;
use OCP\DB\Exception;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CheckS3 extends Base {
    private StorageStatistics $storageStatistics;

    public function __construct(StorageStatistics $storageStatistics) {
        parent::__construct();

        $this->storageStatistics = $storageStatistics;
    }

    public function configure(): void {
        parent::configure();
        $this->setName('serverinfo:checkS3')
             ->setDescription('Description');
    }

    public function execute(InputInterface $input, OutputInterface $output): int {
        
        $this->writeArrayInOutputFormat($input, $output, $res = $this->storageStatistics->getS3info());   
        
        if ($res['s3']['code'] === 0) {
            return 1;
        }
        
        return 0;
    }
}
