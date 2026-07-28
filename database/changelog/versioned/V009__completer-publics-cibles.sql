INSERT INTO campement.public_cible (code, libelle, ordre)
VALUES
    ('FARFADETS',              'Farfadets',              1),
    ('LOUVETEAUX_JEANNETTES', 'Louveteaux-Jeannettes', 2),
    ('SCOUTS_GUIDES',         'Scouts-Guides',         3),
    ('PIONNIERS_CARAVELLES',  'Pionniers-Caravelles',  4),
    ('COMPAGNONS',             'Compagnons',             5),
    ('ADULTE',                 'Adulte',                 6)
ON CONFLICT (code) DO UPDATE
SET libelle = EXCLUDED.libelle,
    ordre = EXCLUDED.ordre;
