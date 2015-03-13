# SGN FormsBundle

# Changelog
## 2.6.0
Ajout d'un type pour les géométries de la BDG (utilisation de geoserver et openlayers)
Dans son formulaire :

```json
$form->add('point', 'bdg_point_carte', array('domaine' => 'nivf','read_only' => true, 'label' => 'nivrn.point.label'));
```

## 2.5.4
Ajout pour autocomplete_entities la possibilité de 'search' avec equals :

```json
sgn_forms:
    autocomplete_entities:
        coordprecisions_select:
            class    : BDGDatabaseBundle:AuxCoordPrecision
            property : id
            value    : id
            search   : equals
            entity   : false
            method   : getFormListeSQL
            minLength: 1
```
Peut être utile lorsque l'on filtre sur un identifiant


## 2.5.3
Correction bug de layout.
Il faut maintenant  déclarer dans config.yml :

```json
twig:
    globals:
        FORMS_LAYOUT:       'BDGWebsiteBundle::admin_layout.html.twig'
```

## 2.5
Ajout de l'option minLength donnant la possibilité de choisir le nombre de caractères mini à saisir dans les listes de choix. La valeur par défaut est 3.
Exemple :

```json
sgn_forms:
    autocomplete_entities:
        auxrnactions_select:
            class    : BDGDatabaseBundle:AuxRnAction
            property : codVal
            value    : codVal
            search   : begins_with
            entity   : false
            method   : getFormListeSQL
            minLength: 1
```

## 2.4
Ajout de la possibilité de ne pas afficher certains champs dans la grille
## 2.3.1
Prise en compte des identifiants ayant un autre nom en base de données

## 2.3.0
- Ajout d'une commande : sgn:generate:tests  de génération des tests fonctionnels sur les formulaires
- Ajout d'une commande : sgn:generate:fixtures de génération de fixtures sur les entités

Voir l'aide en ligne de la console

## 2.2.1
- Modification du formulaire delete

Ce Bundle est une boîte à outil “Formulaire” (FormType) de Symfony2.
Vous n’êtes pas obligé de tout utiliser.
Il permet aujourd’hui de :

1. générer des listes AJAX pour les relations Many2One, ce qui allège énormément la page chargée
2. fournir un template “bootstrap 3” des champs de formulaires
3. Création d’une interface générique pour les entités d’un bundle

