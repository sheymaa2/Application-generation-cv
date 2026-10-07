<?php
// inscription.php : création du compte. L'email sert d'identifiant et doit être validé.
// Formulaire et traitement dans le même fichier (technique du cours : action="").
include 'config.php';

$erreurs = [];
$compte_cree   = false;   // true : on affiche la carte « vérifiez vos emails » à la place du formulaire
$email_envoye  = false;
$lien_affiche  = '';
$email_inscrit = '';

// Valeurs ré-affichées dans le formulaire en cas d'erreur
$email          = '';
$role           = 'candidat';
$nom_entreprise = '';

if (isset($_POST['inscrire'])) {

    $email          = nettoyer($_POST['email'] ?? '');
    $role           = $_POST['role'] ?? '';
    $nom_entreprise = nettoyer($_POST['nom_entreprise'] ?? '');
    $mdp            = $_POST['mdp'] ?? '';             // pas nettoyé : il ne sera jamais affiché
    $mdp2           = $_POST['mdp2'] ?? '';

    if (!in_array($role, $roles)) {
        $erreurs['role'] = "Choisissez le type de compte.";
    }

    // 1) format de l'email
    if (empty($email)) {
        $erreurs['email'] = "L'email est obligatoire.";
    } elseif (!emailValide($email) || strlen($email) > 100) {
        $erreurs['email'] = "L'email n'est pas valide (exemple : nom@gmail.com).";
    } else {
        // 2) unicité : l'email est la clé primaire, il ne peut exister qu'une fois
        $requete = $bdd->prepare("SELECT email FROM utilisateur WHERE email = ?");
        $requete->execute([$email]);
        if ($requete->fetch()) {
            $erreurs['email'] = "Un compte existe déjà avec cet email.";
        }
    }

    if ($role === 'entreprise' && empty($nom_entreprise)) {
        $erreurs['nom_entreprise'] = "Le nom de l'entreprise est obligatoire.";
    }

    if (strlen($mdp) < 8) {
        $erreurs['mdp'] = "Le mot de passe doit contenir au moins 8 caractères.";
    } elseif ($mdp !== $mdp2) {
        $erreurs['mdp2'] = "Les deux mots de passe ne sont pas identiques.";
    }

    if (count($erreurs) === 0) {
        // 3) lien de confirmation : code aléatoire de 64 caractères, impossible à deviner
        $token = bin2hex(random_bytes(32));

        // password_hash : on ne stocke JAMAIS le mot de passe en clair
        $requete = $bdd->prepare(
            "INSERT INTO utilisateur (email, mot_de_passe, role, nom, token_validation)
             VALUES (?, ?, ?, ?, ?)"
        );
        $requete->execute([
            $email,
            password_hash($mdp, PASSWORD_DEFAULT),
            $role,
            $role === 'entreprise' ? $nom_entreprise : null,
            $token,
        ]);

        $lien = $url_site . '/valider.php?token=' . $token;
        $corps = "Bonjour,\n\nPour activer votre compte, cliquez sur ce lien :\n$lien";

        // Si le serveur d'envoi d'emails (SMTP) n'est pas configuré dans .env, envoyerEmail() retourne false :
        // c'est le cas sur un PC avec XAMPP. On affiche alors le lien à l'écran (mode démonstration)
        // pour pouvoir quand même tester la validation.
        $email_envoye = envoyerEmail($email, "Validez votre adresse email", $corps);
        if (!$email_envoye) {
            $lien_affiche = $lien;
        }

        $compte_cree   = true;
        $email_inscrit = $email;
    }
}

afficherEntete('Inscription', '', true);
?>

<div class="auth">
    <?php afficherPanneauPresentation(); ?>

    <section class="auth-carte">

    <?php if ($compte_cree) : ?>

        <!-- ===== Compte créé : on demande de valider l'email ===== -->
        <div class="etat-vide">
            <span class="pastille-icone"><?php echo icone('mail'); ?></span>
            <h2>Vérifiez votre adresse email</h2>

            <?php if ($email_envoye) : ?>
                <p>Un lien de validation a été envoyé à <strong><?php echo htmlspecialchars($email_inscrit); ?></strong>.
                   Cliquez dessus pour activer votre compte.</p>
            <?php else : ?>
                <p>Votre compte <strong><?php echo htmlspecialchars($email_inscrit); ?></strong> est créé.</p>
                <div class="alerte alerte-info">
                    <?php echo icone('alert'); ?>
                    <p><strong>Mode démonstration :</strong> l'envoi d'emails n'est pas configuré sur ce serveur
                       (fichier .env absent). Le lien que vous auriez reçu par email est donc proposé ici.</p>
                </div>
                <a href="<?php echo htmlspecialchars($lien_affiche); ?>" class="btn btn-principal btn-bloc">
                    <?php echo icone('check'); ?> Valider mon adresse email
                </a>
            <?php endif; ?>

            <p class="info">Déjà validé ? <a href="connexion.php">Se connecter</a></p>
        </div>

    <?php else : ?>

        <h2>Créer un compte</h2>
        <p class="info">Votre adresse email sera votre identifiant. Elle devra être validée.</p>

        <?php if (count($erreurs) > 0) {
            afficherMessage("Certains champs sont manquants ou incorrects.", 'erreur');
        } ?>

        <form action="" method="post">

            <p class="titre-champ">Vous êtes :</p>
            <!-- Boutons radio présentés sous forme de cartes -->
            <div class="choix-cartes">
                <label class="carte-choix">
                    <input type="radio" name="role" value="candidat" <?php if ($role === 'candidat') echo 'checked'; ?>>
                    <?php echo icone('user'); ?>
                    <strong>Étudiant</strong>
                    <span>Je crée mon CV</span>
                </label>
                <label class="carte-choix">
                    <input type="radio" name="role" value="entreprise" <?php if ($role === 'entreprise') echo 'checked'; ?>>
                    <?php echo icone('building'); ?>
                    <strong>Entreprise</strong>
                    <span>Je recrute des stagiaires</span>
                </label>
            </div>
            <?php afficherErreur($erreurs, 'role'); ?>

            <!-- Affiché seulement si « Entreprise » est choisi (script.js) -->
            <div class="champ" id="champ-entreprise">
                <label for="nom_entreprise">Nom de l'entreprise <span class="obligatoire">*</span></label>
                <input type="text" id="nom_entreprise" name="nom_entreprise" maxlength="100"
                       value="<?php echo htmlspecialchars($nom_entreprise); ?>">
                <?php afficherErreur($erreurs, 'nom_entreprise'); ?>
            </div>

            <div class="champ">
                <label for="email">Adresse email <span class="obligatoire">*</span></label>
                <input type="email" id="email" name="email" required maxlength="100" placeholder="nom@exemple.com"
                       value="<?php echo htmlspecialchars($email); ?>">
                <?php afficherErreur($erreurs, 'email'); ?>
            </div>

            <div class="champ-duo">
                <div class="champ">
                    <label for="mdp">Mot de passe <span class="obligatoire">*</span></label>
                    <input type="password" id="mdp" name="mdp" required minlength="8" placeholder="8 caractères min.">
                    <?php afficherErreur($erreurs, 'mdp'); ?>
                </div>
                <div class="champ">
                    <label for="mdp2">Confirmation <span class="obligatoire">*</span></label>
                    <input type="password" id="mdp2" name="mdp2" required minlength="8">
                    <?php afficherErreur($erreurs, 'mdp2'); ?>
                </div>
            </div>

            <button type="submit" name="inscrire" class="btn btn-principal btn-bloc">Créer mon compte</button>
        </form>

        <p class="info centre">Déjà inscrit ? <a href="connexion.php">Se connecter</a></p>

    <?php endif; ?>

    </section>
</div>

<?php afficherPied(); ?>
