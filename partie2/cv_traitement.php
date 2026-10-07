<?php
// cv_traitement.php : vérification côté serveur du CV puis enregistrement dans la base
include 'config.php';
exigerConnexion('candidat');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: cv_formulaire.php');
    exit;
}

$email = $_SESSION['email'];

// Photo déjà enregistrée (gardée si on n'en envoie pas une nouvelle)
$requete = $bdd->prepare("SELECT photo FROM utilisateur WHERE email = ?");
$requete->execute([$email]);
$photo_actuelle = $requete->fetch()['photo'];

// Photo plus grosse que "post_max_size" : PHP vide $_POST et $_FILES
if (empty($_POST) && empty($_FILES)) {
    $_SESSION['erreurs'] = ['photo' => "La photo dépasse la taille autorisée par le serveur."];
    header('Location: cv_formulaire.php');
    exit;
}

// ---------- 1) Récupération et nettoyage des champs simples ----------
$cv = [
    'nom'       => nettoyer($_POST['nom'] ?? ''),
    'prenom'    => nettoyer($_POST['prenom'] ?? ''),
    'telephone' => nettoyer($_POST['telephone'] ?? ''),
    'adresse'   => nettoyer($_POST['adresse'] ?? ''),
    'photo'     => $photo_actuelle,
];

// ---------- 2) Récupération des listes (une case par ligne du formulaire) ----------
// Les lignes entièrement vides sont ignorées.

$cv['experiences'] = [];
$exp_titres = $_POST['exp_titre'] ?? [];
for ($i = 0; $i < count($exp_titres); $i++) {
    $experience = [
        'type'        => $_POST['exp_type'][$i] ?? '',
        'titre'       => nettoyer($exp_titres[$i]),
        'organisme'   => nettoyer($_POST['exp_organisme'][$i] ?? ''),
        'lieu'        => nettoyer($_POST['exp_lieu'][$i] ?? ''),
        'date_debut'  => $_POST['exp_debut'][$i] ?? '',
        'date_fin'    => $_POST['exp_fin'][$i] ?? '',
        'description' => nettoyer($_POST['exp_description'][$i] ?? ''),
    ];
    if ($experience['titre'] === '' && $experience['organisme'] === '' && $experience['lieu'] === ''
        && $experience['date_debut'] === '' && $experience['date_fin'] === '' && $experience['description'] === '') {
        continue;
    }
    $cv['experiences'][] = $experience;
}

$cv['competences'] = [];
$comp_libelles = $_POST['comp_libelle'] ?? [];
for ($i = 0; $i < count($comp_libelles); $i++) {
    $libelle = nettoyer($comp_libelles[$i]);
    if ($libelle !== '') {
        $cv['competences'][] = ['libelle' => $libelle, 'niveau' => $_POST['comp_niveau'][$i] ?? ''];
    }
}

$cv['langues'] = [];
$langue_noms = $_POST['langue_nom'] ?? [];
for ($i = 0; $i < count($langue_noms); $i++) {
    $nom_langue = nettoyer($langue_noms[$i]);
    if ($nom_langue !== '') {
        $cv['langues'][] = ['nom_langue' => $nom_langue, 'niveau' => $_POST['langue_niveau'][$i] ?? ''];
    }
}

$cv['interets'] = [];
foreach ($_POST['interet_libelle'] ?? [] as $libelle) {
    $libelle = nettoyer($libelle);
    if ($libelle !== '') {
        $cv['interets'][] = ['libelle' => $libelle];
    }
}

// ---------- 3) Vérifications (ordre du cours : vide ? puis format avec preg_match) ----------
$erreurs = [];

if (empty($cv['nom'])) {
    $erreurs['nom'] = "Le nom est obligatoire.";
} elseif (!preg_match("/^[a-zA-ZÀ-ÿ' -]{1,50}$/u", $cv['nom'])) {
    $erreurs['nom'] = "Le nom ne doit contenir que des lettres (50 maximum).";
}

if (empty($cv['prenom'])) {
    $erreurs['prenom'] = "Le prénom est obligatoire.";
} elseif (!preg_match("/^[a-zA-ZÀ-ÿ' -]{1,50}$/u", $cv['prenom'])) {
    $erreurs['prenom'] = "Le prénom ne doit contenir que des lettres (50 maximum).";
}

if (empty($cv['telephone'])) {
    $erreurs['telephone'] = "Le téléphone est obligatoire.";
} elseif (!preg_match("/^(0|\+212)[5-7][0-9]{8}$/", $cv['telephone'])) {
    $erreurs['telephone'] = "Téléphone invalide (ex. : 0612345678 ou +212612345678).";
}

if (empty($cv['adresse'])) {
    $erreurs['adresse'] = "L'adresse est obligatoire.";
} elseif (strlen($cv['adresse']) > 255) {
    $erreurs['adresse'] = "L'adresse est trop longue (255 caractères maximum).";
}

// Stages et formations : au moins un, intitulé + date de début obligatoires, fin >= début
if (count($cv['experiences']) === 0) {
    $erreurs['experiences'] = "Ajoutez au moins un stage ou une formation.";
}
foreach ($cv['experiences'] as $numero => $e) {
    $nom_ligne = "Ligne " . ($numero + 1);
    if (!in_array($e['type'], $types_experience)) {
        $erreurs['experiences'] = "$nom_ligne : type invalide.";
    } elseif ($e['titre'] === '') {
        $erreurs['experiences'] = "$nom_ligne : l'intitulé est obligatoire.";
    } elseif (!preg_match("/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/", $e['date_debut'])) {
        $erreurs['experiences'] = "$nom_ligne : la date de début est obligatoire.";
    } elseif ($e['date_fin'] !== '' && !preg_match("/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/", $e['date_fin'])) {
        $erreurs['experiences'] = "$nom_ligne : date de fin invalide.";
    } elseif ($e['date_fin'] !== '' && $e['date_fin'] < $e['date_debut']) {
        // Dates au format AAAA-MM-JJ : on peut les comparer comme des chaînes
        $erreurs['experiences'] = "$nom_ligne : la date de fin doit être après la date de début.";
    }
}

if (count($cv['competences']) === 0) {
    $erreurs['competences'] = "Ajoutez au moins une compétence.";
}
foreach ($cv['competences'] as $c) {
    if (!in_array($c['niveau'], $niveaux_competence)) {
        $erreurs['competences'] = "Niveau de compétence invalide.";
    }
}

if (count($cv['langues']) === 0) {
    $erreurs['langues'] = "Ajoutez au moins une langue.";
}
foreach ($cv['langues'] as $l) {
    if (!in_array($l['niveau'], $niveaux_langue)) {
        $erreurs['langues'] = "Niveau de langue invalide.";
    }
}

if (count($cv['interets']) === 0) {
    $erreurs['interets'] = "Ajoutez au moins un centre d'intérêt.";
}

// ---------- 4) Photo ----------
$code_envoi = $_FILES['photo']['error'] ?? UPLOAD_ERR_NO_FILE;
$nouvelle_photo = '';

if ($code_envoi === UPLOAD_ERR_OK) {
    $extension = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));

    if (!in_array($extension, $extensions_photo)) {
        $erreurs['photo'] = "La photo doit être au format JPG ou PNG.";
    } elseif ($_FILES['photo']['size'] > $taille_max_photo) {
        $erreurs['photo'] = "La photo est trop volumineuse (2 Mo maximum).";
    } elseif (getimagesize($_FILES['photo']['tmp_name']) === false) {
        // getimagesize lit le contenu : un faux .jpg (ex. un script renommé) est refusé
        $erreurs['photo'] = "Le fichier envoyé n'est pas une image.";
    } elseif (count($erreurs) === 0) {
        // On ne déplace la photo que si tout le reste est correct
        if (!is_dir($dossier_photos)) {
            mkdir($dossier_photos, 0777, true);
        }
        // Nom unique, sans le nom d'origine : ex. "20261005_143005_a1b2c3d4.jpg"
        $nouvelle_photo = $dossier_photos . '/' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
        if (!move_uploaded_file($_FILES['photo']['tmp_name'], $nouvelle_photo)) {
            $erreurs['photo'] = "Erreur : la photo n'a pas pu être enregistrée.";
            $nouvelle_photo = '';
        }
    }
} elseif ($code_envoi === UPLOAD_ERR_INI_SIZE || $code_envoi === UPLOAD_ERR_FORM_SIZE) {
    $erreurs['photo'] = "La photo dépasse la taille autorisée par le serveur.";
} elseif ($code_envoi !== UPLOAD_ERR_NO_FILE) {
    $erreurs['photo'] = "Erreur pendant l'envoi de la photo.";
} elseif (empty($photo_actuelle)) {
    $erreurs['photo'] = "La photo est obligatoire.";
}

// ---------- 5) Erreurs : retour au formulaire avec la saisie ----------
if (count($erreurs) > 0) {
    $_SESSION['erreurs']   = $erreurs;
    $_SESSION['cv_saisie'] = $cv;
    header('Location: cv_formulaire.php');
    exit;
}

if ($nouvelle_photo !== '') {
    $cv['photo'] = $nouvelle_photo;
}

// ---------- 6) Enregistrement dans la base ----------
// Transaction : soit toutes les requêtes réussissent, soit aucune (pas de CV à moitié enregistré).
// Pour les listes, le plus simple : on efface les anciennes lignes puis on insère les nouvelles.
try {
    $bdd->beginTransaction();

    $requete = $bdd->prepare(
        "UPDATE utilisateur SET nom = ?, prenom = ?, telephone = ?, adresse = ?, photo = ? WHERE email = ?"
    );
    $requete->execute([$cv['nom'], $cv['prenom'], $cv['telephone'], $cv['adresse'], $cv['photo'], $email]);

    foreach (['experience', 'competence', 'langue', 'centre_interet'] as $table) {
        $bdd->prepare("DELETE FROM $table WHERE email = ?")->execute([$email]);
    }

    $requete = $bdd->prepare(
        "INSERT INTO experience (email, type, titre, organisme, lieu, date_debut, date_fin, description)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
    );
    foreach ($cv['experiences'] as $e) {
        // Champ facultatif vide => NULL dans la base
        $requete->execute([
            $email, $e['type'], $e['titre'],
            $e['organisme'] !== '' ? $e['organisme'] : null,
            $e['lieu'] !== '' ? $e['lieu'] : null,
            $e['date_debut'],
            $e['date_fin'] !== '' ? $e['date_fin'] : null,
            $e['description'] !== '' ? $e['description'] : null,
        ]);
    }

    $requete = $bdd->prepare("INSERT INTO competence (email, libelle, niveau) VALUES (?, ?, ?)");
    foreach ($cv['competences'] as $c) {
        $requete->execute([$email, $c['libelle'], $c['niveau']]);
    }

    $requete = $bdd->prepare("INSERT INTO langue (email, nom_langue, niveau) VALUES (?, ?, ?)");
    foreach ($cv['langues'] as $l) {
        $requete->execute([$email, $l['nom_langue'], $l['niveau']]);
    }

    $requete = $bdd->prepare("INSERT INTO centre_interet (email, libelle) VALUES (?, ?)");
    foreach ($cv['interets'] as $i) {
        $requete->execute([$email, $i['libelle']]);
    }

    $bdd->commit();
} catch (PDOException $e) {
    $bdd->rollBack();
    $_SESSION['erreurs']   = ['nom' => "Erreur pendant l'enregistrement, veuillez réessayer."];
    $_SESSION['cv_saisie'] = $cv;
    header('Location: cv_formulaire.php');
    exit;
}

// L'ancienne photo, remplacée, ne sert plus : on la supprime du disque
if ($nouvelle_photo !== '' && !empty($photo_actuelle) && file_exists($photo_actuelle)) {
    unlink($photo_actuelle);
}

$_SESSION['message'] = "Votre CV a bien été enregistré.";
header('Location: cv_apercu.php');
exit;
