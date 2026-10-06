<?php
// formulaire.php : Fiche de Renseignements de l'étudiant

session_start();

// Bouton MODIFIER (?modifier=1) : on reprend les infos gardées dans la session
$donnees = [];
if (isset($_GET['modifier']) && isset($_SESSION['donnees'])) {
    $donnees = $_SESSION['donnees'];
}

// Erreurs envoyées par recap.php, supprimées ensuite pour ne s'afficher qu'une fois
$erreurs = [];
if (isset($_SESSION['erreurs'])) {
    $erreurs = $_SESSION['erreurs'];
    unset($_SESSION['erreurs']);
}

// Affiche le message d'erreur d'un champ, s'il y en a un
function afficherErreur($erreurs, $champ)
{
    if (isset($erreurs[$champ])) {
        echo '<p class="erreur">' . htmlspecialchars($erreurs[$champ]) . '</p>';
    }
}

// Listes des choix ($filieres, $annees, $modules...), partagées avec recap.php
include 'listes.php';

// Affiche les champs d'UN projet (utilisée aussi dans le <template> copié par le JS).
// Les [] dans les name envoient un tableau à recap.php (une case par projet).
function afficherBlocProjet($types, $projet = [])
{
    $type_choisi = $projet['type'] ?? 'Projet';
    $lieu        = htmlspecialchars($projet['lieu'] ?? '');
    $debut       = htmlspecialchars($projet['debut'] ?? '');
    $fin         = htmlspecialchars($projet['fin'] ?? '');
    $description = htmlspecialchars($projet['description'] ?? '');
    ?>
    <div class="projet">
        <p class="projet-titre">Projet / Stage</p>

        <div class="projet-champs">
            <label class="champ">
                Type :
                <select name="projet_type[]">
                    <?php foreach ($types as $type) : ?>
                        <option value="<?php echo $type; ?>" <?php if ($type_choisi === $type) echo 'selected'; ?>><?php echo $type; ?></option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label class="champ">
                Lieu :
                <input type="text" name="projet_lieu[]" value="<?php echo $lieu; ?>">
            </label>

            <label class="champ">
                Date de début :
                <input type="date" name="projet_debut[]" value="<?php echo $debut; ?>">
            </label>

            <label class="champ">
                Date de fin :
                <input type="date" name="projet_fin[]" value="<?php echo $fin; ?>">
            </label>

            <label class="champ champ-large">
                Description :
                <textarea name="projet_description[]" rows="3"><?php echo $description; ?></textarea>
            </label>
        </div>
    </div>
    <?php
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fiche de Renseignements</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<div class="conteneur">
    <h1>Fiche de Renseignements</h1>

    <?php if (count($erreurs) > 0) : ?>
        <p class="message-erreur">Certains champs sont manquants ou incorrects. Veuillez corriger les messages en rouge.</p>
    <?php endif; ?>

    <p class="info">Les champs marqués d'une <span class="obligatoire">*</span> sont obligatoires.</p>

    <!-- multipart/form-data : obligatoire pour envoyer un fichier -->
    <form action="recap.php" method="post" enctype="multipart/form-data">

        <div class="grille">

            <!-- ===== Bloc 1 : Renseignements Académiques ===== -->
            <fieldset class="bloc bloc-academique">
                <legend>Renseignements Académiques</legend>

                <p class="titre-champ">Vous êtes en : <span class="obligatoire">*</span></p>

                <!-- Radio : un seul choix ; checked = choix précédent -->
                <div class="choix">
                    <?php foreach ($filieres as $filiere) : ?>
                        <label>
                            <input type="radio" name="filiere" value="<?php echo $filiere; ?>" required
                                <?php if (($donnees['filiere'] ?? '') === $filiere) echo 'checked'; ?>>
                            <?php echo $filiere; ?>
                        </label>
                    <?php endforeach; ?>
                </div>
                <?php afficherErreur($erreurs, 'filiere'); ?>

                <div class="choix">
                    <?php foreach ($annees as $annee) : ?>
                        <label>
                            <input type="radio" name="annee" value="<?php echo $annee; ?>" required
                                <?php if (($donnees['annee'] ?? '') === $annee) echo 'checked'; ?>>
                            <?php echo $annee; ?>
                        </label>
                    <?php endforeach; ?>
                </div>
                <?php afficherErreur($erreurs, 'annee'); ?>

                <p class="titre-champ">Modules suivis cette année :</p>

                <!-- Checkbox : plusieurs choix ; modules[] = tableau en PHP -->
                <div class="choix">
                    <?php foreach ($modules as $module) : ?>
                        <label>
                            <input type="checkbox" name="modules[]" value="<?php echo $module; ?>"
                                <?php if (in_array($module, $donnees['modules'] ?? [])) echo 'checked'; ?>>
                            <?php echo $module; ?>
                        </label>
                    <?php endforeach; ?>
                </div>
                <?php afficherErreur($erreurs, 'modules'); ?>

                <div class="champ champ-ligne">
                    <label for="nb_projets" class="titre-champ">Nombre de projets réalisés cette année :</label>
                    <select id="nb_projets" name="nb_projets">
                        <?php for ($i = 0; $i <= $nb_projets_max; $i++) : ?>
                            <option value="<?php echo $i; ?>"
                                <?php if (($donnees['nb_projets'] ?? '') === (string) $i) echo 'selected'; ?>>
                                <?php echo $i; ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
                <?php afficherErreur($erreurs, 'nb_projets'); ?>
            </fieldset>

            <!-- ===== Bloc 2 : Renseignements Personnels ===== -->
            <fieldset class="bloc bloc-personnel">
                <legend>Renseignements Personnels</legend>

                <!-- Validation côté client : required, maxlength, pattern, min/max.
                     htmlspecialchars() protège la valeur ré-affichée. -->
                <div class="champ">
                    <label for="nom">Nom : <span class="obligatoire">*</span></label>
                    <input type="text" id="nom" name="nom" required maxlength="50"
                           pattern="[a-zA-ZÀ-ÿ' \-]+" title="Lettres uniquement"
                           value="<?php echo htmlspecialchars($donnees['nom'] ?? ''); ?>">
                    <?php afficherErreur($erreurs, 'nom'); ?>
                </div>

                <div class="champ">
                    <label for="prenom">Prénom : <span class="obligatoire">*</span></label>
                    <input type="text" id="prenom" name="prenom" required maxlength="50"
                           pattern="[a-zA-ZÀ-ÿ' \-]+" title="Lettres uniquement"
                           value="<?php echo htmlspecialchars($donnees['prenom'] ?? ''); ?>">
                    <?php afficherErreur($erreurs, 'prenom'); ?>
                </div>

                <div class="champ">
                    <label for="age">Âge :</label>
                    <input type="number" id="age" name="age" min="16" max="60"
                           value="<?php echo htmlspecialchars($donnees['age'] ?? ''); ?>">
                    <?php afficherErreur($erreurs, 'age'); ?>
                </div>

                <div class="champ">
                    <label for="telephone">Numéro de Téléphone :</label>
                    <input type="tel" id="telephone" name="telephone" maxlength="10"
                           pattern="0[5-7][0-9]{8}" title="10 chiffres commençant par 05, 06 ou 07"
                           value="<?php echo htmlspecialchars($donnees['telephone'] ?? ''); ?>">
                    <?php afficherErreur($erreurs, 'telephone'); ?>
                </div>

                <div class="champ">
                    <label for="email">Email : <span class="obligatoire">*</span></label>
                    <input type="email" id="email" name="email" required maxlength="100"
                           value="<?php echo htmlspecialchars($donnees['email'] ?? ''); ?>">
                    <?php afficherErreur($erreurs, 'email'); ?>
                </div>
            </fieldset>

            <!-- ===== Bloc 3 : Vos remarques ===== -->
            <fieldset class="bloc bloc-remarques">
                <legend>Vos remarques</legend>

                <div class="champ">
                    <label for="remarques">Remarques :</label>
                    <!-- textarea : la valeur se met entre les balises -->
                    <textarea id="remarques" name="remarques" rows="4"><?php echo htmlspecialchars($donnees['remarques'] ?? ''); ?></textarea>
                </div>

                <div class="champ">
                    <label for="fichier">Fichier :</label>
                    <input type="file" id="fichier" name="fichier" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                    <p class="info">PDF, JPG, PNG, DOC ou DOCX, 5 Mo maximum.</p>
                    <?php afficherErreur($erreurs, 'fichier'); ?>

                    <!-- Un champ fichier ne peut pas être pré-rempli :
                         le champ caché dit à recap.php de garder l'ancien fichier -->
                    <?php if (($donnees['fichier_nom'] ?? '') !== '') : ?>
                        <input type="hidden" name="garder_fichier" value="1">
                        <p class="info">
                            Fichier actuel : <?php echo htmlspecialchars($donnees['fichier_nom']); ?>
                            (choisissez-en un autre pour le remplacer)
                        </p>
                    <?php endif; ?>
                </div>
            </fieldset>

            <!-- ===== Bloc 4 : Projets et stages ===== -->
            <fieldset class="bloc bloc-projets">
                <legend>Projets et stages réalisés</legend>

                <!-- Projets saisis avant, sinon un projet vide -->
                <div id="liste-projets">
                    <?php
                    $projets_saisis = $donnees['projets'] ?? [];
                    if (count($projets_saisis) === 0) {
                        afficherBlocProjet($types_projet);
                    } else {
                        foreach ($projets_saisis as $projet) {
                            afficherBlocProjet($types_projet, $projet);
                        }
                    }
                    ?>
                </div>
                <?php afficherErreur($erreurs, 'projets'); ?>

                <!-- Modèle invisible copié par script.js -->
                <template id="modele-projet">
                    <?php afficherBlocProjet($types_projet); ?>
                </template>

                <!-- type="button" : n'envoie pas le formulaire -->
                <button type="button" id="ajouter-projet" class="btn btn-ajouter">+ Ajouter un projet ou stage</button>
            </fieldset>

            <!-- ===== Bloc 5 : Centres d'intérêt ===== -->
            <fieldset class="bloc bloc-interets">
                <legend>Centres d'intérêt</legend>

                <div class="champ">
                    <label for="interets">Vos centres d'intérêt :</label>
                    <textarea id="interets" name="interets" rows="4"><?php echo htmlspecialchars($donnees['interets'] ?? ''); ?></textarea>
                </div>
            </fieldset>

            <!-- ===== Bloc 6 : Compétences et Langues ===== -->
            <fieldset class="bloc bloc-competences">
                <legend>Compétences et Langues</legend>

                <div class="champ">
                    <label for="competences">Compétences :</label>
                    <textarea id="competences" name="competences" rows="3"><?php echo htmlspecialchars($donnees['competences'] ?? ''); ?></textarea>
                </div>

                <div class="champ">
                    <label for="langues">Langues :</label>
                    <textarea id="langues" name="langues" rows="2"><?php echo htmlspecialchars($donnees['langues'] ?? ''); ?></textarea>
                </div>
            </fieldset>

        </div>

        <div class="boutons">
            <button type="submit" class="btn btn-principal">Envoyer</button>
            <button type="reset" class="btn btn-secondaire">Effacer</button>
        </div>

    </form>
</div>

<script src="script.js"></script>

</body>
</html>
