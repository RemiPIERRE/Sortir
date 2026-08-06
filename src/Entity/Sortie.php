<?php

namespace App\Entity;

use App\Repository\SortieRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Une sortie : événement organisé par un participant et ouvert aux inscriptions.
 *
 * Porte ses dates, sa capacité, son lieu, son campus, son organisateur, ses
 * inscrits et son état courant (voir EtatSortieManager pour les transitions).
 */
#[ORM\Entity(repositoryClass: SortieRepository::class)]
class Sortie
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $nom = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $dateHeureDebut = null;

    #[ORM\Column]
    private ?int $duree = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $dateLimiteInscription = null;

    #[ORM\Column]
    private ?int $nbInscriptionMax = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $infoSortie = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $motifAnnulation = null;

    #[ORM\ManyToOne(inversedBy: 'sorties')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Etat $etat = null;

    #[ORM\ManyToOne(inversedBy: 'sorties')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Lieu $lieu = null;

    #[ORM\ManyToOne(inversedBy: 'sorties')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Campus $campus = null;

    #[ORM\ManyToOne(inversedBy: 'sortiesOrganisees')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Participant $organisateur = null;

    /**
     * @var Collection<int, Participant>
     */
    #[ORM\ManyToMany(targetEntity: Participant::class, inversedBy: 'sortiesInscrites')]
    private Collection $inscrits;

    public function __construct()
    {
        $this->inscrits = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(string $nom): static
    {
        $this->nom = $nom;

        return $this;
    }

    public function getDateHeureDebut(): ?\DateTimeImmutable
    {
        return $this->dateHeureDebut;
    }

    public function setDateHeureDebut(\DateTimeImmutable $dateHeureDebut): static
    {
        $this->dateHeureDebut = $dateHeureDebut;

        return $this;
    }

    public function getDuree(): ?int
    {
        return $this->duree;
    }

    public function setDuree(int $duree): static
    {
        $this->duree = $duree;

        return $this;
    }

    public function getDateLimiteInscription(): ?\DateTimeImmutable
    {
        return $this->dateLimiteInscription;
    }

    public function setDateLimiteInscription(\DateTimeImmutable $dateLimiteInscription): static
    {
        $this->dateLimiteInscription = $dateLimiteInscription;

        return $this;
    }

    public function getNbInscriptionMax(): ?int
    {
        return $this->nbInscriptionMax;
    }

    public function setNbInscriptionMax(int $nbInscriptionMax): static
    {
        $this->nbInscriptionMax = $nbInscriptionMax;

        return $this;
    }

    public function getInfoSortie(): ?string
    {
        return $this->infoSortie;
    }

    public function setInfoSortie(string $infoSortie): static
    {
        $this->infoSortie = $infoSortie;

        return $this;
    }

    public function getMotifAnnulation(): ?string
    {
        return $this->motifAnnulation;
    }

    public function setMotifAnnulation(?string $motifAnnulation): static
    {
        $this->motifAnnulation = $motifAnnulation;

        return $this;
    }

    public function getEtat(): ?Etat
    {
        return $this->etat;
    }

    public function setEtat(?Etat $etat): static
    {
        $this->etat = $etat;

        return $this;
    }

    public function getLieu(): ?Lieu
    {
        return $this->lieu;
    }

    public function setLieu(?Lieu $lieu): static
    {
        $this->lieu = $lieu;

        return $this;
    }

    public function getCampus(): ?Campus
    {
        return $this->campus;
    }

    public function setCampus(?Campus $campus): static
    {
        $this->campus = $campus;

        return $this;
    }

    public function getOrganisateur(): ?Participant
    {
        return $this->organisateur;
    }

    public function setOrganisateur(?Participant $organisateur): static
    {
        $this->organisateur = $organisateur;

        return $this;
    }

    /**
     * @return Collection<int, Participant>
     */
    public function getInscrits(): Collection
    {
        return $this->inscrits;
    }

    public function addInscrit(Participant $inscrit): static
    {
        if (!$this->inscrits->contains($inscrit)) {
            $this->inscrits->add($inscrit);
        }

        return $this;
    }

    public function removeInscrit(Participant $inscrit): static
    {
        $this->inscrits->removeElement($inscrit);

        return $this;
    }
}
