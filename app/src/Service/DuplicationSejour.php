<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Sejour;
use App\Entity\Utilisateur;
use App\Repository\DenreeRepository;
use Doctrine\DBAL\Connection;

final class DuplicationSejour
{
    public const CHOIX = ['fournisseurs', 'denrees', 'recettes', 'menus', 'inventaire'];

    public function __construct(
        private readonly Connection $connexion,
        private readonly DenreeRepository $denrees,
        private readonly CalculStockDynamique $calculStock,
    ) {
    }

    /** @param list<string> $choix */
    public function dupliquer(Sejour $source, Sejour $cible, array $choix, Utilisateur $auteur): void
    {
        $choix = array_values(array_intersect(self::CHOIX, $choix));
        $besoinDenrees = [] !== array_intersect($choix, ['denrees', 'recettes', 'menus', 'inventaire']);
        $besoinFournisseurs = in_array('fournisseurs', $choix, true);
        $s = (string) $source->getId();
        $c = (string) $cible->getId();
        $inventaire = [];
        if (in_array('inventaire', $choix, true)) {
            $denreesSource = $this->denrees->findBy(['sejour' => $source]);
            $stocks = $this->calculStock->pourDenrees($source, $denreesSource);
            foreach ($denreesSource as $denree) {
                $stock = $stocks[(string) $denree->getId()] ?? ['entrees' => 0.0, 'sorties' => 0.0];
                $quantite = $stock['entrees'] - $stock['sorties'];
                if ($quantite > 0) {
                    $inventaire[$denree->getNom()] = number_format(max(0.001, $quantite), 3, '.', '');
                }
            }
        }
        $this->connexion->transactional(function (Connection $db) use ($s, $c, $choix, $besoinDenrees, $besoinFournisseurs, $auteur, $inventaire): void {
            if ($besoinFournisseurs) {
                $db->executeStatement('INSERT INTO campement.fournisseur (id,sejour_id,nom,telephone,email,adresse,actif) SELECT uuidv7(),:c,nom,telephone,email,adresse,actif FROM campement.fournisseur WHERE sejour_id=:s', compact('s', 'c'));
            }
            if ($besoinDenrees) {
                $db->executeStatement('INSERT INTO campement.denree (id,sejour_id,nom,unite_reference_id,unite_inventaire_id,actif) SELECT uuidv7(),:c,nom,unite_reference_id,unite_inventaire_id,actif FROM campement.denree WHERE sejour_id=:s', compact('s', 'c'));
            }
            if ($besoinFournisseurs && $besoinDenrees) {
                $db->executeStatement('INSERT INTO campement.denree_fournisseur (id,fournisseur_id,denree_id,reference,actif) SELECT uuidv7(),fc.id,dc.id,r.reference,r.actif FROM campement.denree_fournisseur r JOIN campement.fournisseur fs ON fs.id=r.fournisseur_id JOIN campement.fournisseur fc ON fc.sejour_id=:c AND fc.nom=fs.nom JOIN campement.denree ds ON ds.id=r.denree_id JOIN campement.denree dc ON dc.sejour_id=:c AND dc.nom=ds.nom WHERE fs.sejour_id=:s', compact('s', 'c'));
                $db->executeStatement('INSERT INTO campement.denree_fournisseur_conditionnement (id,reference_fournisseur_id,ordre,libelle,conditionnement_id,quantite_contenu,libelle_contenu,unite_contenu_id) SELECT uuidv7(),rc.id,n.ordre,n.libelle,n.conditionnement_id,n.quantite_contenu,n.libelle_contenu,n.unite_contenu_id FROM campement.denree_fournisseur_conditionnement n JOIN campement.denree_fournisseur rs ON rs.id=n.reference_fournisseur_id JOIN campement.fournisseur fs ON fs.id=rs.fournisseur_id JOIN campement.denree ds ON ds.id=rs.denree_id JOIN campement.fournisseur fc ON fc.sejour_id=:c AND fc.nom=fs.nom JOIN campement.denree dc ON dc.sejour_id=:c AND dc.nom=ds.nom JOIN campement.denree_fournisseur rc ON rc.fournisseur_id=fc.id AND rc.denree_id=dc.id AND rc.reference IS NOT DISTINCT FROM rs.reference WHERE fs.sejour_id=:s', compact('s', 'c'));
            }
            if (in_array('recettes', $choix, true)) {
                $db->executeStatement('INSERT INTO campement.recette (id,sejour_id,nom,categorie,actif) SELECT uuidv7(),:c,nom,categorie,actif FROM campement.recette WHERE sejour_id=:s', compact('s', 'c'));
                $db->executeStatement('INSERT INTO campement.recette_denree (id,recette_id,denree_id,conditionnement_id,regime,ordre) SELECT uuidv7(),rc.id,dc.id,rd.conditionnement_id,rd.regime,rd.ordre FROM campement.recette_denree rd JOIN campement.recette rs ON rs.id=rd.recette_id JOIN campement.recette rc ON rc.sejour_id=:c AND rc.nom=rs.nom JOIN campement.denree ds ON ds.id=rd.denree_id JOIN campement.denree dc ON dc.sejour_id=:c AND dc.nom=ds.nom WHERE rs.sejour_id=:s', compact('s', 'c'));
                $db->executeStatement('INSERT INTO campement.recette_denree_quantite (id,recette_denree_id,sejour_public_cible_id,quantite_individuelle) SELECT uuidv7(),rdc.id,pcc.id,q.quantite_individuelle FROM campement.recette_denree_quantite q JOIN campement.recette_denree rds ON rds.id=q.recette_denree_id JOIN campement.recette rs ON rs.id=rds.recette_id JOIN campement.recette rc ON rc.sejour_id=:c AND rc.nom=rs.nom JOIN campement.denree ds ON ds.id=rds.denree_id JOIN campement.denree dc ON dc.sejour_id=:c AND dc.nom=ds.nom JOIN campement.recette_denree rdc ON rdc.recette_id=rc.id AND rdc.denree_id=dc.id AND rdc.ordre=rds.ordre JOIN campement.sejour_public_cible pcs ON pcs.id=q.sejour_public_cible_id JOIN campement.sejour_public_cible pcc ON pcc.sejour_id=:c AND pcc.public_cible_id=pcs.public_cible_id WHERE rs.sejour_id=:s', compact('s', 'c'));
            }
            if (in_array('menus', $choix, true)) {
                $db->executeStatement('INSERT INTO campement.menu (id,sejour_id,sejour_type_repas_id,date_menu,special_code,nom,actif) SELECT uuidv7(),:c,trc.id,CASE WHEN m.date_menu IS NULL THEN NULL ELSE m.date_menu + (sc.date_debut-ss.date_debut) END,m.special_code,m.nom,m.actif FROM campement.menu m JOIN campement.sejour ss ON ss.id=:s JOIN campement.sejour sc ON sc.id=:c LEFT JOIN campement.sejour_type_repas trs ON trs.id=m.sejour_type_repas_id LEFT JOIN campement.sejour_type_repas trc ON trc.sejour_id=:c AND trc.type_repas_id=trs.type_repas_id WHERE m.sejour_id=:s', compact('s', 'c'));
                $db->executeStatement('INSERT INTO campement.menu_denree (id,menu_id,denree_id,conditionnement_id,categorie,regime,ordre) SELECT uuidv7(),mc.id,dc.id,md.conditionnement_id,md.categorie,md.regime,md.ordre FROM campement.menu_denree md JOIN campement.menu ms ON ms.id=md.menu_id JOIN campement.menu mc ON mc.sejour_id=:c AND mc.special_code IS NOT DISTINCT FROM ms.special_code AND mc.date_menu IS NOT DISTINCT FROM (ms.date_menu + ((SELECT date_debut FROM campement.sejour WHERE id=:c)-(SELECT date_debut FROM campement.sejour WHERE id=:s))) AND mc.sejour_type_repas_id IS NOT DISTINCT FROM (SELECT tc.id FROM campement.sejour_type_repas ts JOIN campement.sejour_type_repas tc ON tc.sejour_id=:c AND tc.type_repas_id=ts.type_repas_id WHERE ts.id=ms.sejour_type_repas_id) JOIN campement.denree ds ON ds.id=md.denree_id JOIN campement.denree dc ON dc.sejour_id=:c AND dc.nom=ds.nom WHERE ms.sejour_id=:s', compact('s', 'c'));
                $db->executeStatement('INSERT INTO campement.menu_denree_quantite (id,menu_denree_id,sejour_public_cible_id,quantite_individuelle) SELECT uuidv7(),mdc.id,pcc.id,q.quantite_individuelle FROM campement.menu_denree_quantite q JOIN campement.menu_denree mds ON mds.id=q.menu_denree_id JOIN campement.menu ms ON ms.id=mds.menu_id JOIN campement.denree ds ON ds.id=mds.denree_id JOIN campement.menu mc ON mc.sejour_id=:c AND mc.special_code IS NOT DISTINCT FROM ms.special_code AND mc.date_menu IS NOT DISTINCT FROM (ms.date_menu + ((SELECT date_debut FROM campement.sejour WHERE id=:c)-(SELECT date_debut FROM campement.sejour WHERE id=:s))) AND mc.sejour_type_repas_id IS NOT DISTINCT FROM (SELECT tc.id FROM campement.sejour_type_repas ts JOIN campement.sejour_type_repas tc ON tc.sejour_id=:c AND tc.type_repas_id=ts.type_repas_id WHERE ts.id=ms.sejour_type_repas_id) JOIN campement.denree dc ON dc.sejour_id=:c AND dc.nom=ds.nom JOIN campement.menu_denree mdc ON mdc.menu_id=mc.id AND mdc.denree_id=dc.id AND mdc.ordre=mds.ordre AND mdc.categorie IS NOT DISTINCT FROM mds.categorie JOIN campement.sejour_public_cible pcs ON pcs.id=q.sejour_public_cible_id JOIN campement.sejour_public_cible pcc ON pcc.sejour_id=:c AND pcc.public_cible_id=pcs.public_cible_id WHERE ms.sejour_id=:s', compact('s', 'c'));
            }
            if (in_array('inventaire', $choix, true)) {
                $mouvement = (string) \Symfony\Component\Uid\Uuid::v7();
                $cree = $db->executeStatement("INSERT INTO campement.mouvement_stock (id,sejour_id,utilisateur_id,type_mouvement_id,origine_mouvement_id,date_mouvement) SELECT :m,:c,:u,t.id,o.id,NOW() FROM campement.type_mouvement t CROSS JOIN campement.origine_mouvement o WHERE t.code='ENTREE' AND o.code='INVENTAIRE'", ['m' => $mouvement, 'c' => $c, 'u' => (string) $auteur->getId()]);
                if (1 !== $cree) {
                    throw new \RuntimeException('Le type ou l’origine du mouvement d’inventaire est introuvable.');
                }
                foreach ($inventaire as $nomDenree => $quantite) {
                    $db->executeStatement(
                        'INSERT INTO campement.mouvement_stock_ligne (id,mouvement_stock_id,denree_id,conditionnement_saisie_id,quantite_saisie) SELECT uuidv7(),:m,denree.id,denree.unite_inventaire_id,:quantite FROM campement.denree denree WHERE denree.sejour_id=:c AND denree.nom=:nom',
                        ['m' => $mouvement, 'c' => $c, 'nom' => $nomDenree, 'quantite' => $quantite],
                    );
                }
            }
        });
    }
}
