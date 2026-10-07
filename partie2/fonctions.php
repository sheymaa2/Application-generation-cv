<?php
// fonctions.php : fonctions réutilisées par toutes les pages (inclus par config.php)

// PHPMailer : bibliothèque d'envoi d'emails (fichiers du dossier src de PHPMailer)
require_once __DIR__ . '/lib/PHPMailer/Exception.php';
require_once __DIR__ . '/lib/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/lib/PHPMailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;

// Nettoie une saisie (modèle du cours) : espaces, antislashs, balises HTML/JS.
// htmlspecialchars() est fait à l'affichage, pour ne pas l'appliquer deux fois.
function nettoyer($valeur)
{
    $valeur = trim($valeur);
    $valeur = stripslashes($valeur);
    $valeur = strip_tags($valeur);
    return $valeur;
}

// Vérifie le FORMAT d'un email avec une expression régulière (comme en Partie 1)
function emailValide($email)
{
    return preg_match("/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/", $email) === 1;
}

// Affiche le message d'erreur d'un champ, s'il y en a un
function afficherErreur($erreurs, $champ)
{
    if (isset($erreurs[$champ])) {
        echo '<p class="erreur">' . htmlspecialchars($erreurs[$champ]) . '</p>';
    }
}

// "2026-09-29" devient "29/09/2026"
function formaterDate($date)
{
    if ($date === null || $date === '') {
        return '';
    }
    return date('d/m/Y', strtotime($date));
}

// Envoie un email avec PHPMailer (réglages SMTP lus dans .env).
// Retourne true si l'email est parti, false sinon (pas de .env, erreur SMTP...).
function envoyerEmail($destinataire, $sujet, $corps)
{
    if (empty($_ENV['SMTP_HOST'])) {
        return false;
    }

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = $_ENV['SMTP_HOST'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $_ENV['SMTP_USER'];
        $mail->Password   = $_ENV['SMTP_PASS'];
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = (int) $_ENV['SMTP_PORT'];
        $mail->CharSet    = 'UTF-8';

        $mail->setFrom($_ENV['SMTP_FROM'], 'Générateur de CV');
        $mail->addAddress($destinataire);
        $mail->Subject = $sujet;
        $mail->Body    = $corps;

        $mail->send();
        return true;
    } catch (Exception $e) {
        return false;
    }
}

// Bloque l'accès aux pages réservées : il faut être connecté, avec le bon rôle
function exigerConnexion($role)
{
    if (!isset($_SESSION['email']) || $_SESSION['role'] !== $role) {
        header('Location: connexion.php');
        exit;
    }
}

// Lit tout le CV d'un utilisateur dans la base : retourne un tableau, ou null s'il n'existe pas
function lireCV($bdd, $email)
{
    $requete = $bdd->prepare("SELECT * FROM utilisateur WHERE email = ? AND role = 'candidat'");
    $requete->execute([$email]);
    $cv = $requete->fetch();

    if (!$cv) {
        return null;
    }

    // ORDER BY date_debut DESC : l'expérience la plus récente en premier
    $requete = $bdd->prepare("SELECT * FROM experience WHERE email = ? ORDER BY date_debut DESC");
    $requete->execute([$email]);
    $cv['experiences'] = $requete->fetchAll();

    $requete = $bdd->prepare("SELECT * FROM competence WHERE email = ? ORDER BY id_competence");
    $requete->execute([$email]);
    $cv['competences'] = $requete->fetchAll();

    $requete = $bdd->prepare("SELECT * FROM langue WHERE email = ? ORDER BY id_langue");
    $requete->execute([$email]);
    $cv['langues'] = $requete->fetchAll();

    $requete = $bdd->prepare("SELECT * FROM centre_interet WHERE email = ? ORDER BY id_centre");
    $requete->execute([$email]);
    $cv['interets'] = $requete->fetchAll();

    return $cv;
}

// Le CV contient-il toutes les informations obligatoires de l'énoncé ?
function cvComplet($cv)
{
    return $cv !== null
        && !empty($cv['nom']) && !empty($cv['prenom'])
        && !empty($cv['telephone']) && !empty($cv['adresse'])
        && !empty($cv['photo'])
        && count($cv['experiences']) > 0
        && count($cv['competences']) > 0
        && count($cv['langues']) > 0
        && count($cv['interets']) > 0;
}

// Petites icônes dessinées en SVG (HTML pur, aucune bibliothèque) : icone('mail'), icone('user')...
function icone($nom)
{
    $dessins = [
        'user'      => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
        'building'  => '<rect x="4" y="2" width="16" height="20" rx="2"/><path d="M9 22v-4h6v4M8 6h.01M12 6h.01M16 6h.01M8 10h.01M12 10h.01M16 10h.01M8 14h.01M12 14h.01M16 14h.01"/>',
        'mail'      => '<rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-10 6L2 7"/>',
        'phone'     => '<path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1.9.4 1.8.7 2.7a2 2 0 0 1-.5 2.1L8 9.8a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.4c.9.3 1.8.6 2.7.7a2 2 0 0 1 1.7 2z"/>',
        'pin'       => '<path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0z"/><circle cx="12" cy="10" r="3"/>',
        'briefcase' => '<rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/>',
        'star'      => '<path d="m12 2 3.1 6.3 6.9 1-5 4.9 1.2 6.8L12 17.8 5.8 21l1.2-6.8-5-4.9 6.9-1z"/>',
        'globe'     => '<circle cx="12" cy="12" r="10"/><path d="M2 12h20M12 2a15 15 0 0 1 0 20 15 15 0 0 1 0-20"/>',
        'heart'     => '<path d="M19 14c1.5-1.5 3-3.2 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.8 0-3 .5-4.5 2-1.5-1.5-2.7-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4 3 5.5l7 7z"/>',
        'camera'    => '<path d="M14.5 4h-5L7 7H4a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-3z"/><circle cx="12" cy="13" r="3"/>',
        'download'  => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l5 5 5-5M12 15V3"/>',
        'edit'      => '<path d="M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/>',
        'calendar'  => '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>',
        'clock'     => '<circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>',
        'inbox'     => '<path d="M22 12h-6l-2 3h-4l-2-3H2"/><path d="M5.5 5.1 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.5-6.9A2 2 0 0 0 16.8 4H7.2a2 2 0 0 0-1.7 1.1z"/>',
        'check'     => '<path d="M20 6 9 17l-5-5"/>',
        'alert'     => '<circle cx="12" cy="12" r="10"/><path d="M12 8v4M12 16h.01"/>',
        'logout'    => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/>',
        'plus'      => '<path d="M12 5v14M5 12h14"/>',
        'x'         => '<path d="M18 6 6 18M6 6l12 12"/>',
        'eye'       => '<path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>',
        'users'     => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.9M16 3.1a4 4 0 0 1 0 7.8"/>',
        'send'      => '<path d="m22 2-7 20-4-9-9-4z"/><path d="M22 2 11 13"/>',
        'lock'      => '<rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
        'file'      => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M8 13h8M8 17h5"/>',
    ];
    return '<svg class="icone" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"'
         . ' stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ($dessins[$nom] ?? '') . '</svg>';
}

// Message coloré avec icône : $type = 'succes', 'erreur' ou 'info'
function afficherMessage($texte, $type = 'succes')
{
    $icones = ['succes' => 'check', 'erreur' => 'alert', 'info' => 'mail'];
    echo '<div class="alerte alerte-' . $type . '">' . icone($icones[$type]) . '<p>' . htmlspecialchars($texte) . '</p></div>';
}

// Niveau d'une compétence ou d'une langue en pourcentage (pour les barres de niveau)
function pourcentageNiveau($niveau)
{
    $echelle = [
        'Débutant' => 33, 'Intermédiaire' => 66, 'Avancé' => 100,
        'A1' => 17, 'A2' => 33, 'B1' => 50, 'B2' => 67, 'C1' => 83, 'C2' => 100, 'Langue maternelle' => 100,
    ];
    return $echelle[$niveau] ?? 50;
}

// Classe CSS de la pastille selon le statut d'une candidature
function classeStatut($statut)
{
    if ($statut === 'Acceptée') {
        return 'badge-vert';
    } elseif ($statut === 'Refusée') {
        return 'badge-rouge';
    }
    return 'badge-orange';
}

// Un lien du menu ; il est mis en valeur quand c'est la page affichée
function lienMenu($page, $texte, $nom_icone)
{
    // basename($_SERVER['PHP_SELF']) = nom du fichier PHP en cours, ex. "offres.php"
    $classe = (basename($_SERVER['PHP_SELF']) === $page) ? 'actif' : '';
    echo '<a href="' . $page . '" class="' . $classe . '">' . icone($nom_icone) . '<span>' . $texte . '</span></a>';
}

// Haut de page commun : <head>, menu selon la personne connectée, titre de la page
// $page_auth = true pour les pages de connexion / inscription (mise en page en deux panneaux)
function afficherEntete($titre, $sous_titre = '', $page_auth = false)
{
    ?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($titre); ?></title>
    <!-- Polices Google : Poppins (titres) et Inter (texte) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body>

<header class="menu">
    <a href="index.php" class="menu-logo">
        <span class="logo-carre">CV</span>
    </a>
    <nav class="menu-liens">
        <?php if (!isset($_SESSION['email'])) {
            lienMenu('connexion.php', 'Connexion', 'lock');
            lienMenu('inscription.php', 'Inscription', 'user');
        } elseif ($_SESSION['role'] === 'candidat') {
            lienMenu('cv_formulaire.php', 'Mon CV', 'edit');
            lienMenu('cv_apercu.php', 'Aperçu', 'eye');
            lienMenu('offres.php', 'Offres de stage', 'briefcase');
        } else {
            lienMenu('entreprise_offres.php', 'Mes offres', 'briefcase');
        } ?>
        <?php if (isset($_SESSION['email'])) : ?>
            <span class="menu-compte" title="<?php echo htmlspecialchars($_SESSION['email']); ?>">
                <?php echo icone($_SESSION['role'] === 'candidat' ? 'user' : 'building'); ?>
                <span><?php echo htmlspecialchars($_SESSION['email']); ?></span>
            </span>
            <a href="deconnexion.php" class="menu-sortie" title="Déconnexion"><?php echo icone('logout'); ?><span>Déconnexion</span></a>
        <?php endif; ?>
    </nav>
</header>

<main class="conteneur <?php if ($page_auth) echo 'conteneur-auth'; ?>">
    <?php if (!$page_auth) : ?>
        <div class="titre-page">
            <h1><?php echo htmlspecialchars($titre); ?></h1>
            <?php if ($sous_titre !== '') : ?>
                <p><?php echo htmlspecialchars($sous_titre); ?></p>
            <?php endif; ?>
        </div>
    <?php endif; ?>
    <?php
}

// Panneau de présentation (à gauche) des pages connexion et inscription
function afficherPanneauPresentation()
{
    ?>
    <section class="auth-presentation">
        <h2>Votre CV professionnel, en quelques minutes.</h2>
        <p>Remplissez vos informations une seule fois, générez un PDF soigné et postulez aux offres de stage.</p>
        <ul>
            <li><?php echo icone('edit'); ?> Saisie guidée, rubrique par rubrique</li>
            <li><?php echo icone('file'); ?> CV au format PDF en un clic</li>
            <li><?php echo icone('briefcase'); ?> Candidatures de stage suivies en direct</li>
        </ul>
    </section>
    <?php
}

// Fin de page commune : ferme le contenu et charge le JavaScript
function afficherPied()
{
    ?>
</main>
<script src="script.js"></script>
</body>
</html>
    <?php
}
