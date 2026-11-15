-- Permet à l'utilisateur applicatif de créer la base de test « resto_test » (composer test:prepare).
GRANT ALL PRIVILEGES ON `resto%`.* TO 'resto'@'%';
FLUSH PRIVILEGES;
