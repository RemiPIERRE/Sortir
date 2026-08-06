<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Participant;
use App\Repository\CampusRepository;
use App\Repository\ParticipantRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Import en masse de participants depuis un fichier CSV (séparateur « ; »).
 *
 * En-tête attendu : email, password, campus, admin. Chaque ligne est validée
 * individuellement (champs requis, email valide, doublons intra-fichier et en
 * base, campus existant) ; les lignes fautives sont collectées sans interrompre
 * l'import. Les mots de passe sont hashés avant persistance.
 */
class UserCsvImporter
{
    private const REQUIRED_COLUMNS = ['email', 'password', 'campus', 'admin'];
    private const ADMIN_TRUE = ['1', 'true', 'vrai', 'oui', 'o', 'x', 'yes', 'y'];

    public function __construct(
        private readonly EntityManagerInterface      $em,
        private readonly CampusRepository            $campusRepository,
        private readonly ParticipantRepository       $participantRepository,
        private readonly UserPasswordHasherInterface $hasher,
    )
    {
    }

    /**
     * @return array{created:int, errors:list<array{line:int, email:string, reason:string}>}
     */
    public function import(\SplFileInfo $file): array
    {
        $created = 0;
        $errors = [];

        $handle = fopen($file->getPathname(), 'rb');
        if ($handle === false) {
            return ['created' => 0, 'errors' => [['line' => 0, 'email' => '', 'reason' => 'Fichier illisible.']]];
        }

        $header = fgetcsv($handle, 0, ';');
        if ($header === false) {
            fclose($handle);
            return ['created' => 0, 'errors' => [['line' => 0, 'email' => '', 'reason' => 'Fichier vide.']]];
        }

        $header = array_map(static fn($h) => strtolower(trim((string)$h)), $header);
        $header[0] = preg_replace('/^\x{FEFF}/u', '', $header[0]) ?? $header[0];
        $map = array_flip($header);

        foreach (self::REQUIRED_COLUMNS as $col) {
            if (!isset($map[$col])) {
                fclose($handle);
                return ['created' => 0, 'errors' => [[
                    'line' => 1, 'email' => '',
                    'reason' => sprintf('Colonne manquante dans l’en-tête : « %s ».', $col),
                ]]];
            }
        }

        $campusByName = [];
        foreach ($this->campusRepository->findAll() as $campus) {
            $campusByName[mb_strtolower(trim((string)$campus->getNom()))] = $campus;
        }

        $seenEmails = [];
        $ligne = 1;

        while (($row = fgetcsv($handle, 0, ';')) !== false) {
            $ligne++;

            if (count(array_filter($row, static fn($v) => trim((string)$v) !== '')) === 0) {
                continue;
            }

            $email = trim((string)($row[$map['email']] ?? ''));
            $password = (string)($row[$map['password']] ?? '');
            $campusNom = trim((string)($row[$map['campus']] ?? ''));
            $adminRaw = trim((string)($row[$map['admin']] ?? ''));

            if ($email === '' || $password === '' || $campusNom === '') {
                $errors[] = ['line' => $ligne, 'email' => $email, 'reason' => 'Champ obligatoire manquant (email, password ou campus).'];
                continue;
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = ['line' => $ligne, 'email' => $email, 'reason' => 'Adresse email invalide.'];
                continue;
            }

            $emailKey = mb_strtolower($email);
            if (isset($seenEmails[$emailKey])) {
                $errors[] = ['line' => $ligne, 'email' => $email, 'reason' => 'Doublon dans le fichier (email déjà présent plus haut).'];
                continue;
            }

            if ($this->participantRepository->findOneBy(['email' => $email]) !== null) {
                $errors[] = ['line' => $ligne, 'email' => $email, 'reason' => 'Un compte existe déjà avec cet email.'];
                continue;
            }

            $campus = $campusByName[mb_strtolower($campusNom)] ?? null;
            if ($campus === null) {
                $errors[] = ['line' => $ligne, 'email' => $email, 'reason' => sprintf('Campus inconnu : « %s ».', $campusNom)];
                continue;
            }

            $user = new Participant();
            $user->setEmail($email);
            $user->setCampus($campus);
            $user->setActif(true);
            $user->setAdministrateur(in_array(mb_strtolower($adminRaw), self::ADMIN_TRUE, true));
            $user->setPassword($this->hasher->hashPassword($user, $password));

            $this->em->persist($user);
            $seenEmails[$emailKey] = true;
            $created++;
        }

        fclose($handle);
        $this->em->flush();

        return ['created' => $created, 'errors' => $errors];
    }
}
