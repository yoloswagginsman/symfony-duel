<?php

namespace App\Repository;

use App\Entity\ApiToken;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends AbstractRepository<ApiToken>
 */
class ApiTokenRepository extends AbstractRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ApiToken::class);
    }
}
