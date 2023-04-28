<?php

/*
 * This file is part of EC-CUBE
 *
 * Copyright(c) EC-CUBE CO.,LTD. All Rights Reserved.
 *
 * http://www.ec-cube.co.jp/
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Eccube\Repository;

use Doctrine\Persistence\ManagerRegistry as RegistryInterface;
use Eccube\Entity\Plugin;

/**
 * PluginRepository
 *
 * This class was generated by the Doctrine ORM. Add your own custom
 * repository methods below.
 */
class PluginRepository extends AbstractRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, Plugin::class);
    }

    public function findAllEnabled()
    {
        return $this->findBy(['enabled' => '1']);
    }

    /**
     * プラグインコードから, プラグインを検索する.
     *
     * このメソッドは、プラグインコードをすべて小文字に正規化してから検索します.
     *
     * @param string $code プラグインコード
     *
     * @return Plugin
     */
    public function findByCode($code)
    {
        $qb = $this->createQueryBuilder('p')
            ->where('LOWER(p.code) = :code')
            ->setParameter('code', strtolower($code));

        return $qb->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
