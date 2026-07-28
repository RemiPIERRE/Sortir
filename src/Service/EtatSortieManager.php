<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Etat;
use App\Entity\Sortie;
use App\Repository\EtatRepository;

class EtatSortieManager
{
    public const CREATED = 'En création';
    public const OPEN = 'Ouverte';
    public const CLOSED = 'Clôturée';
    public const ONGOING = 'En cours';
    public const FINISHED = 'Terminée';
    public const CANCELLED = 'Annulée';
    public const ARCHIVED = 'Historisée';

    private array $stateCache = [];

    public function __construct(private readonly EtatRepository $etatRepository)
    {
    }

    public function getState(string $label): Etat
    {
        if (!isset($this->stateCache[$label])) {
            $state = $this->etatRepository->findOneBy(['libelle' => $label]);
            if (null === $state) {
                throw new \RuntimeException(sprintf(
                    'Etat "%s" non trouvé dans la BDD.',
                    $label
                ));
            }
            $this->stateCache[$label] = $state;
        }

        return $this->stateCache[$label];
    }

    public function initialize(Sortie $sortie, bool $publish): void
    {
        $sortie->setEtat($this->getState($publish ? self::OPEN : self::CREATED));
    }

    public function publish(Sortie $sortie): void
    {
        if (!$this->isInState($sortie, self::CREATED)) {
            throw new \LogicException('Seule une sortie "En création" peut être publiée.');
        }
        $sortie->setEtat($this->getState(self::OPEN));
    }

    public function cancel(Sortie $sortie, string $reason): void
    {
        if (!$this->canBeCancelled($sortie)) {
            throw new \LogicException('Cette sortie ne peut pas être annulée dans son état actuel.');
        }
        $reason = trim($reason);
        if ('' === $reason) {
            throw new \LogicException('Un motif d\'annulation est obligatoire.');
        }
        $sortie->setMotifAnnulation($reason);
        $sortie->setEtat($this->getState(self::CANCELLED));
    }

    public function close(Sortie $sortie): void
    {
        if (!$this->isInState($sortie, self::OPEN)) {
            return;
        }
        $sortie->setEtat($this->getState(self::CLOSED));
    }

    public function canBeEdited(Sortie $sortie): bool
    {
        return $this->isInState($sortie, self::CREATED);
    }

    public function canBeCancelled(Sortie $sortie): bool
    {
        $stateOk = $this->isInOneOfStates($sortie, [self::OPEN, self::CLOSED]);

        return $stateOk && $this->now() < $sortie->getDateHeureDebut();
    }

    public function canRegister(Sortie $sortie): bool
    {
        if (!$this->isInState($sortie, self::OPEN)) {
            return false;
        }
        if ($this->now() > $sortie->getDateLimiteInscription()) {
            return false;
        }

        return $sortie->getInscrits()->count() < $sortie->getNbInscriptionMax();
    }

    public function canWithdraw(Sortie $sortie): bool
    {
        return $this->now() < $sortie->getDateHeureDebut();
    }

    public function refresh(Sortie $sortie): bool
    {
        $current = $sortie->getEtat()?->getLibelle();
        $now = $this->now();

        if (self::CANCELLED !== $current
            && self::ARCHIVED !== $current
            && $now >= $this->startDatePlus($sortie, '+1 month')) {
            $sortie->setEtat($this->getState(self::ARCHIVED));

            return true;
        }

        if (self::OPEN === $current && $now > $sortie->getDateLimiteInscription()) {
            $sortie->setEtat($this->getState(self::CLOSED));
            $current = self::CLOSED;
        }

        if (\in_array($current, [self::OPEN, self::CLOSED], true)
            && $now >= $sortie->getDateHeureDebut()
            && $now < $this->endDate($sortie)) {
            $sortie->setEtat($this->getState(self::ONGOING));

            return true;
        }

        if (\in_array($current, [self::OPEN, self::CLOSED, self::ONGOING], true)
            && $now >= $this->endDate($sortie)) {
            $sortie->setEtat($this->getState(self::FINISHED));

            return true;
        }

        return $current !== $sortie->getEtat()?->getLibelle();
    }

    private function isInState(Sortie $sortie, string $label): bool
    {
        return $sortie->getEtat()?->getLibelle() === $label;
    }

    private function isInOneOfStates(Sortie $sortie, array $labels): bool
    {
        return \in_array($sortie->getEtat()?->getLibelle(), $labels, true);
    }

    private function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable();
    }

    private function endDate(Sortie $sortie): \DateTimeImmutable
    {
        return \DateTimeImmutable::createFromInterface($sortie->getDateHeureDebut())
            ->modify(sprintf('+%d minutes', $sortie->getDuree()));
    }

    private function startDatePlus(Sortie $sortie, string $modifier): \DateTimeImmutable
    {
        return \DateTimeImmutable::createFromInterface($sortie->getDateHeureDebut())
            ->modify($modifier);
    }
}
