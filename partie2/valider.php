<?php
// valider.php : page ouverte par le lien reçu par email (valider.php?token=...)
include 'config.php';

$token = $_GET['token'] ?? '';
$reussi = false;

// Le token est une chaîne de 64 caractères hexadécimaux : on vérifie le format avant la requête
if (preg_match("/^[a-f0-9]{64}$/", $token)) {
    $requete = $bdd->prepare("SELECT email FROM utilisateur WHERE token_validation = ?");
    $requete->execute([$token]);
    $utilisateur = $requete->fetch();

    if ($utilisateur) {
        // Email confirmé : on efface le token pour que le lien ne serve qu'une fois
        $requete = $bdd->prepare(
            "UPDATE utilisateur SET email_valide = 1, token_validation = NULL WHERE email = ?"
        );
        $requete->execute([$utilisateur['email']]);
        $reussi = true;
    }
}

afficherEntete('Validation de l\'email', '', true);
?>

<section class="auth-carte carte-seule">
    <div class="etat-vide">
        <?php if ($reussi) : ?>
            <span class="pastille-icone pastille-succes"><?php echo icone('check'); ?></span>
            <h2>Adresse email validée</h2>
            <p>Votre compte est activé. Vous pouvez maintenant vous connecter.</p>
        <?php else : ?>
            <span class="pastille-icone pastille-erreur"><?php echo icone('x'); ?></span>
            <h2>Lien invalide</h2>
            <p>Ce lien n'existe pas ou a déjà été utilisé.</p>
        <?php endif; ?>
        <a href="connexion.php" class="btn btn-principal btn-bloc">Se connecter</a>
    </div>
</section>

<?php afficherPied(); ?>
