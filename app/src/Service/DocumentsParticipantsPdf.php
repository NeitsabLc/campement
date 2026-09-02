<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\DocumentParticipant;
use App\Entity\Participant;
use setasign\Fpdi\Fpdi;

final class DocumentsParticipantsPdf
{
    public function __construct(private readonly StockageDocumentParticipant $stockage)
    {
    }

    /**
     * @param list<Participant> $participants
     * @param list<string>      $types
     */
    public function generer(array $participants, array $types): string
    {
        $pdf = new Fpdi();
        $pdf->SetAutoPageBreak(false);

        foreach ($participants as $participant) {
            $documents = [];
            foreach ($types as $type) {
                $documents[$type] = array_values(array_filter(
                    $participant->getDocuments()->toArray(),
                    static fn (DocumentParticipant $document): bool => $document->getType() === $type,
                ));
            }

            $this->ajouterIntercalaire($pdf, $participant, $types, $documents);
            foreach ($types as $type) {
                foreach ($documents[$type] as $document) {
                    $chemin = $this->stockage->chemin($document->getCheminStockage());
                    if (!is_file($chemin)) {
                        continue;
                    }
                    'pdf' === strtolower(pathinfo($chemin, PATHINFO_EXTENSION))
                        ? $this->ajouterPdf($pdf, $chemin)
                        : $this->ajouterImage($pdf, $chemin);
                }
            }
        }

        if ([] === $participants) {
            $pdf->AddPage();
            $pdf->SetFont('Arial', 'B', 18);
            $pdf->SetTextColor(0, 59, 96);
            $pdf->Cell(0, 20, $this->texte('Aucun participant enregistré'), 0, 1, 'C');
        }

        return $pdf->Output('S');
    }

    /**
     * @param list<string>                             $types
     * @param array<string, list<DocumentParticipant>> $documents
     */
    private function ajouterIntercalaire(Fpdi $pdf, Participant $participant, array $types, array $documents): void
    {
        $libelles = [
            DocumentParticipant::FICHE_SANITAIRE => 'Fiche sanitaire',
            DocumentParticipant::VACCINS => 'Copie des vaccins',
            DocumentParticipant::QUALIFICATION => 'Formation',
            DocumentParticipant::AUTORISATION_DEPART_CAMP => 'Autorisation de départ en camp',
        ];
        $pdf->AddPage();
        $pdf->SetFillColor(0, 59, 96);
        $pdf->Rect(0, 0, 210, 38, 'F');
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('Arial', 'B', 20);
        $pdf->SetXY(15, 12);
        $pdf->Cell(180, 10, $this->texte($participant->getPrenom().' '.mb_strtoupper($participant->getNom())), 0, 1);
        $pdf->SetTextColor(0, 59, 96);
        $pdf->SetFont('Arial', '', 11);
        $pdf->SetXY(15, 48);
        $pdf->Cell(0, 7, $this->texte('Unité : '.$participant->getGroupe()->getNom()), 0, 1);
        $pdf->SetX(15);
        $pdf->Cell(0, 7, $this->texte('Date de naissance : '.$participant->getDateNaissance()->format('d/m/Y')), 0, 1);
        $pdf->Ln(7);
        $pdf->SetFont('Arial', 'B', 13);
        $pdf->SetX(15);
        $pdf->Cell(0, 8, 'Documents', 0, 1);
        $pdf->SetFont('Arial', '', 11);
        foreach ($types as $type) {
            $nombre = count($documents[$type]);
            $pdf->SetX(20);
            $pdf->Cell(0, 8, $this->texte(sprintf('%s : %s', $libelles[$type], $nombre > 0 ? $nombre.' fichier(s)' : 'manquant')), 0, 1);
        }
    }

    private function ajouterPdf(Fpdi $pdf, string $chemin): void
    {
        try {
            $pages = $pdf->setSourceFile($chemin);
            for ($numero = 1; $numero <= $pages; ++$numero) {
                $modele = $pdf->importPage($numero);
                $taille = $pdf->getTemplateSize($modele);
                $pdf->AddPage($taille['orientation'], [$taille['width'], $taille['height']]);
                $pdf->useTemplate($modele);
            }
        } catch (\Throwable $exception) {
            throw new \RuntimeException(sprintf('Le document « %s » ne peut pas être intégré au PDF.', basename($chemin)), 0, $exception);
        }
    }

    private function ajouterImage(Fpdi $pdf, string $chemin): void
    {
        $dimensions = @getimagesize($chemin);
        if (false === $dimensions) {
            throw new \RuntimeException(sprintf('L’image « %s » est illisible.', basename($chemin)));
        }
        [$largeur, $hauteur] = $dimensions;
        $orientation = $largeur > $hauteur ? 'L' : 'P';
        $pageLargeur = 'L' === $orientation ? 297.0 : 210.0;
        $pageHauteur = 'L' === $orientation ? 210.0 : 297.0;
        $ratio = min(($pageLargeur - 20) / $largeur, ($pageHauteur - 20) / $hauteur);
        $imageLargeur = $largeur * $ratio;
        $imageHauteur = $hauteur * $ratio;
        $pdf->AddPage($orientation);
        $pdf->Image($chemin, ($pageLargeur - $imageLargeur) / 2, ($pageHauteur - $imageHauteur) / 2, $imageLargeur, $imageHauteur);
    }

    private function texte(string $texte): string
    {
        return iconv('UTF-8', 'windows-1252//TRANSLIT', $texte) ?: $texte;
    }
}
