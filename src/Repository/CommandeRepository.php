<?php

namespace App\Repository;

use App\Entity\Commande;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Commande>
 */
class CommandeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Commande::class);
    }

    /*commande/mois pr stats admin, use par AdminController::stats() pr Chart.js
     * @return array Ex: [['mois' => '2026-01', 'nb' => 12], ...]*/
    public function findCommandesParMois(): array
    {
        return $this->createQueryBuilder('c')
           ->select(
             "SUBSTRING(CAST(c.dateCommande AS string), 1, 7) AS mois",
             'COUNT(c.id) AS nb'
           )
           ->groupBy('mois')
           ->orderBy('mois', 'ASC')
           ->getQuery()
           ->getResult();
    }
}
