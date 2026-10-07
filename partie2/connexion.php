<?php
// connexion.php : connexion avec l'email (identifiant) et le mot de passe
include 'config.php';

$erreur = '';
$email  = '';

if (isset($_POST['connecter'])) {
    $email = nettoyer($_POST['email'] ?? '');
    $mdp   = $_POST['mdp'] ?? '';

    $requete = $bdd->prepare("SELECT email, mot_de_passe, role, email_valide FROM utilisateur WHERE email = ?");
    $requete->execute([$email]);
    $utilisateur = $requete->fetch();

    // password_verify compare le mot de passe tapé avec le hash enregistré.
    // Même message dans les deux cas : on ne dit pas si c'est l'email ou le mot de passe qui est faux.
    if (!$utilisateur || !password_verify($mdp, $utilisateur['mot_de_passe'])) {
        $erreur = "Email ou mot de passe incorrect.";
    } elseif ($utilisateur['email_valide'] != 1) {
        $erreur = "Votre email n'est pas encore validé : cliquez sur le lien reçu par email.";
    } else {
        // Nouvel identifiant de session après connexion (protège contre le vol de session)
        session_regenerate_id(true);
        $_SESSION['email'] = $utilisateur['email'];
        $_SESSION['role']  = $utilisateur['role'];

        header('Location: index.php');
        exit;
    }
}

afficherEntete('Connexion', '', true);
?>

<div class="auth">
    <?php afficherPanneauPresentation(); ?>

    <section class="auth-carte">
        <h2>Bon retour !</h2>
        <p class="info">Connectez-vous avec votre adresse email.</p>

        <?php if ($erreur !== '') {
            afficherMessage($erreur, 'erreur');
        } ?>

        <form action="" method="post">
            <div class="champ">
                <label for="email">Adresse email</label>
                <input type="email" id="email" name="email" required maxlength="100" placeholder="nom@exemple.com"
                       value="<?php echo htmlspecialchars($email); ?>">
            </div>

            <div class="champ">
                <label for="mdp">Mot de passe</label>
                <input type="password" id="mdp" name="mdp" required>
            </div>

            <button type="submit" name="connecter" class="btn btn-principal btn-bloc">Se connecter</button>
        </form>

        <p class="info centre">Pas encore de compte ? <a href="inscription.php">Créer un compte</a></p>
    </section>
</div>

<?php afficherPied(); ?>
