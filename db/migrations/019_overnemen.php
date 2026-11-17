<?php
/**
 * v0.28: "Vraag het aan de groep". Wie zich afmeldt zonder vervanger, laat de app een persoonlijke mail sturen naar
 * de collega's die vrij zijn, met een knop "Ik neem over" (zonder aan te melden). Per collega één link.
 */
return function (): void {
    $mysql = Database::driver() === 'mysql';
    Database::pdo()->exec('CREATE TABLE IF NOT EXISTS coverage_offers (
      id ' . ($mysql ? 'INT AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT') . ',
      activity_id INT NOT NULL,
      user_id INT NOT NULL,
      token_hash VARCHAR(64) NOT NULL UNIQUE,
      asked_by INT NULL,
      created_at VARCHAR(19) NOT NULL,
      used_at VARCHAR(19) NULL
    )' . ($mysql ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci' : ''));
};
