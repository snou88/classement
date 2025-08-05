-- Ajout des colonnes best et worst à la table teams
ALTER TABLE teams
ADD COLUMN best INT DEFAULT 0,
ADD COLUMN worst INT DEFAULT 0;
