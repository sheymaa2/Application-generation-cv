<?php
// deconnexion.php : vide et détruit la session, puis retour à la connexion
include 'config.php';

$_SESSION = [];
session_destroy();

header('Location: connexion.php');
exit;
