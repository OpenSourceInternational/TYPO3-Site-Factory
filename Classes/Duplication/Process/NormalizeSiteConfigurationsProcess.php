<?php

namespace Romm\SiteFactory\Duplication\Process;

use Romm\SiteFactory\Duplication\AbstractDuplicationProcess;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class NormalizeSiteConfigurationsProcess
 * @package Romm\SiteFactory\Duplication\Process
 */
class NormalizeSiteConfigurationsProcess extends AbstractDuplicationProcess
{
    /**
     * Set pid = 0 for all sites configurations
     */
    public function run()
    {
        $connection = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getConnectionForTable('tx_sitefactory_domain_model_save');
        $query = $connection->createQueryBuilder();
        $query->getRestrictions()->removeAll();
        $query->update('tx_sitefactory_domain_model_save')
            ->set('pid',  0)
            ->execute();
    }
}