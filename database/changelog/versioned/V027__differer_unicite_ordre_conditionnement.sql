--liquibase formatted sql

--changeset campement:V027-differer-unicite-ordre-conditionnement splitStatements:true endDelimiter:;
--comment: Autorise la réorganisation atomique des conditionnements d'une référence fournisseur

ALTER TABLE campement.denree_fournisseur_conditionnement
    DROP CONSTRAINT uq_denree_fournisseur_conditionnement,
    ADD CONSTRAINT uq_denree_fournisseur_conditionnement
        UNIQUE (reference_fournisseur_id, ordre)
        DEFERRABLE INITIALLY DEFERRED;

--rollback ALTER TABLE campement.denree_fournisseur_conditionnement DROP CONSTRAINT uq_denree_fournisseur_conditionnement; ALTER TABLE campement.denree_fournisseur_conditionnement ADD CONSTRAINT uq_denree_fournisseur_conditionnement UNIQUE (reference_fournisseur_id, ordre) NOT DEFERRABLE;
