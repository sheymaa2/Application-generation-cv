<?php
// cv_apercu.php : récapitulatif du CV enregistré, avant la génération du PDF
include 'config.php';
exigerConnexion('candidat');

$cv = lireCV($bdd, $_SESSION['email']);

$message = '';
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']);
}

// Liste de contrôle : chaque rubrique obligatoire est-elle remplie ?
$controles = [
    'Nom et prénom'          => !empty($cv['nom']) && !empty($cv['prenom']),
    'Téléphone et adresse'   => !empty($cv['telephone']) && !empty($cv['adresse']),
    'Photo'                  => !empty($cv['photo']),
    'Stages et formations'   => count($cv['experiences']) > 0,
    'Compétences'            => count($cv['competences']) > 0,
    'Langues'                => count($cv['langues']) > 0,
    "Centres d'intérêt"      => count($cv['interets']) > 0,
];

afficherEntete('Aperçu de mon CV', 'Voici votre CV tel qu\'il apparaîtra dans le PDF.');
?>

<?php if ($message !== '') {
    afficherMessage($message, 'succes');
} ?>

<?php if (!cvComplet($cv)) : ?>

    <!-- ===== CV incomplet : ce qu'il reste à remplir ===== -->
    <section class="carte carte-centree">
        <div class="etat-vide">
            <span class="pastille-icone"><?php echo icone('file'); ?></span>
            <h2>Votre CV n'est pas encore complet</h2>
            <p>Remplissez toutes les rubriques pour pouvoir générer le PDF.</p>
        </div>
        <ul class="liste-controle">
            <?php foreach ($controles as $rubrique => $ok) : ?>
                <li class="<?php echo $ok ? 'ok' : 'manque'; ?>">
                    <?php echo icone($ok ? 'check' : 'x'); ?> <?php echo $rubrique; ?>
                </li>
            <?php endforeach; ?>
        </ul>
        <a href="cv_formulaire.php" class="btn btn-principal btn-bloc"><?php echo icone('edit'); ?> Remplir mon CV</a>
    </section>

<?php else : ?>

    <!-- ===== Boutons d'action ===== -->
    <div class="barre-outils">
        <a href="cv_formulaire.php" class="btn btn-secondaire"><?php echo icone('edit'); ?> MODIFIER</a>
        <!-- target="_blank" : le PDF s'ouvre dans un nouvel onglet -->
        <a href="cv_pdf.php" target="_blank" class="btn btn-principal"><?php echo icone('download'); ?> Générer le CV en PDF</a>
    </div>

    <!-- ===== Le CV, présenté comme une feuille ===== -->
    <article class="cv-document">

        <!-- Colonne de gauche : photo, contact, compétences, langues, intérêts -->
        <aside class="cv-cote">
            <img src="<?php echo htmlspecialchars($cv['photo']); ?>" alt="Photo" class="cv-photo">

            <h3>Contact</h3>
            <p class="cv-contact"><?php echo icone('mail') . htmlspecialchars($cv['email']); ?></p>
            <p class="cv-contact"><?php echo icone('phone') . htmlspecialchars($cv['telephone']); ?></p>
            <p class="cv-contact"><?php echo icone('pin') . htmlspecialchars($cv['adresse']); ?></p>

            <h3>Compétences</h3>
            <?php foreach ($cv['competences'] as $c) : ?>
                <div class="niveau">
                    <p><?php echo htmlspecialchars($c['libelle']); ?> <span><?php echo htmlspecialchars($c['niveau']); ?></span></p>
                    <div class="barre"><div style="width: <?php echo pourcentageNiveau($c['niveau']); ?>%"></div></div>
                </div>
            <?php endforeach; ?>

            <h3>Langues</h3>
            <?php foreach ($cv['langues'] as $l) : ?>
                <div class="niveau">
                    <p><?php echo htmlspecialchars($l['nom_langue']); ?> <span><?php echo htmlspecialchars($l['niveau']); ?></span></p>
                    <div class="barre"><div style="width: <?php echo pourcentageNiveau($l['niveau']); ?>%"></div></div>
                </div>
            <?php endforeach; ?>

            <h3>Centres d'intérêt</h3>
            <div class="etiquettes">
                <?php foreach ($cv['interets'] as $i) : ?>
                    <span><?php echo htmlspecialchars($i['libelle']); ?></span>
                <?php endforeach; ?>
            </div>
        </aside>

        <!-- Colonne principale : nom puis parcours (frise chronologique) -->
        <div class="cv-principal">
            <h2 class="cv-nom">
                <?php echo htmlspecialchars($cv['prenom']); ?>
                <strong><?php echo htmlspecialchars(mb_strtoupper($cv['nom'])); ?></strong>
            </h2>

            <h3>Stages et formations</h3>
            <div class="frise">
                <?php foreach ($cv['experiences'] as $e) : ?>
                    <div class="frise-element">
                        <p class="frise-dates">
                            <?php echo icone('calendar'); ?>
                            <?php echo formaterDate($e['date_debut']); ?> –
                            <?php echo $e['date_fin'] ? formaterDate($e['date_fin']) : "aujourd'hui"; ?>
                        </p>
                        <h4>
                            <span class="badge <?php echo $e['type'] === 'Stage' ? 'badge-bleu' : 'badge-violet'; ?>"><?php echo htmlspecialchars($e['type']); ?></span>
                            <?php echo htmlspecialchars($e['titre']); ?>
                        </h4>
                        <?php if ($e['organisme'] || $e['lieu']) : ?>
                            <p class="frise-lieu"><?php echo htmlspecialchars(implode(' · ', array_filter([$e['organisme'], $e['lieu']]))); ?></p>
                        <?php endif; ?>
                        <?php if ($e['description']) : ?>
                            <p><?php echo nl2br(htmlspecialchars($e['description'])); ?></p>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </article>

<?php endif; ?>

<?php afficherPied(); ?>
