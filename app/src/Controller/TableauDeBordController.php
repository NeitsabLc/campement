<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\DocumentParticipant;
use App\Entity\Participant;
use App\Entity\PresenceParticipant;
use App\Entity\Utilisateur;
use App\Repository\GroupeRepository;
use App\Repository\MenuRepository;
use App\Repository\ParticipantRepository;
use App\Service\ContexteSejour;
use App\Service\PresentationMenu;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class TableauDeBordController extends AbstractController
{
    #[Route('/', name: 'app_tableau_de_bord', methods: ['GET'])]
    public function index(
        ContexteSejour $contexte,
        GroupeRepository $groupes,
        ParticipantRepository $participants,
        MenuRepository $menus,
        PresentationMenu $presentationMenu,
        EntityManagerInterface $entityManager,
    ): Response {
        $sejour = $contexte->actif();
        $utilisateur = $this->getUser();
        $vueGroupe = $this->isGranted(Utilisateur::ROLE_GROUPE) && $utilisateur instanceof Utilisateur;
        $groupeUtilisateur = $vueGroupe ? $utilisateur->getGroupe() : null;
        $aujourdhui = new \DateTimeImmutable('today');
        $sejourEnCours = null !== $sejour
            && $aujourdhui >= $sejour->getDateDebut()
            && $aujourdhui <= $sejour->getDateFin();
        $resumeEffectifs = null;
        $menusDuJour = [];
        $suiviDocuments = null;

        if (null !== $sejour) {
            $groupesDuSejour = $groupes->findActifsPourSejour($sejour);
            if (null !== $groupeUtilisateur) {
                $groupesDuSejour = array_values(array_filter(
                    $groupesDuSejour,
                    static fn ($groupe): bool => $groupe === $groupeUtilisateur,
                ));
            }
            $participantsDuSejour = $participants->findPourSejour($sejour);
            if (null !== $groupeUtilisateur) {
                $participantsDuSejour = array_values(array_filter(
                    $participantsDuSejour,
                    static fn (Participant $participant): bool => $participant->getGroupe() === $groupeUtilisateur,
                ));
            }

            if ($sejourEnCours && $sejour->isModuleAdministratifActif()) {
                $listeParticipants = $participantsDuSejour;
                $requetePresences = $entityManager->getRepository(PresenceParticipant::class)->createQueryBuilder('presence')
                    ->join('presence.participant', 'participant')
                    ->join('participant.groupe', 'groupe')
                    ->andWhere('groupe.sejour = :sejour')
                    ->andWhere('presence.datePresence <= :date')
                    ->setParameter('sejour', $sejour)
                    ->setParameter('date', $aujourdhui);
                if (null !== $groupeUtilisateur) {
                    $requetePresences->andWhere('groupe = :groupe')->setParameter('groupe', $groupeUtilisateur);
                }
                $presences = $requetePresences->orderBy('presence.datePresence', 'DESC')->getQuery()->getResult();
                $exceptions = [];
                foreach ($presences as $presence) {
                    $id = (string) $presence->getParticipant()->getId();
                    $exceptions[$id] ??= [];
                    $exceptions[$id][] = $presence;
                }

                $parGroupe = [];
                foreach ($groupesDuSejour as $groupe) {
                    $parGroupe[(string) $groupe->getId()] = ['groupe' => $groupe, 'jeunes' => 0, 'adultes' => 0, 'total' => 0];
                }
                $totaux = ['jeunes' => 0, 'adultes' => 0, 'presents' => 0, 'absents' => 0];
                foreach ($listeParticipants as $participant) {
                    if (!$participant->getGroupe()->estPresentLe($aujourdhui)
                        || $aujourdhui < $participant->getDateDebutPresence()
                        || $aujourdhui > $participant->getDateFinPresence()) {
                        continue;
                    }
                    $absent = false;
                    foreach ($exceptions[(string) $participant->getId()] ?? [] as $exception) {
                        if (PresenceParticipant::DEPART === $exception->getStatut()
                            || ($exception->getDatePresence() == $aujourdhui && PresenceParticipant::ABSENT === $exception->getStatut())) {
                            $absent = true;
                            break;
                        }
                    }
                    if ($absent) {
                        ++$totaux['absents'];
                        continue;
                    }
                    $cle = Participant::TYPE_JEUNE === $participant->getType() ? 'jeunes' : 'adultes';
                    $groupeId = (string) $participant->getGroupe()->getId();
                    ++$totaux[$cle];
                    ++$totaux['presents'];
                    ++$parGroupe[$groupeId][$cle];
                    ++$parGroupe[$groupeId]['total'];
                }
                $resumeEffectifs = ['mode' => 'reel', 'totaux' => $totaux, 'groupes' => array_values($parGroupe)];
            } else {
                $parGroupe = [];
                $totaux = ['jeunes' => 0, 'adultes' => 0, 'total' => 0];
                foreach ($groupesDuSejour as $groupe) {
                    $total = $groupe->getEffectifJeune() + $groupe->getEffectifAdulte();
                    $parGroupe[] = ['groupe' => $groupe, 'jeunes' => $groupe->getEffectifJeune(), 'adultes' => $groupe->getEffectifAdulte(), 'total' => $total];
                    $totaux['jeunes'] += $groupe->getEffectifJeune();
                    $totaux['adultes'] += $groupe->getEffectifAdulte();
                    $totaux['total'] += $total;
                }
                $resumeEffectifs = ['mode' => 'theorique', 'totaux' => $totaux, 'groupes' => $parGroupe];
            }

            if ($sejourEnCours && $sejour->isModuleIntendanceActif()) {
                $menusDuJour = $presentationMenu->resumesMenus($menus->findPourDate($sejour, $aujourdhui));
            }

            if ($sejour->isModuleAdministratifActif()) {
                $statuts = ['complets' => 0, 'incomplets' => 0, 'aucun' => 0, 'total' => 0];
                foreach ($participantsDuSejour as $participant) {
                    $requis = DocumentParticipant::typesPour($participant->getType());
                    $nombre = count(array_filter($requis, $participant->hasDocumentType(...)));
                    ++$statuts['total'];
                    ++$statuts[0 === $nombre ? 'aucun' : ($nombre === count($requis) ? 'complets' : 'incomplets')];
                }
                $suiviDocuments = $statuts;
            }
        }

        return $this->render('tableau_de_bord/index.html.twig', [
            'sejours' => $contexte->accessibles(),
            'sejour_selectionne' => $sejour,
            'aujourdhui' => $aujourdhui,
            'sejour_en_cours' => $sejourEnCours,
            'resume_effectifs' => $resumeEffectifs,
            'menus_du_jour' => $menusDuJour,
            'suivi_documents' => $suiviDocuments,
            'vue_groupe' => $vueGroupe,
        ]);
    }
}
