<?php
namespace Plugin\JsysAsi\Repository;

use Doctrine\Persistence\ManagerRegistry as RegistryInterface;
use Eccube\Repository\AbstractRepository;
use Plugin\JsysAsi\Entity\JsysAsiLoginHistoryLockStatus;

/**
 * ログイン履歴ロックステータスマスタRepository
 * @author manabe
 *
 */
class JsysAsiLoginHistoryLockStatusRepository extends AbstractRepository
{
    /**
     * JsysAsiLoginHistoryLockStatusRepository constructor.
     * @param RegistryInterface $registry
     */
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, JsysAsiLoginHistoryLockStatus::class);
    }

}
