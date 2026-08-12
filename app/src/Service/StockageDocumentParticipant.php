<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Uid\Uuid;

final class StockageDocumentParticipant
{
    public const TAILLE_MAXIMALE = 10 * 1024 * 1024;
    private const EXTENSIONS_PAR_MIME = [
        'application/pdf' => 'pdf',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
    ];

    public function __construct(private readonly string $repertoireDocuments)
    {
    }

    public function valider(UploadedFile $fichier): ?string
    {
        if (!$fichier->isValid()) {
            return 'Le transfert du fichier a échoué.';
        }
        if ($fichier->getSize() > self::TAILLE_MAXIMALE) {
            return 'Chaque fichier doit peser au maximum 10 Mo.';
        }
        if (!isset(self::EXTENSIONS_PAR_MIME[$fichier->getMimeType() ?? ''])) {
            return 'Seuls les fichiers PDF, JPG, JPEG et PNG sont acceptés.';
        }

        return null;
    }

    public function stocker(UploadedFile $fichier): string
    {
        $extension = self::EXTENSIONS_PAR_MIME[$fichier->getMimeType() ?? ''] ?? null;
        if (null === $extension) {
            throw new \RuntimeException('Type de fichier non autorisé.');
        }
        if (!is_dir($this->repertoireDocuments) && !mkdir($this->repertoireDocuments, 0770, true) && !is_dir($this->repertoireDocuments)) {
            throw new \RuntimeException('Impossible de créer le répertoire de stockage des documents.');
        }
        $nomStockage = Uuid::v7()->toRfc4122().'.'.$extension;
        $fichier->move($this->repertoireDocuments, $nomStockage);

        return $nomStockage;
    }

    public function chemin(string $nomStockage): string
    {
        if (basename($nomStockage) !== $nomStockage) {
            throw new \RuntimeException('Chemin de document invalide.');
        }

        return $this->repertoireDocuments.DIRECTORY_SEPARATOR.$nomStockage;
    }

    public function supprimer(string $nomStockage): void
    {
        $chemin = $this->chemin($nomStockage);
        if (is_file($chemin)) {
            @unlink($chemin);
        }
    }

    /** @return list<string> */
    public function listerFichiers(?\DateTimeImmutable $creesAvant = null): array
    {
        if (!is_dir($this->repertoireDocuments)) {
            return [];
        }

        $fichiers = [];
        foreach (new \FilesystemIterator($this->repertoireDocuments, \FilesystemIterator::SKIP_DOTS) as $fichier) {
            if (!$fichier->isFile()) {
                continue;
            }
            $nom = $fichier->getFilename();
            $extension = strtolower($fichier->getExtension());
            $identifiant = pathinfo($nom, PATHINFO_FILENAME);
            if (!Uuid::isValid($identifiant) || !in_array($extension, self::EXTENSIONS_PAR_MIME, true)) {
                continue;
            }
            if ($creesAvant instanceof \DateTimeImmutable && $fichier->getMTime() > $creesAvant->getTimestamp()) {
                continue;
            }
            $fichiers[] = $nom;
        }
        sort($fichiers);

        return $fichiers;
    }
}
