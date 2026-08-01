ALTER TABLE campement.mouvement_stock_ligne
    ADD COLUMN numero_lot VARCHAR(100);

COMMENT ON COLUMN campement.mouvement_stock_ligne.numero_lot IS
    'Numéro de lot relevé sur la denrée lors de son entrée en stock.';
