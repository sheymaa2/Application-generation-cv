<?php
// config.php : réglages de l'application et connexion à la base de données.
// Inclus au début de chaque page avec : include 'config.php';

session_start();

// ---------- Base de données (XAMPP : utilisateur root, sans mot de passe) ----------
$db_hote = 'localhost';
$db_nom  = 'generateur_cv';
$db_user = 'root';
$db_mdp  = '';

// PDO : objet qui représente la connexion à MySQL.
// ERRMODE_EXCEPTION : une erreur SQL arrête le script avec un message clair.
// FETCH_ASSOC : chaque ligne lue est un tableau associatif ['colonne' => valeur].
try {
    $bdd = new PDO(
        "mysql:host=$db_hote;dbname=$db_nom;charset=utf8mb4",
        $db_user,
        $db_mdp,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
} catch (PDOException $e) {
    die("Connexion à la base impossible : démarrez MySQL dans XAMPP et importez sql/generateur_cv.sql.");
}

// ---------- Envoi des emails (identifiants dans .env, jamais écrits dans le code) ----------
// Créez un fichier .env dans ce dossier à partir de .env.example (SMTP_HOST, SMTP_PORT, SMTP_USER,
// SMTP_PASS, SMTP_FROM). Sans .env, le lien de validation est affiché à l'écran.
function chargerEnv($chemin)
{
    if (!file_exists($chemin)) {
        return;
    }
    $lignes = file($chemin, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lignes as $ligne) {
        $ligne = trim($ligne);
        if ($ligne === '' || $ligne[0] === '#') {
            continue;
        }
        $parties = explode('=', $ligne, 2);
        if (count($parties) === 2) {
            $_ENV[trim($parties[0])] = trim($parties[1]);
        }
    }
}
chargerEnv(__DIR__ . '/.env');

// Adresse du site, pour construire le lien de validation envoyé par email
// Adresse du site, calculée automatiquement (reste correcte si le dossier est renommé)
$protocole = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$dossiers  = explode('/', dirname($_SERVER['SCRIPT_NAME']));
$url_site  = $protocole . '://' . $_SERVER['HTTP_HOST'] . implode('/', array_map('rawurlencode', $dossiers));

// ---------- Listes de choix (formulaire + vérification côté serveur) ----------
$types_experience   = ['Stage', 'Formation'];
$niveaux_competence = ['Débutant', 'Intermédiaire', 'Avancé'];
$niveaux_langue     = ['A1', 'A2', 'B1', 'B2', 'C1', 'C2', 'Langue maternelle'];
$roles              = ['candidat', 'entreprise'];
$statuts            = ['En attente', 'Acceptée', 'Refusée'];

// ---------- Photo ----------
$dossier_photos      = 'uploads/photos';
$extensions_photo    = ['jpg', 'jpeg', 'png'];
$taille_max_photo    = 2 * 1024 * 1024;   // 2 Mo

include 'fonctions.php';
