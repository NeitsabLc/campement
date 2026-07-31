--liquibase formatted sql

--changeset campement:V022-autorisation-depart-unique
--comment: Limiter chaque participant à une autorisation de départ en camp

CREATE UNIQUE INDEX uq_document_participant_autorisation
    ON campement.document_participant(participant_id)
    WHERE type = 'autorisation_depart_camp';

--rollback DROP INDEX campement.uq_document_participant_autorisation;
