# SGN FormsBundle

# Changelog
## 4.0.0
On peut maintenant stocker ses entités dans des dossiers séparés.
Dans votre projet, vous pouvez stocker vos entités dans des dossiers séparés mais toujours dans le dossier Entity.
Pou cela, il faut le dire à doctrine/orm.
Exemple :
```
#config.yml
doctrine:
    orm:
        default_entity_manager: default
        entity_managers:
            default:
                connection:         default
                mappings:
                    BDGDatabaseBundle: ~
                    BDGAdmin:
                        mapping:    true
                        type:       annotation
                        is_bundle:  false
                        dir:        %kernel.root_dir%/../src/BDG/DatabaseBundle/Entity/admin
                        prefix:     BDG\DatabaseBundle\Entity\admin
                        alias:      BDGAdmin

```
Dans cet exemple, toutes les entités du domaine AUX de la BDG, ont été créées dans le dossier auxi( aux est interdit sur windows).
Attention, à partir du moment où vous créez un alias, vous devrez toujours l'utiliser.

Pour vos listes de choix gérées avec sgn_forms :
```
sgn_forms:
    autocomplete_entities:
        auxcartes_select:
            class    : BDGAux:AuxCarte
            property : no
            value    : no
            search   : contains
            entity   : false
            method   : getFormListeSQL
```
Et dans le SQL du repository:
```
    /**
     * Get getFormListeSQL
     *
     * @return text
     */
    public function getFormListeSQL()
    {
        $sql = "SELECT e.no as id, TRIM (concat( concat( e.no, ' (' ), concat(e.nom, ')' )  ) ) as value
        FROM   BDGAux:AuxCarte e
        WHERE  LOWER(TRIM (concat( concat( e.no, ' (' ), concat(e.nom, ')' )  ) )) LIKE LOWER(:like)";

        return $sql;

    }
```
Pour les entités qui ont des relations avec des entité qui ne sont pas dans le même dossier, il faudra préciser le chemin complet :
```
<?php

namespace BDG\DatabaseBundle\Entity\rsgf;

/**
 * RsgfPtg
 *
 * etc
 */
class RsgfPtg extends \BDG\DatabaseModelBundle\Model\rsgf\RsgfPtgModel
{
    /**
     * @ORM\OneToMany(targetEntity="BDG\DatabaseBundle\Entity\nivf\NivfRnGeodesique", mappedBy="rsgfPtg", cascade={"persist", "remove", "merge"}, orphanRemoval=true)
     */
    protected $rngeods;

```

## 3.8.0
On n'utilise plus les bundles "Components, selon les recommandations Symfony, il faut charger les bibliothèques tierces directement avec composer.

## 3.2.0 à 3.7.0
Utilisation de twgit. Insertion de toutes les anciennes branches.

## 3.1.0
Ajout d'une option 'extended' dans 'entities_filters' : augmente l'affichage des tables liées en ajoutant les relations ...ToOne et permet l'affichage des tables liées pour chaque objet des tables liées !
Ajout en parallèle de l'option 'rel_hidden' pour ne pas afficher certaines relations, y compris Audit. Il n'y a par conséquent plus d'utilité pour le paramètre 'audit : true/false', qui est supprimé.

```json
sgn_forms:
    entities_filters:
        '*':
            extended   : true
            rel_hidden : 'TechniqueStation, TypeInstrument, Audit'
```

## 3.0.0
Réorganisation des options d'affichage des grilles via la nouvelle option 'entities_filters', qui rassemble 'entities_fields' (devient 'order') et 'entities_fields_hidden' (devient 'hidden'). Ajout de 'audit' pour l'affichage de la table 'Audit' (true par défaut), et de la possibilité de donner des valeurs par défaut pour toutes les entités (avec '*').

```json
sgn_forms:
    entities_filters:
        '*':
            order    : 'id'
            hidden   : 'slug, ptgTemp, projectTemp'
        'BDGSDatabaseBundle:PointRef':
            order    : 'id, nomFR'
            hidden   : 'acroTemp'
            audit    : false
```

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

