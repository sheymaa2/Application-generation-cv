<?php
// listes.php : choix possibles, inclus dans formulaire.php (affichage) et recap.php (vérification)

$filieres      = ['2AP', 'GSTR', 'GI', 'SCM', 'GC', 'MS'];
$annees        = ['1ère année', '2ème année', '3ème année'];
$modules       = ['Pro Av', 'Compilation', 'Réseaux Av', 'Web Avancée', 'POO', 'BD'];
$types_projet  = ['Projet', 'Stage'];
$nb_projets_max = 10;

// Réglages du fichier envoyé
$dossier_uploads       = 'uploads';
$extensions_autorisees = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'];
$taille_max_fichier    = 5 * 1024 * 1024;   // 5 Mo en octets
