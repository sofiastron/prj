<?php

namespace App\Service;

use Doctrine\ORM\EntityManagerInterface;

/**
 * Exécute un bloc dans une transaction : tout est validé ou tout est annulé.
 * Les appels imbriqués utilisent des points de sauvegarde (savepoints).
 */
class Transaction
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    /**
     * @template T
     *
     * @param callable():T $work
     *
     * @return T
     */
    public function run(callable $work): mixed
    {
        $conn = $this->em->getConnection();
        $conn->beginTransaction();
        try {
            $result = $work();
            $this->em->flush();
            $conn->commit();

            return $result;
        } catch (\Throwable $e) {
            $conn->rollBack();
            // L'état mémoire peut contenir des modifications annulées : on repart d'un contexte propre.
            $this->em->clear();

            throw $e;
        }
    }
}
