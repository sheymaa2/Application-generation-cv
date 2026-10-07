<?php
// entreprise_offres.php (FACULTATIF) : l'entreprise publie ses offres de stage et voit leurs candidatures
include 'config.php';
exigerConnexion('entreprise');

$email_entreprise = $_SESSION['email'];
$erreurs = [];
$offre = ['titre' => '', 'lieu' => '', 'duree' => '', 'description' => ''];

$message = '';
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']);
}

// ---------- Publier une offre ----------
if (isset($_POST['publier'])) {
    $offre = [
        'titre'       => nettoyer($_POST['titre'] ?? ''),
        'lieu'        => nettoyer($_POST['lieu'] ?? ''),
        'duree'       => nettoyer($_POST['duree'] ?? ''),
        'description' => nettoyer($_POST['description'] ?? ''),
    ];

    if (empty($offre['titre']) || strlen($offre['titre']) > 100) {
        $erreurs['titre'] = "L'intitulé est obligatoire (100 caractères maximum).";
    }
    if (empty($offre['lieu']) || strlen($offre['lieu']) > 100) {
        $erreurs['lieu'] = "Le lieu est obligatoire (100 caractères maximum).";
    }
    if (empty($offre['duree']) || strlen($offre['duree']) > 50) {
        $erreurs['duree'] = "La durée est obligatoire (ex. : 2 mois).";
    }
    if (empty($offre['description'])) {
        $erreurs['description'] = "La description est obligatoire.";
    }

    if (count($erreurs) === 0) {
        $requete = $bdd->prepare(
            "INSERT INTO offre_stage (email_entreprise, titre, lieu, duree, description) VALUES (?, ?, ?, ?, ?)"
        );
        $requete->execute([$email_entreprise, $offre['titre'], $offre['lieu'], $offre['duree'], $offre['description']]);

        // Redirection après un POST : un F5 ne republie pas la même offre
        $_SESSION['message'] = "L'offre a été publiée.";
        header('Location: entreprise_offres.php');
        exit;
    }
}

// ---------- Ouvrir / fermer une offre ----------
// "AND email_entreprise = ?" : une entreprise ne peut modifier que SES offres
if (isset($_POST['basculer'])) {
    $requete = $bdd->prepare(
        "UPDATE offre_stage SET ouverte = 1 - ouverte WHERE id_offre = ? AND email_entreprise = ?"
    );
    $requete->execute([(int) $_POST['id_offre'], $email_entreprise]);
    header('Location: entreprise_offres.php');
    exit;
}

// ---------- Liste des offres de l'entreprise, avec le nombre de candidatures ----------
$requete = $bdd->prepare(
    "SELECT o.*, COUNT(c.id_candidature) AS nb_candidatures,
            SUM(c.statut = 'En attente') AS nb_en_attente
     FROM offre_stage o
     LEFT JOIN candidature c ON c.id_offre = o.id_offre
     WHERE o.email_entreprise = ?
     GROUP BY o.id_offre
     ORDER BY o.date_publication DESC"
);
$requete->execute([$email_entreprise]);
$offres = $requete->fetchAll();

$requete = $bdd->prepare("SELECT nom FROM utilisateur WHERE email = ?");
$requete->execute([$email_entreprise]);
$nom_entreprise = $requete->fetchColumn();

// Totaux pour les cartes de statistiques
$total_candidatures = 0;
$total_en_attente   = 0;
foreach ($offres as $o) {
    $total_candidatures += $o['nb_candidatures'];
    $total_en_attente   += (int) $o['nb_en_attente'];
}

afficherEntete('Espace ' . $nom_entreprise, 'Publiez vos offres de stage et traitez les candidatures reçues.');
?>

<?php if ($message !== '') {
    afficherMessage($message, 'succes');
} ?>

<div class="statistiques">
    <div class="stat"><?php echo icone('briefcase'); ?><strong><?php echo count($offres); ?></strong><span>offre(s) publiée(s)</span></div>
    <div class="stat"><?php echo icone('users'); ?><strong><?php echo $total_candidatures; ?></strong><span>candidature(s) reçue(s)</span></div>
    <div class="stat stat-alerte"><?php echo icone('clock'); ?><strong><?php echo $total_en_attente; ?></strong><span>en attente de réponse</span></div>
</div>

<div class="grille-entreprise">

    <!-- ===== Nouvelle offre ===== -->
    <section class="carte">
        <div class="section-entete">
            <span class="section-numero"><?php echo icone('plus'); ?></span>
            <div>
                <h2>Publier une offre</h2>
                <p>Elle sera visible immédiatement par les étudiants.</p>
            </div>
        </div>

        <form action="" method="post">
            <div class="champ">
                <label for="titre">Intitulé du stage <span class="obligatoire">*</span></label>
                <input type="text" id="titre" name="titre" required maxlength="100" placeholder="Ex. : Stage développeur PHP"
                       value="<?php echo htmlspecialchars($offre['titre']); ?>">
                <?php afficherErreur($erreurs, 'titre'); ?>
            </div>
            <div class="champ-duo">
                <div class="champ">
                    <label for="lieu">Lieu <span class="obligatoire">*</span></label>
                    <input type="text" id="lieu" name="lieu" required maxlength="100" placeholder="Ex. : Tanger"
                           value="<?php echo htmlspecialchars($offre['lieu']); ?>">
                    <?php afficherErreur($erreurs, 'lieu'); ?>
                </div>
                <div class="champ">
                    <label for="duree">Durée <span class="obligatoire">*</span></label>
                    <input type="text" id="duree" name="duree" required maxlength="50" placeholder="Ex. : 2 mois"
                           value="<?php echo htmlspecialchars($offre['duree']); ?>">
                    <?php afficherErreur($erreurs, 'duree'); ?>
                </div>
            </div>
            <div class="champ">
                <label for="description">Description <span class="obligatoire">*</span></label>
                <textarea id="description" name="description" rows="5" required
                          placeholder="Missions, profil recherché, compétences attendues..."><?php echo htmlspecialchars($offre['description']); ?></textarea>
                <?php afficherErreur($erreurs, 'description'); ?>
            </div>

            <button type="submit" name="publier" class="btn btn-principal btn-bloc"><?php echo icone('send'); ?> Publier l'offre</button>
        </form>
    </section>

    <!-- ===== Offres publiées ===== -->
    <section>
        <h2 class="titre-section">Mes offres</h2>

        <?php if (count($offres) === 0) : ?>
            <div class="carte etat-vide">
                <span class="pastille-icone"><?php echo icone('inbox'); ?></span>
                <h2>Aucune offre publiée</h2>
                <p>Utilisez le formulaire pour publier votre première offre de stage.</p>
            </div>
        <?php endif; ?>

        <?php foreach ($offres as $o) : ?>
            <article class="carte offre-ligne <?php if (!$o['ouverte']) echo 'offre-fermee'; ?>">
                <div class="offre-ligne-texte">
                    <h3>
                        <?php echo htmlspecialchars($o['titre']); ?>
                        <span class="badge <?php echo $o['ouverte'] ? 'badge-vert' : 'badge-gris'; ?>">
                            <?php echo $o['ouverte'] ? 'Ouverte' : 'Fermée'; ?>
                        </span>
                    </h3>
                    <div class="puces">
                        <span><?php echo icone('pin') . htmlspecialchars($o['lieu']); ?></span>
                        <span><?php echo icone('clock') . htmlspecialchars($o['duree']); ?></span>
                        <span><?php echo icone('calendar') . formaterDate($o['date_publication']); ?></span>
                    </div>
                    <p class="offre-compteur">
                        <strong><?php echo $o['nb_candidatures']; ?></strong> candidature(s)
                        <?php if ($o['nb_en_attente'] > 0) : ?>
                            · <span class="badge badge-orange"><?php echo (int) $o['nb_en_attente']; ?> en attente</span>
                        <?php endif; ?>
                    </p>
                </div>
                <div class="actions">
                    <a href="entreprise_candidatures.php?offre=<?php echo $o['id_offre']; ?>" class="btn btn-petit btn-principal">
                        <?php echo icone('users'); ?> Candidatures
                    </a>
                    <form action="" method="post">
                        <input type="hidden" name="id_offre" value="<?php echo $o['id_offre']; ?>">
                        <button type="submit" name="basculer" class="btn btn-petit btn-secondaire">
                            <?php echo $o['ouverte'] ? 'Fermer' : 'Rouvrir'; ?>
                        </button>
                    </form>
                </div>
            </article>
        <?php endforeach; ?>
    </section>

</div>

<?php afficherPied(); ?>
