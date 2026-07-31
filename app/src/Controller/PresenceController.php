<?php
declare(strict_types=1);
namespace App\Controller;
use App\Entity\Participant;
use App\Entity\PresenceParticipant;
use App\Entity\Utilisateur;
use App\Repository\GroupeRepository;
use App\Repository\ParticipantRepository;
use App\Service\ContexteSejour;
use DateInterval;
use DatePeriod;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

#[IsGranted(Utilisateur::ROLE_GESTIONNAIRE)]
final class PresenceController extends AbstractController
{
    #[Route('/administratif/registre-presence', name:'app_presence', methods:['GET','POST'])]
    public function index(Request $request, ContexteSejour $contexte, GroupeRepository $groupes, ParticipantRepository $participants, EntityManagerInterface $em):Response
    {
        $sejour=$contexte->actif(); if(!$sejour) throw $this->createNotFoundException('Aucun séjour actif.');
        $erreurs=[];
        if($request->isMethod('POST')){
            if(!$this->isCsrfTokenValid('modifier_presence',$request->request->getString('_token'))) throw $this->createAccessDeniedException();
            $id=$request->request->getString('participant_id'); $date=DateTimeImmutable::createFromFormat('!Y-m-d',$request->request->getString('date'));
            $participant=Uuid::isValid($id)?$participants->find($id):null; $statut=$request->request->getString('statut'); $commentaire=trim($request->request->getString('commentaire'));
            if(!$participant instanceof Participant||$participant->getGroupe()->getSejour()!==$sejour)$erreurs[]='Participant invalide.';
            if(!$date||$date<$participant?->getDateDebutPresence()||$date>$participant?->getDateFinPresence())$erreurs[]='Date de présence invalide.';
            if(!in_array($statut,['present',PresenceParticipant::ABSENT,PresenceParticipant::DEPART],true))$erreurs[]='Statut invalide.';
            if(PresenceParticipant::DEPART===$statut&&''===$commentaire)$erreurs[]='Le commentaire est obligatoire pour un départ.';
            if(mb_strlen($commentaire)>500)$erreurs[]='Le commentaire ne peut pas dépasser 500 caractères.';
            if([]===$erreurs){
                $repo=$em->getRepository(PresenceParticipant::class); $presence=$repo->findOneBy(['participant'=>$participant,'datePresence'=>$date]);
                $departPrecedent=$repo->createQueryBuilder('p')->where('p.participant=:participant')->andWhere('p.statut=:depart')->andWhere('p.datePresence<=:date')->setParameter('participant',$participant)->setParameter('depart',PresenceParticipant::DEPART)->setParameter('date',$date)->orderBy('p.datePresence','DESC')->setMaxResults(1)->getQuery()->getOneOrNullResult();
                if(in_array($statut,['present',PresenceParticipant::ABSENT],true)&&$departPrecedent&&$departPrecedent!==$presence){$em->remove($departPrecedent);}
                if('present'===$statut){if($presence)$em->remove($presence);}else{$presence??=(new PresenceParticipant())->setParticipant($participant)->setDatePresence($date);$presence->setStatut($statut)->setCommentaire(''===$commentaire?null:$commentaire)->actualiser();$em->persist($presence);}
                if(PresenceParticipant::DEPART===$statut){foreach($repo->createQueryBuilder('p')->where('p.participant=:participant')->andWhere('p.id<>:courant')->andWhere('(p.statut=:depart OR p.datePresence>:date)')->setParameter('participant',$participant)->setParameter('courant',$presence->getId())->setParameter('depart',PresenceParticipant::DEPART)->setParameter('date',$date)->getQuery()->getResult() as $autre)$em->remove($autre);}
                $em->flush(); $this->addFlash('success','Le registre de présence a été mis à jour.'); return $this->redirectToRoute('app_presence',['date'=>$date->format('Y-m-d')]);
            }
        }
        $liste=$participants->findPourSejour($sejour); $presences=$em->getRepository(PresenceParticipant::class)->createQueryBuilder('p')->join('p.participant','participant')->join('participant.groupe','groupe')->where('groupe.sejour=:sejour')->setParameter('sejour',$sejour)->getQuery()->getResult();
        $index=[]; foreach($presences as $p)$index[(string)$p->getParticipant()->getId()][$p->getDatePresence()->format('Y-m-d')]=$p;
        $dates=iterator_to_array(new DatePeriod($sejour->getDateDebut(),new DateInterval('P1D'),$sejour->getDateFin()->modify('+1 day')));
        $demandee=DateTimeImmutable::createFromFormat('!Y-m-d',$request->query->getString('date'));
        $aujourdhui=new DateTimeImmutable('today');
        $dateSelection=$demandee&&$demandee>=$sejour->getDateDebut()&&$demandee<=$sejour->getDateFin()?$demandee:($aujourdhui>=$sejour->getDateDebut()&&$aujourdhui<=$sejour->getDateFin()?$aujourdhui:$sejour->getDateDebut());
        $groupesDuJour=$groupes->findActifsPresentsPourSejour($sejour,$dateSelection);$groupesAffiches=[];foreach($groupesDuJour as $groupe)$groupesAffiches[(string)$groupe->getId()]=true;
        $jour=[];$totauxPresents=['jeunes'=>0,'adultes'=>0];$key=$dateSelection->format('Y-m-d');
        foreach($liste as $participant){$exception=$index[(string)$participant->getId()][$key]??null;$depart=null;foreach($index[(string)$participant->getId()]??[] as $candidate){if(PresenceParticipant::DEPART===$candidate->getStatut()&&$candidate->getDatePresence()<=$dateSelection){$depart=$candidate;break;}}
            $outside=$dateSelection<$participant->getDateDebutPresence()||$dateSelection>$participant->getDateFinPresence();$statut=$outside?'outside':($depart?'departed':($exception?->getStatut()===PresenceParticipant::ABSENT?'absent':'present'));
            $jour[(string)$participant->getId()]=['statut'=>$statut,'modifiable'=>!$outside,'exception'=>$exception??$depart];
            if('present'===$statut&&isset($groupesAffiches[(string)$participant->getGroupe()->getId()])){$cle=Participant::TYPE_JEUNE===$participant->getType()?'jeunes':'adultes';++$totauxPresents[$cle];}}
        return $this->render('presence/index.html.twig',['sejour'=>$sejour,'groupes'=>$groupesDuJour,'participants'=>$liste,'dates'=>$dates,'dateSelection'=>$dateSelection,'aujourdhui'=>$aujourdhui,'aujourdhuiDansSejour'=>$aujourdhui>=$sejour->getDateDebut()&&$aujourdhui<=$sejour->getDateFin(),'jour'=>$jour,'totauxPresents'=>$totauxPresents,'erreurs'=>$erreurs]);
    }
}
