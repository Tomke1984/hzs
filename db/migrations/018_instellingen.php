<?php
/**
 * v0.28: instellingen die de beheerder in de app aanpast (eerst: mailinstellingen), zodat config.php op de server
 * (bv. op het volume in Coolify) niet met de hand bewerkt moet worden.
 */
return function (): void {
    Database::pdo()->exec('CREATE TABLE IF NOT EXISTS settings (k VARCHAR(60) NOT NULL PRIMARY KEY, v TEXT NULL)' . (Database::driver() === 'mysql' ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4' : ''));
};
