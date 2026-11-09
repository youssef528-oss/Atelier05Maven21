-- ============================================================================
-- SCRIPT DE CRÉATION DE LA BASE DE DONNÉES ET DE LA TABLE "personne"
-- TP07 : Application CRUD PHP à table unique
-- ============================================================================

CREATE DATABASE IF NOT EXISTS `tp_php` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `tp_php`;

-- Suppression de l'ancienne table personne si vous souhaitez la réinitialiser
DROP TABLE IF EXISTS `personne`;

-- Création de la table principale "personne" avec le CIN comme clé primaire (TP07)
CREATE TABLE `personne` (
    `cin` VARCHAR(20) NOT NULL,
    `nom` VARCHAR(60) NOT NULL,
    `prenom` VARCHAR(60) NOT NULL,
    `email` VARCHAR(100) NOT NULL,
    `telephone` VARCHAR(30) NOT NULL,
    `date_naissance` DATE NOT NULL,
    `cv` VARCHAR(255) DEFAULT NULL,
    `date_creation` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `date_modification` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`cin`),
    UNIQUE KEY `uk_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insertion de données de test cohérentes
INSERT INTO `personne` (`cin`, `nom`, `prenom`, `email`, `telephone`, `date_naissance`, `cv`) VALUES
('EE123456', 'El Alaoui', 'Youssef', 'youssef.elalaoui@example.ma', '+212653535353', '2000-01-01', 'cv_EE123456_sample.pdf'),
('AB654321', 'Friouli', 'Sara', 'sara.friouli@example.ma', '0637482508', '2002-05-14', 'cv_AB654321_sample.pdf'),
('C987654', 'Bennani', 'Karim', 'karim.bennani@example.ma', '0612345678', '1998-11-20', NULL);
