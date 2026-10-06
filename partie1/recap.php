<?php
// recap.php : traitement côté serveur des informations saisies

// La session garde les infos d'une page à l'autre (VALIDER et MODIFIER)
session_start();

$fichier_texte = 'etudiants.txt';

// Valeurs autorisées, pour vérifier qu'on n'a pas envoyé une valeur inventée
include 'listes.php';

// Nettoie une saisie : espaces, antislashs, balises HTML/JS.
// htmlspecialchars() se fait à l'affichage (sinon appliqué deux fois).
function nettoyer($valeur)
{
    $valeur = trim($valeur);
    $valeur = stripslashes($valeur);
    $valeur = strip_tags($valeur);
    return $valeur;
}

// Validation côté serveur : retourne [champ => message], vide si tout est correct
function validerDonnees($d, $filieres, $annees, $modules, $types_projet, $nb_projets_max)
{
    $erreurs = [];

    // Le "u" de la regex permet de gérer les accents
    if (empty($d['nom'])) {
        $erreurs['nom'] = "Le nom est obligatoire.";
    } elseif (!preg_match("/^[a-zA-ZÀ-ÿ' -]+$/u", $d['nom'])) {
        $erreurs['nom'] = "Le nom ne doit contenir que des lettres.";
    }

    if (empty($d['prenom'])) {
        $erreurs['prenom'] = "Le prénom est obligatoire.";
    } elseif (!preg_match("/^[a-zA-ZÀ-ÿ' -]+$/u", $d['prenom'])) {
        $erreurs['prenom'] = "Le prénom ne doit contenir que des lettres.";
    }

    if (empty($d['email'])) {
        $erreurs['email'] = "L'email est obligatoire.";
    } elseif (!preg_match("/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/", $d['email'])) {
        $erreurs['email'] = "L'email n'est pas valide (exemple : nom@gmail.com).";
    }

    // Facultatif : !== '' et pas empty(), car empty("0") vaut true
    if ($d['age'] !== '') {
        if (!preg_match("/^[0-9]+$/", $d['age']) || $d['age'] < 16 || $d['age'] > 60) {
            $erreurs['age'] = "L'âge doit être un nombre entre 16 et 60.";
        }
    }

    if ($d['telephone'] !== '') {
        if (!preg_match("/^0[5-7][0-9]{8}$/", $d['telephone'])) {
            $erreurs['telephone'] = "Le téléphone doit contenir 10 chiffres et commencer par 05, 06 ou 07.";
        }
    }

    if (empty($d['filiere'])) {
        $erreurs['filiere'] = "Veuillez choisir votre filière.";
    } elseif (!in_array($d['filiere'], $filieres)) {
        $erreurs['filiere'] = "Filière invalide.";
    }

    if (empty($d['annee'])) {
        $erreurs['annee'] = "Veuillez choisir votre année.";
    } elseif (!in_array($d['annee'], $annees)) {
        $erreurs['annee'] = "Année invalide.";
    }

    foreach ($d['modules'] as $module) {
        if (!in_array($module, $modules)) {
            $erreurs['modules'] = "Module invalide.";
        }
    }

    if (!preg_match("/^[0-9]+$/", $d['nb_projets']) || $d['nb_projets'] > $nb_projets_max) {
        $erreurs['nb_projets'] = "Nombre de projets invalide.";
    }

    // Dates au format AAAA-MM-JJ : on peut les comparer comme des chaînes
    foreach ($d['projets'] as $numero => $projet) {
        if (!in_array($projet['type'], $types_projet)) {
            $erreurs['projets'] = "Type de projet invalide.";
        } elseif ($projet['debut'] !== '' && $projet['fin'] !== '' && $projet['fin'] < $projet['debut']) {
            $erreurs['projets'] = $projet['type'] . " n°" . ($numero + 1)
                                . " : la date de fin doit être après la date de début.";
        }
    }

    return $erreurs;
}

// Affiche une valeur de façon sécurisée, ou "Non renseigné" si vide
function afficher($valeur)
{
    if (trim($valeur) === '') {
        return '<span class="vide">Non renseigné</span>';
    }
    return htmlspecialchars($valeur);
}

// "2026-09-29" devient "29/09/2026"
function formaterDate($date)
{
    if ($date === '') {
        return '';
    }
    return date('d/m/Y', strtotime($date));
}

// Construit le texte d'une fiche à écrire dans le fichier
function construireTexte($d)
{
    $texte  = "===== Fiche enregistrée le " . date('d/m/Y à H:i') . " =====\n";
    $texte .= "Nom : " . $d['nom'] . "\n";
    $texte .= "Prénom : " . $d['prenom'] . "\n";
    $texte .= "Âge : " . $d['age'] . "\n";
    $texte .= "Téléphone : " . $d['telephone'] . "\n";
    $texte .= "Email : " . $d['email'] . "\n";
    $texte .= "Filière : " . $d['filiere'] . "\n";
    $texte .= "Année : " . $d['annee'] . "\n";
    $texte .= "Modules suivis : " . implode(', ', $d['modules']) . "\n";
    $texte .= "Nombre de projets : " . $d['nb_projets'] . "\n";
    $texte .= "Remarques : " . $d['remarques'] . "\n";
    $texte .= "Fichier : " . $d['fichier_nom'] . "\n";
    $texte .= "Emplacement du fichier : " . ($d['fichier_chemin'] ?? '') . "\n";

    $texte .= "Projets et stages :\n";
    foreach ($d['projets'] as $numero => $projet) {
        $texte .= "  - " . $projet['type'] . " n°" . ($numero + 1)
                . " | Lieu : " . $projet['lieu']
                . " | Du " . formaterDate($projet['debut'])
                . " au " . formaterDate($projet['fin'])
                . " | Description : " . $projet['description'] . "\n";
    }

    $texte .= "Centres d'intérêt : " . $d['interets'] . "\n";
    $texte .= "Compétences : " . $d['competences'] . "\n";
    $texte .= "Langues : " . $d['langues'] . "\n\n";

    return $texte;
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'valider') {

    // ===== Cas 1 : clic sur VALIDER =====
    if (!isset($_SESSION['donnees'])) {
        header('Location: formulaire.php');
        exit;
    }
    $donnees = $_SESSION['donnees'];

    // Sécurité : on revérifie avant d'enregistrer
    if (count(validerDonnees($donnees, $filieres, $annees, $modules, $types_projet, $nb_projets_max)) > 0) {
        header('Location: formulaire.php?modifier=1');
        exit;
    }

    // Évite d'enregistrer deux fois la même fiche (F5 ou double clic)
    if (isset($_SESSION['enregistree'])) {
        $message = "Cette fiche est déjà enregistrée dans le fichier $fichier_texte.";

    // FILE_APPEND : ajoute à la fin sans effacer ; LOCK_EX : bloque le fichier pendant l'écriture
    } elseif (file_put_contents($fichier_texte, construireTexte($donnees), FILE_APPEND | LOCK_EX) !== false) {
        $_SESSION['enregistree'] = true;
        $message = "Les informations ont bien été enregistrées dans le fichier $fichier_texte.";
    } else {
        $message = "Erreur : impossible d'enregistrer les informations.";
    }

} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ===== Cas 2 : on arrive depuis le formulaire (Envoyer) =====

    // Fichier plus gros que "post_max_size" : PHP vide $_POST et $_FILES
    if (empty($_POST) && empty($_FILES)) {
        $_SESSION['erreurs'] = ['fichier' => "Le fichier dépasse la taille autorisée par le serveur."];
        header('Location: formulaire.php?modifier=1');
        exit;
    }

    // --- Fichier envoyé ---
    // PHP le met dans un dossier temporaire et le supprime à la fin de la page :
    // il faut le déplacer dans uploads avec move_uploaded_file().
    $fichier_nom    = '';
    $fichier_taille = '';
    $fichier_chemin = '';
    $erreur_fichier = '';

    $code_envoi = $_FILES['fichier']['error'] ?? UPLOAD_ERR_NO_FILE;

    if ($code_envoi === UPLOAD_ERR_OK) {
        $nom_origine = basename($_FILES['fichier']['name']);
        $extension   = strtolower(pathinfo($nom_origine, PATHINFO_EXTENSION));

        if (!in_array($extension, $extensions_autorisees)) {
            $erreur_fichier = "Type de fichier non autorisé (PDF, JPG, PNG, DOC ou DOCX uniquement).";
        } elseif ($_FILES['fichier']['size'] > $taille_max_fichier) {
            $erreur_fichier = "Le fichier est trop volumineux (5 Mo maximum).";
        } else {
            if (!is_dir($dossier_uploads)) {
                mkdir($dossier_uploads);
            }

            // "mon CV.pdf" devient "20261001_143005_mon_CV.pdf" : pas d'écrasement
            $nom_securise   = preg_replace("/[^a-zA-Z0-9._-]/", "_", $nom_origine);
            $fichier_chemin = $dossier_uploads . '/' . date('Ymd_His') . '_' . $nom_securise;

            if (move_uploaded_file($_FILES['fichier']['tmp_name'], $fichier_chemin)) {
                $fichier_nom    = $nom_origine;
                $fichier_taille = round($_FILES['fichier']['size'] / 1024, 2) . ' Ko';
            } else {
                $fichier_chemin = '';
                $erreur_fichier = "Erreur : le fichier n'a pas pu être enregistré.";
            }
        }
    } elseif ($code_envoi === UPLOAD_ERR_INI_SIZE || $code_envoi === UPLOAD_ERR_FORM_SIZE) {
        $erreur_fichier = "Le fichier dépasse la taille autorisée par le serveur.";
    } elseif ($code_envoi !== UPLOAD_ERR_NO_FILE) {
        $erreur_fichier = "Erreur pendant l'envoi du fichier.";
    }

    // Après MODIFIER sans nouveau fichier : on garde l'ancien
    if ($fichier_nom === '' && isset($_POST['garder_fichier']) && isset($_SESSION['donnees'])) {
        $fichier_nom    = $_SESSION['donnees']['fichier_nom'];
        $fichier_taille = $_SESSION['donnees']['fichier_taille'];
        $fichier_chemin = $_SESSION['donnees']['fichier_chemin'] ?? '';
    }

    // --- Projets et stages (case 0 = 1er projet, case 1 = 2ème...) ---
    $projet_types        = $_POST['projet_type'] ?? [];
    $projet_lieux        = $_POST['projet_lieu'] ?? [];
    $projet_debuts       = $_POST['projet_debut'] ?? [];
    $projet_fins         = $_POST['projet_fin'] ?? [];
    $projet_descriptions = $_POST['projet_description'] ?? [];

    $projets = [];
    for ($i = 0; $i < count($projet_types); $i++) {
        $projet = [
            'type'        => nettoyer($projet_types[$i]),
            'lieu'        => nettoyer($projet_lieux[$i] ?? ''),
            'debut'       => $projet_debuts[$i] ?? '',
            'fin'         => $projet_fins[$i] ?? '',
            'description' => nettoyer($projet_descriptions[$i] ?? ''),
        ];

        // On ignore les projets complètement vides
        if (trim($projet['lieu']) === '' && $projet['debut'] === ''
            && $projet['fin'] === '' && trim($projet['description']) === '') {
            continue;
        }

        $projets[] = $projet;
    }

    // ?? : valeur par défaut si le champ n'est pas envoyé (ex. radio non coché)
    $donnees = [
        'nom'            => nettoyer($_POST['nom'] ?? ''),
        'prenom'         => nettoyer($_POST['prenom'] ?? ''),
        'age'            => nettoyer($_POST['age'] ?? ''),
        'telephone'      => nettoyer($_POST['telephone'] ?? ''),
        'email'          => nettoyer($_POST['email'] ?? ''),
        'filiere'        => $_POST['filiere'] ?? '',
        'annee'          => $_POST['annee'] ?? '',
        'modules'        => $_POST['modules'] ?? [],
        'nb_projets'     => $_POST['nb_projets'] ?? '',
        'remarques'      => nettoyer($_POST['remarques'] ?? ''),
        'fichier_nom'    => $fichier_nom,
        'fichier_taille' => $fichier_taille,
        'fichier_chemin' => $fichier_chemin,
        'projets'        => $projets,
        'interets'       => nettoyer($_POST['interets'] ?? ''),
        'competences'    => nettoyer($_POST['competences'] ?? ''),
        'langues'        => nettoyer($_POST['langues'] ?? ''),
    ];

    $_SESSION['donnees'] = $donnees;

    // Nouvelles infos : pas encore enregistrées dans le fichier
    unset($_SESSION['enregistree']);

    // S'il y a des erreurs, retour au formulaire qui les affichera
    $erreurs = validerDonnees($donnees, $filieres, $annees, $modules, $types_projet, $nb_projets_max);

    if ($erreur_fichier !== '') {
        $erreurs['fichier'] = $erreur_fichier;
    }

    if (count($erreurs) > 0) {
        $_SESSION['erreurs'] = $erreurs;
        header('Location: formulaire.php?modifier=1');
        exit;
    }

} else {
    // ===== Cas 3 : accès direct à la page =====
    header('Location: formulaire.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Récapitulatif</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<div class="conteneur">
    <h1>Récapitulatif des informations</h1>

    <?php if ($message !== '') : ?>
        <p class="message"><?php echo htmlspecialchars($message); ?></p>
    <?php endif; ?>

    <div class="grille">

        <!-- ===== Renseignements Académiques ===== -->
        <fieldset class="bloc bloc-academique">
            <legend>Renseignements Académiques</legend>
            <table class="recap">
                <tr><th>Filière</th><td><?php echo afficher($donnees['filiere']); ?></td></tr>
                <tr><th>Année</th><td><?php echo afficher($donnees['annee']); ?></td></tr>
                <!-- implode() : tableau => texte "POO, BD, ..." -->
                <tr><th>Modules suivis</th><td><?php echo afficher(implode(', ', $donnees['modules'])); ?></td></tr>
                <tr><th>Nombre de projets</th><td><?php echo afficher($donnees['nb_projets']); ?></td></tr>
            </table>
        </fieldset>

        <!-- ===== Renseignements Personnels ===== -->
        <fieldset class="bloc bloc-personnel">
            <legend>Renseignements Personnels</legend>
            <table class="recap">
                <tr><th>Nom</th><td><?php echo afficher($donnees['nom']); ?></td></tr>
                <tr><th>Prénom</th><td><?php echo afficher($donnees['prenom']); ?></td></tr>
                <tr><th>Âge</th><td><?php echo afficher($donnees['age']); ?></td></tr>
                <tr><th>Téléphone</th><td><?php echo afficher($donnees['telephone']); ?></td></tr>
                <tr><th>Email</th><td><?php echo afficher($donnees['email']); ?></td></tr>
            </table>
        </fieldset>

        <!-- ===== Vos remarques ===== -->
        <fieldset class="bloc bloc-remarques">
            <legend>Vos remarques</legend>
            <table class="recap">
                <!-- nl2br() garde les retours à la ligne -->
                <tr><th>Remarques</th><td><?php echo nl2br(afficher($donnees['remarques'])); ?></td></tr>
                <tr>
                    <th>Fichier</th>
                    <td>
                        <?php if (($donnees['fichier_chemin'] ?? '') !== '') : ?>
                            <a href="<?php echo htmlspecialchars($donnees['fichier_chemin']); ?>" target="_blank">
                                <?php echo htmlspecialchars($donnees['fichier_nom']); ?>
                            </a>
                        <?php else : ?>
                            <?php echo afficher($donnees['fichier_nom']); ?>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php if ($donnees['fichier_taille'] !== '') : ?>
                    <tr><th>Taille</th><td><?php echo $donnees['fichier_taille']; ?></td></tr>
                <?php endif; ?>
            </table>
        </fieldset>

        <!-- ===== Projets et stages ===== -->
        <fieldset class="bloc bloc-projets">
            <legend>Projets et stages réalisés</legend>

            <?php if (count($donnees['projets']) === 0) : ?>
                <p class="vide">Aucun projet ou stage renseigné</p>
            <?php else : ?>
                <?php foreach ($donnees['projets'] as $numero => $projet) : ?>
                    <div class="projet">
                        <p class="projet-titre"><?php echo htmlspecialchars($projet['type']) . ' n°' . ($numero + 1); ?></p>
                        <table class="recap">
                            <tr><th>Lieu</th><td><?php echo afficher($projet['lieu']); ?></td></tr>
                            <tr><th>Date de début</th><td><?php echo afficher(formaterDate($projet['debut'])); ?></td></tr>
                            <tr><th>Date de fin</th><td><?php echo afficher(formaterDate($projet['fin'])); ?></td></tr>
                            <tr><th>Description</th><td><?php echo nl2br(afficher($projet['description'])); ?></td></tr>
                        </table>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </fieldset>

        <!-- ===== Centres d'intérêt ===== -->
        <fieldset class="bloc bloc-interets">
            <legend>Centres d'intérêt</legend>
            <table class="recap">
                <tr><th>Centres d'intérêt</th><td><?php echo nl2br(afficher($donnees['interets'])); ?></td></tr>
            </table>
        </fieldset>

        <!-- ===== Compétences et Langues ===== -->
        <fieldset class="bloc bloc-competences">
            <legend>Compétences et Langues</legend>
            <table class="recap">
                <tr><th>Compétences</th><td><?php echo nl2br(afficher($donnees['competences'])); ?></td></tr>
                <tr><th>Langues</th><td><?php echo nl2br(afficher($donnees['langues'])); ?></td></tr>
            </table>
        </fieldset>

    </div>

    <div class="boutons">
        <!-- VALIDER : action=valider => enregistrement dans le fichier -->
        <form action="recap.php" method="post">
            <button type="submit" name="action" value="valider" class="btn btn-principal">VALIDER</button>
        </form>

        <!-- MODIFIER : ?modifier=1 => formulaire pré-rempli -->
        <a href="formulaire.php?modifier=1" class="btn btn-secondaire">MODIFIER</a>
    </div>
</div>

</body>
</html>
