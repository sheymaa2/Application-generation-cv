<?php
// offres.php (FACULTATIF) : le candidat consulte les offres de stage, postule et suit ses candidatures
include 'config.php';
exigerConnexion('candidat');

$email = $_SESSION['email'];
$cv_ok = cvComplet(lireCV($bdd, $email));
$message = '';
$erreur = '';

// ---------- Postuler ----------
if (isset($_POST['postuler'])) {
    $id_offre = (int) ($_POST['id_offre'] ?? 0);
    $motivation = nettoyer($_POST['motivation'] ?? '');

    // On revérifie côté serveur : l'offre existe et est ouverte
    $requete = $bdd->prepare("SELECT ouverte FROM offre_stage WHERE id_offre = ?");
    $requete->execute([$id_offre]);
    $ouverte = $requete->fetchColumn();

    if (!$cv_ok) {
        $erreur = "Complétez votre CV avant de postuler.";
    } elseif ($ouverte != 1) {
        $erreur = "Cette offre n'accepte plus de candidatures.";
    } else {
        // Vérifie qu'on n'a pas déjà postulé (la table a aussi une contrainte UNIQUE)
        $requete = $bdd->prepare("SELECT COUNT(*) FROM candidature WHERE id_offre = ? AND email_candidat = ?");
        $requete->execute([$id_offre, $email]);

        if ($requete->fetchColumn() > 0) {
            $erreur = "Vous avez déjà postulé à cette offre.";
        } else {
            $requete = $bdd->prepare("INSERT INTO candidature (id_offre, email_candidat, message) VALUES (?, ?, ?)");
            $requete->execute([$id_offre, $email, $motivation !== '' ? $motivation : null]);
            $message = "Votre candidature a été envoyée.";
        }
    }
}

// ---------- Offres ouvertes, avec le statut de MA candidature (NULL si je n'ai pas postulé) ----------
$requete = $bdd->prepare(
    "SELECT o.*, u.nom AS entreprise, c.statut
     FROM offre_stage o
     JOIN utilisateur u ON u.email = o.email_entreprise
     LEFT JOIN candidature c ON c.id_offre = o.id_offre AND c.email_candidat = ?
     WHERE o.ouverte = 1 OR c.id_candidature IS NOT NULL
     ORDER BY o.date_publication DESC"
);
$requete->execute([$email]);
$offres = $requete->fetchAll();

// Compteurs affichés en haut de la page
$nb_postulees = 0;
foreach ($offres as $o) {
    if ($o['statut'] !== null) {
        $nb_postulees++;
    }
}

afficherEntete('Offres de stage', 'Postulez en un clic : votre CV enregistré est envoyé à l\'entreprise.');
?>

<?php if ($message !== '') {
    afficherMessage($message, 'succes');
} ?>
<?php if ($erreur !== '') {
    afficherMessage($erreur, 'erreur');
} ?>
<?php if (!$cv_ok) {
    afficherMessage("Votre CV est incomplet : complétez-le (menu « Mon CV ») pour pouvoir postuler.", 'info');
} ?>

<div class="statistiques">
    <div class="stat"><?php echo icone('briefcase'); ?><strong><?php echo count($offres); ?></strong><span>offre(s)</span></div>
    <div class="stat"><?php echo icone('send'); ?><strong><?php echo $nb_postulees; ?></strong><span>candidature(s) envoyée(s)</span></div>
</div>

<?php if (count($offres) === 0) : ?>
    <section class="carte carte-centree">
        <div class="etat-vide">
            <span class="pastille-icone"><?php echo icone('inbox'); ?></span>
            <h2>Aucune offre pour le moment</h2>
            <p>Revenez bientôt : les entreprises publient régulièrement de nouveaux stages.</p>
        </div>
    </section>
<?php endif; ?>

<div class="grille-offres">
    <?php foreach ($offres as $o) : ?>
        <article class="carte offre">
            <div class="offre-entete">
                <!-- Initiale de l'entreprise en guise de logo -->
                <span class="avatar"><?php echo htmlspecialchars(mb_strtoupper(mb_substr($o['entreprise'], 0, 1))); ?></span>
                <div>
                    <h3><?php echo htmlspecialchars($o['titre']); ?></h3>
                    <p class="info"><?php echo htmlspecialchars($o['entreprise']); ?></p>
                </div>
            </div>

            <div class="puces">
                <span><?php echo icone('pin') . htmlspecialchars($o['lieu']); ?></span>
                <span><?php echo icone('clock') . htmlspecialchars($o['duree']); ?></span>
                <span><?php echo icone('calendar') . formaterDate($o['date_publication']); ?></span>
            </div>

            <p class="offre-description"><?php echo nl2br(htmlspecialchars($o['description'])); ?></p>

            <?php if ($o['statut'] !== null) : ?>
                <p class="offre-statut">
                    Ma candidature : <span class="badge <?php echo classeStatut($o['statut']); ?>"><?php echo htmlspecialchars($o['statut']); ?></span>
                </p>
            <?php elseif ($cv_ok) : ?>
                <!-- <details> : bloc qui s'ouvre au clic sur <summary>, sans JavaScript -->
                <details class="postuler">
                    <summary class="btn btn-principal btn-petit"><?php echo icone('send'); ?> Postuler</summary>
                    <!-- Mon CV enregistré est joint automatiquement : l'entreprise le verra en PDF -->
                    <form action="" method="post">
                        <input type="hidden" name="id_offre" value="<?php echo $o['id_offre']; ?>">
                        <div class="champ">
                            <label for="motivation<?php echo $o['id_offre']; ?>">Message de motivation <small>(facultatif)</small></label>
                            <textarea id="motivation<?php echo $o['id_offre']; ?>" name="motivation" rows="3"
                                      placeholder="Pourquoi ce stage vous intéresse..."></textarea>
                        </div>
                        <button type="submit" name="postuler" class="btn btn-principal btn-bloc">Envoyer ma candidature avec mon CV</button>
                    </form>
                </details>
            <?php endif; ?>
        </article>
    <?php endforeach; ?>
</div>

<?php afficherPied(); ?>
