<?php
// entreprise_candidatures.php (FACULTATIF) : candidatures reçues pour une offre,
// consultation du CV PDF et décision (acceptée / refusée)
include 'config.php';
exigerConnexion('entreprise');

$email_entreprise = $_SESSION['email'];
$id_offre = (int) ($_GET['offre'] ?? 0);

// L'offre doit appartenir à l'entreprise connectée
$requete = $bdd->prepare("SELECT * FROM offre_stage WHERE id_offre = ? AND email_entreprise = ?");
$requete->execute([$id_offre, $email_entreprise]);
$offre = $requete->fetch();

if (!$offre) {
    header('Location: entreprise_offres.php');
    exit;
}

$message = '';

// ---------- Changer le statut d'une candidature ----------
if (isset($_POST['statut'])) {
    $statut = $_POST['statut'];
    $id_candidature = (int) ($_POST['id_candidature'] ?? 0);

    if (in_array($statut, $statuts)) {
        // id_offre dans le WHERE : impossible de modifier la candidature d'une autre offre
        $requete = $bdd->prepare("UPDATE candidature SET statut = ? WHERE id_candidature = ? AND id_offre = ?");
        $requete->execute([$statut, $id_candidature, $id_offre]);

        // Le candidat est prévenu par email (si le serveur SMTP est configuré)
        $requete = $bdd->prepare("SELECT email_candidat FROM candidature WHERE id_candidature = ? AND id_offre = ?");
        $requete->execute([$id_candidature, $id_offre]);
        $email_candidat = $requete->fetchColumn();

        if ($email_candidat && $statut !== 'En attente') {
            envoyerEmail(
                $email_candidat,
                "Votre candidature : " . $offre['titre'],
                "Bonjour,\n\nVotre candidature au stage « " . $offre['titre'] . " » a été : " . mb_strtolower($statut)
                . "."
            );
        }
        $message = "Statut mis à jour : $statut.";
    }
}

// ---------- Candidatures de l'offre (jointure pour avoir le nom du candidat) ----------
$requete = $bdd->prepare(
    "SELECT c.*, u.nom, u.prenom, u.telephone, u.photo
     FROM candidature c
     JOIN utilisateur u ON u.email = c.email_candidat
     WHERE c.id_offre = ?
     ORDER BY c.date_candidature DESC"
);
$requete->execute([$id_offre]);
$candidatures = $requete->fetchAll();

afficherEntete('Candidatures', $offre['titre'] . ' · ' . $offre['lieu'] . ' · ' . $offre['duree']);
?>

<p class="lien-retour"><a href="entreprise_offres.php">← Retour à mes offres</a></p>

<?php if ($message !== '') {
    afficherMessage($message, 'succes');
} ?>

<?php if (count($candidatures) === 0) : ?>
    <section class="carte carte-centree">
        <div class="etat-vide">
            <span class="pastille-icone"><?php echo icone('inbox'); ?></span>
            <h2>Aucune candidature pour cette offre</h2>
            <p>Les candidatures des étudiants apparaîtront ici.</p>
        </div>
    </section>
<?php endif; ?>

<div class="liste-candidats">
    <?php foreach ($candidatures as $c) : ?>
        <article class="carte candidat">
            <?php if (!empty($c['photo'])) : ?>
                <img src="<?php echo htmlspecialchars($c['photo']); ?>" alt="" class="candidat-photo">
            <?php else : ?>
                <span class="avatar avatar-grand"><?php echo icone('user'); ?></span>
            <?php endif; ?>

            <div class="candidat-infos">
                <h3>
                    <?php echo htmlspecialchars($c['prenom'] . ' ' . $c['nom']); ?>
                    <span class="badge <?php echo classeStatut($c['statut']); ?>"><?php echo htmlspecialchars($c['statut']); ?></span>
                </h3>
                <div class="puces">
                    <span><?php echo icone('mail') . htmlspecialchars($c['email_candidat']); ?></span>
                    <span><?php echo icone('phone') . htmlspecialchars($c['telephone']); ?></span>
                    <span><?php echo icone('calendar') . 'Reçue le ' . formaterDate($c['date_candidature']); ?></span>
                </div>
                <?php if ($c['message']) : ?>
                    <blockquote class="motivation"><?php echo nl2br(htmlspecialchars($c['message'])); ?></blockquote>
                <?php endif; ?>
            </div>

            <div class="candidat-actions">
                <!-- urlencode : l'email peut contenir des caractères spéciaux (+, @...) -->
                <a href="cv_pdf.php?email=<?php echo urlencode($c['email_candidat']); ?>" target="_blank" class="btn btn-petit btn-secondaire">
                    <?php echo icone('file'); ?> Voir le CV
                </a>
                <!-- Chaque bouton envoie sa propre valeur dans $_POST['statut'] -->
                <form action="" method="post" class="form-statut">
                    <input type="hidden" name="id_candidature" value="<?php echo $c['id_candidature']; ?>">
                    <?php if ($c['statut'] === 'En attente') : ?>
                        <button type="submit" name="statut" value="Acceptée" class="btn btn-petit btn-succes"><?php echo icone('check'); ?> Accepter</button>
                        <button type="submit" name="statut" value="Refusée" class="btn btn-petit btn-danger"><?php echo icone('x'); ?> Refuser</button>
                    <?php else : ?>
                        <button type="submit" name="statut" value="En attente" class="btn btn-petit btn-lien">Remettre en attente</button>
                    <?php endif; ?>
                </form>
            </div>
        </article>
    <?php endforeach; ?>
</div>

<?php afficherPied(); ?>
