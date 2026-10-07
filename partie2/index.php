<?php
// index.php : page d'entrée, redirige selon la personne connectée
include 'config.php';

if (!isset($_SESSION['email'])) {
    header('Location: connexion.php');
} elseif ($_SESSION['role'] === 'entreprise') {
    header('Location: entreprise_offres.php');
} else {
    header('Location: cv_formulaire.php');
}
exit;
