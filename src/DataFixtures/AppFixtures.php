<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Campus;
use App\Entity\Etat;
use App\Entity\Lieu;
use App\Entity\Participant;
use App\Entity\Sortie;
use App\Entity\Ville;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    public function __construct(
        private readonly UserPasswordHasherInterface $hasher
    )
    {
    }

    public function load(ObjectManager $manager): void
    {
        $villes = $this->loadVilles($manager);
        $campus = $this->loadCampus($manager);
        $etats = $this->loadEtats($manager);
        $lieux = $this->loadLieux($manager, $villes);
        $participants = $this->loadParticipants($manager, $campus);
        $this->loadSorties($manager, $etats, $lieux, $campus, $participants);

        $manager->flush();
    }

    /**
     * @return array<string, Ville>
     */
    private function loadVilles(ObjectManager $manager): array
    {
        $data = [
            'chartres' => ['Chartres-de-Bretagne', '35131'],
            'herblain' => ['Saint-Herblain', '44800'],
            'roche' => ['La Roche-sur-Yon', '85000'],
            'nantes' => ['Nantes', '44000'],
            'rennes' => ['Rennes', '35000'],
            'niort' => ['Niort', '79000'],
        ];

        $villes = [];
        foreach ($data as $key => [$nom, $cp]) {
            $ville = new Ville();
            $ville->setNom($nom);
            $ville->setCodePostal($cp);
            $manager->persist($ville);
            $villes[$key] = $ville;
        }

        return $villes;
    }

    /**
     * @return array<string, Campus>
     */
    private function loadCampus(ObjectManager $manager): array
    {
        $noms = [
            'chartres' => 'CHARTRES-DE-BRETAGNE',
            'herblain' => 'SAINT-HERBLAIN',
            'roche' => 'LA ROCHE-SUR-YON',
        ];

        $campus = [];
        foreach ($noms as $key => $nom) {
            $c = new Campus();
            $c->setNom($nom);
            $manager->persist($c);
            $campus[$key] = $c;
        }

        return $campus;
    }

    /**
     * Les 7 etats du cycle de vie d'une sortie (diagramme d'etat).
     *
     * @return array<string, Etat>
     */
    private function loadEtats(ObjectManager $manager): array
    {
        $libelles = [
            'creation' => 'En création',
            'ouverte' => 'Ouverte',
            'cloturee' => 'Clôturée',
            'encours' => 'En cours',
            'terminee' => 'Terminée',
            'annulee' => 'Annulée',
            'historisee' => 'Historisée',
        ];

        $etats = [];
        foreach ($libelles as $key => $libelle) {
            $etat = new Etat();
            $etat->setLibelle($libelle);
            $manager->persist($etat);
            $etats[$key] = $etat;
        }

        return $etats;
    }

    /**
     * @param array<string, Ville> $villes
     * @return array<string, Lieu>
     */
    private function loadLieux(ObjectManager $manager, array $villes): array
    {
        $data = [
            'parc' => ['Salle Polyvalente du Parc', '2 rue du Parc', 'chartres', 48.0028, -1.7016],
            'poterie' => ['Atelier Poterie & Co', '14 rue des Arts', 'nantes', 47.2184, -1.5536],
            'murdock' => ['Pub Murdock', '5 place du Marché', 'rennes', 48.1113, -1.6800],
            'plage' => ['Base Nautique', 'Quai de la Loire', 'herblain', 47.2100, -1.6500],
            'foret' => ['Maison de la Forêt', 'Route des Chênes', 'roche', 46.6705, -1.4260],
            'cinema' => ['Cinéma Le Concorde', '79 bd de l\'Égalité', 'nantes', 47.2170, -1.5680],
        ];

        $lieux = [];
        foreach ($data as $key => [$nom, $rue, $villeKey, $lat, $lng]) {
            $lieu = new Lieu();
            $lieu->setNom($nom);
            $lieu->setRue($rue);
            $lieu->setLatitude($lat);
            $lieu->setLongitude($lng);
            $lieu->setVille($villes[$villeKey]);
            $manager->persist($lieu);
            $lieux[$key] = $lieu;
        }

        return $lieux;
    }

    /**
     * @param array<string, Campus> $campus
     * @return array<string, Participant>
     */
    private function loadParticipants(ObjectManager $manager, array $campus): array
    {
        // [cle, mail, pseudo, nom, prenom, tel, admin, actif, campusKey]
        $data = [
            ['admin', 'admin@eni.fr', 'admin.ENI', 'Berthier', 'Sophie', '0600000000', true, true, 'chartres'],
            ['jeannine', 'jeannine.leroux@eni.fr', 'Jeannine.L', 'Leroux', 'Jeannine', '0611111111', false, true, 'chartres'],
            ['adrien', 'adrien.spinoza@eni.fr', 'Spinoz_A', 'Spinoza', 'Adrien', '0622222222', false, true, 'herblain'],
            ['remi', 'remi.sauveterre@eni.fr', 'Remi.S', 'Sauveterre', 'Rémi', '0633333333', false, true, 'chartres'],
            ['joseph', 'joseph.olivier@eni.fr', 'Jojo56', 'Olivier', 'Joseph', '0644444444', false, true, 'roche'],
            ['marie', 'marie.curie@eni.fr', 'MarieC', 'Curie', 'Marie', '0655555555', false, true, 'chartres'],
            ['raymond', 'raymond.daubigny@eni.fr', 'Raymond.D', 'Daubigny', 'Raymond', '0666666666', false, false, 'herblain'],
            [null, 'timothee.chalamet@eni.fr', null, null, null, null, false, true, 'chartres'],
            [null, 'keanu.reeves@eni.fr', null, null, null, null, false, true, 'herblain']

        ];

        $participants = [];
        foreach ($data as [$key, $mail, $pseudo, $nom, $prenom, $tel, $admin, $actif, $campusKey]) {
            $p = new Participant();
            $p->setEmail($mail);
            $p->setPseudo($pseudo);
            $p->setNom($nom);
            $p->setPrenom($prenom);
            $p->setTelephone($tel);
            $p->setAdministrateur($admin);
            $p->setActif($actif);
            $p->setRoles($admin ? ['ROLE_ADMIN'] : []);
            $p->setPassword($this->hasher->hashPassword($p, 'password'));
            $p->setCampus($campus[$campusKey]);
            $manager->persist($p);
            $participants[$key] = $p;
        }

        return $participants;
    }

    /**
     * Une sortie par etat, pour couvrir tout le cycle de vie.
     *
     * @param array<string, Etat> $etats
     * @param array<string, Lieu> $lieux
     * @param array<string, Campus> $campus
     * @param array<string, Participant> $participants
     */
    private function loadSorties(
        ObjectManager $manager,
        array         $etats,
        array         $lieux,
        array         $campus,
        array         $participants
    ): void
    {
        $jour = static fn(int $offset, string $heure = '18:30'): \DateTimeImmutable => (new \DateTimeImmutable($heure))->modify(sprintf('%+d days', $offset));

        // [nom, etatKey, organisateurKey, lieuKey, campusKey, debutOffset, limiteOffset, duree, max, infos, motif, inscritsKeys]
        $data = [
            [
                'Tournoi de Philo', 'encours', 'adrien', 'parc', 'chartres',
                0, -2, 120, 8,
                'Débat ouvert sur le libre arbitre, ambiance conviviale.',
                null, ['jeannine', 'remi', 'marie'],
            ],
            [
                'Atelier Origami', 'cloturee', 'remi', 'parc', 'chartres',
                -3, -5, 90, 5,
                'Initiation au pliage japonais, matériel fourni.',
                null, ['jeannine', 'marie', 'joseph'],
            ],
            [
                'Atelier Perles & Bijoux', 'ouverte', 'joseph', 'poterie', 'roche',
                7, 5, 120, 12,
                'Création de bijoux en perles, tous niveaux.',
                null, ['marie', 'jeannine'],
            ],
            [
                'Concert Métal Underground', 'ouverte', 'raymond', 'murdock', 'herblain',
                12, 10, 180, 10,
                'Soirée concert dans une salle intimiste.',
                null, ['adrien'],
            ],
            [
                'Soirée Jardinage Collectif', 'ouverte', 'remi', 'parc', 'chartres',
                9, 7, 150, 15,
                'Plantation partagée au jardin du campus.',
                null, ['jeannine', 'marie', 'joseph'],
            ],
            [
                'Sortie Cinéma — Avant-première', 'creation', 'jeannine', 'cinema', 'chartres',
                18, 16, 130, 10,
                'Projection en avant-première, encore en préparation.',
                null, [],
            ],
            [
                'Soirée Jeux de Société', 'annulee', 'marie', 'murdock', 'herblain',
                5, 3, 180, 12,
                'Soirée jeux annulée faute de salle disponible.',
                'Salle indisponible à la dernière minute.',
                ['jeannine', 'adrien', 'joseph', 'remi'],
            ],
            [
                'Rando Forêt & Pique-nique', 'terminee', 'joseph', 'foret', 'roche',
                -8, -12, 240, 20,
                'Randonnée de 10 km suivie d\'un pique-nique.',
                null, ['jeannine', 'marie', 'remi', 'adrien'],
            ],
            [
                'Ancienne Balade Nautique', 'historisee', 'adrien', 'plage', 'herblain',
                -45, -50, 120, 15,
                'Sortie kayak (archivée, plus d\'un mois).',
                null, ['jeannine', 'remi'],
            ],
        ];

        foreach ($data as [$nom, $etatKey, $orgKey, $lieuKey, $campusKey,
                 $debutOffset, $limiteOffset, $duree, $max, $infos, $motif, $inscritsKeys]) {
            $sortie = new Sortie();
            $sortie->setNom($nom);
            $sortie->setDateHeureDebut($jour($debutOffset));
            $sortie->setDateLimiteInscription($jour($limiteOffset, '23:59'));
            $sortie->setDuree($duree);
            $sortie->setNbInscriptionMax($max);
            $sortie->setInfoSortie($infos);
            $sortie->setMotifAnnulation($motif);
            $sortie->setEtat($etats[$etatKey]);
            $sortie->setLieu($lieux[$lieuKey]);
            $sortie->setCampus($campus[$campusKey]);
            $sortie->setOrganisateur($participants[$orgKey]);

            foreach ($inscritsKeys as $pk) {
                $sortie->addInscrit($participants[$pk]);
            }

            $manager->persist($sortie);
        }
    }
}
