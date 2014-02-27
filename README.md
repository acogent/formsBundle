# SGN FormsBundle

Ce Bundle est une boîte à outil “Formulaire” (FormType) de Symfony2.
Vous n’êtes pas obligé de tout utiliser.
Il permet aujourd’hui de :

1. générer des listes AJAX pour les relations Many2One, ce qui allège énormément la page chargée
2. fournir un template “bootstrap 3” des champs de formulaires


TODO
3. Création d’une interface générique pour les entités d’un bundle




## Installation

Mettre à jour le fichier composer.json de votre projet avec les éléments suivants : 

```json
    "require": {
        ...,
        "sgn/forms-bundle": "1.*"
    },
    "repositories": [
        {
            "type": "composer",
            "url": "http://geodesie.ign.fr/satis/"
        }
    ]
```

Puis mettre à jour vos dépendances avec composer : 

```bash
    composer update
```

Enfin, activer le bundle dans votre fichier `app/AppKernel.php`:

```php
    // app/AppKernel.php
    public function registerBundles()
    {
        return array(
            // ...
            new SGN\FormsBundle\SGNFormsBundle(),
        );
    }
```

## Configuration individuelle et utilisation

### Listes Ajax pour entités

Cet outil a besoin de [Select2JS](http://geodesie.ign.fr:8088/gitlab/components/select2bundle)  et de [JQuery](http://geodesie.ign.fr:8088/gitlab/components/jquerybundle). Deux bundles existent.
Attention, ils ne sont pas dans les dépendances, à vous de les ajouter !
Vous devez également les déclarer dans le header de votre page.

1. Ajouter les champs que vous voulez “ajaxer” dans config/config.yml :

```
sgn_forms:
    autocomplete_entities:
        # exemple complet
        sites:
            class:    BDGSDatabaseBundle:Site
            role:     ROLE_USER
            property: numero
            value:    id
            search:   begins_with
            target:   property
            show:     property
        # exemple minimal avec les valeurs par défaut
        pointrefs:
            class:    BDGSDatabaseBundle:PointRef
            property: nomFR

```
- class    : le nom ‘doctrine’ de la classe
- role     : permet de dire qui peut faire de l’ajax par défaut IS_AUTHENTICATED_ANONYMOUSLY. Cela permet d’interdire les modifs par anonymous
- property : le nom du champ qui sera affiché
- value    : le nom du champ dont on renvoie une valeur (si “id”, l‘entité est renvoyée)
- search   : la façon dont est faite la recherche, par défaut begins_with. Valeurs possibles : contains = LIKE '%value%' begins_with = LIKE 'value%' ends_with = LIKE '%value' 
- target   : le ou les attribut(s) sur le(s)quel(s) porte la recherche, par défaut property. Est utile si value est différent de l’id de l’entité. Valeurs possibles : property, value, both
- show     : ce qu’affiche Ajax, par défaut property. Valeurs possible : property (la liste ajax affiche le numero, dans l’exemple), value (la liste ajax affiche l’id), property_value (la liste affiche le numero suivi de l’id entre parenthèses), value_property (la liste affiche l’id suivi du numero entre parenthèses). NB : les valeurs property_value et value_property imposent que target soit à “both”.

Cas particuliers : 
- Par défaut, la recherche porte sur l’entité (class) via son id et son attribut (property). Il est possible d’utiliser une valeur différente, grâce au paramètre value. Dans ce cas, veillez à choisir un attribut unique de type texte (string).

```
sgn_forms:
    autocomplete_entities:
        misscids:
            class:    CANEXIntranetDatabaseBundle:AuxMission
            value:    missCid
            property: designation

```
- Si vous souhaitez que la recherche renvoie l’id de la classe en tant que value (mais pas la classe elle-même), vous pouvez mettre le paramètre entity à FALSE :

```
sgn_forms:
    autocomplete_entities:
        nivfrnids:
            class:    CANEXIntranetDatabaseBundle:NivfRn
            property: rnNom
            entity:   false

```

Note : Si l’entité ne dispose pas d’attribut pouvant servir de property, vous pouvez utiliser le texte renvoyé par sa fonction __toString (sous réserve que cette dernière soit définie). Dans ce cas, value, search et target sont imposés. Si vous entrez d’autres valeurs, elles seront tout simplement ignorées. Par contre, role et show fonctionnent de la même manière :

```
sgn_forms:
    autocomplete_entities:
        # exemple complet avec __toString
        canexs:
            class:    BDGDatabaseBundle:AuxCanex
            role:     ROLE_USER
            property: __toString
            value:    id 
            search:   contains
            target:   both
            show:     property
        # exemple minimal avec les valeurs par défaut pour __toString
        niverns:
            class:    BDGDatabaseBundle:NiveRn
            property: __toString

```
Le mieux est de mettre le contenu ci-dessus dans un fichier séparé config/sgn_forms.yml et d’importer ce fichier dans votre config.yml :
```
    imports:
    - { resource: sgn_forms.yml }
```

2. Dites à twig d’utiliser le template “fields.ajax.autocomplete.html.twig” dans config/config.yml en complétant les inforamtions twig :

```
twig:
    ...
    form:
        resources:
            - SGNFormsBundle::fields.ajax.autocomplete.html.twig
```

3. Importer les routes :

Dans routing.yml, ajouter :

```
sgn_forms:
    resource: '@SGNFormsBundle/Resources/config/routing.xml'

``` 

4. Dans le formulaire :

Il suffit enfin de déclarer votre champ de formulaire comme suit ;

```
class PointRefType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder   ->add('nom', null)
                   ->add('Site', 'sgn_ajax_autocomplete', array('entity_alias'=>'sites'));
        ...
    }
 }          
``` 

Où : 
- sgn_ajax_autocomplete est le type du champ
- entity_alias contient la valeur que vous avez déclaré dans sgn_forms.yml


Et normalement, tout fonctionne !

### Tri des colonnes pour jQgrid

Vous pouvez personnaliser l'ordre d'affichage des colonnes dans jQgrid.
Maintenant, par défaut, l'ordre sera lié à l'héritage (les champs des classes parents en premier).
Mais, vous pouvez le changer. Il suffit de déclarer les champs dans le fichier config.yml de votre application ou mieux dans le fichier que vous avez créé précédemment.

```
sgn_forms:
    ....
    entities_fields: 
        'BDGSDatabaseBundle:PointRef': 'id , nomFR'
        'SITELOGDatabaseBundle:Sitelog': 'id, Domes'

```
L'entrée "entities_fields" est obligatoire. Listez ensuite les entités avec leur bundle et la liste des champs ordonnés séparé par une virgule (pas de tableau). L'application complètera cette liste automatiquement avec les champs non listés.

### Le template bootstrap3

Cet outil a besoin de [Bootstrap 3](http://geodesie.ign.fr:8088/gitlab/components/bootstrapbundle)
Attention, il n'est pas dans les dépendances, à vous de l'ajouter !

Dites à twig d'utiliser le template "forms.bootstrap3.html.twig" dans config/config.yml en complétant les inforamtions twig :

```json
twig:
    ...
    form:
        resources:
            - SGNFormsBundle::forms.bootstrap3.html.twig
```

Déclarer un template d'aministration :

```json
twig:
    ...
    globals:
        ADMIN_LAYOUT: 'BDGSWebsiteBundle::admin_layout.html.twig'
```
où 'BDGSWebsiteBundle::admin_layout.html.twig' est un exmple, à vous de mettre votre template.


### Le générateur de formulaire et interface générique de consultation des entités 


1. AppKernel.php

Ajouter les bundles suivants, si ce n'est déjà fait :

```
new Components\JQueryBundle\ComponentsJQueryBundle(),
new Components\JQueryUiBundle\ComponentsJQueryUiBundle(),
new Components\jqGridBundle\ComponentsjqGridBundle(),
new Components\Select2Bundle\ComponentsSelect2Bundle(),
new SGN\FormsBundle\SGNFormsBundle(),
```
2. config/config.yml

```
sgn_forms:
    bundles: ['BDGSDatabaseBundle', 'SITELOGDatabaseBundle']
    orm: 'default'
    bestof_entity: ['BDGSDatabaseBundle.PointRef', 'BDGSDatabaseBundle.PointRefNumero', 'SITELOGDatabaseBundle.Sitelog','SITELOGDatabaseBundle.GNSSSiteLocation']
```

3. Importer les routes

Dans routing.yml, ajouter :

```
sgn_forms_crud:
    resource: "@SGNFormsBundle/Controller/"
    type:     annotation
    prefix:   /{_locale}/
    defaults:
        _locale: fr
    requirements:
        _locale: en|fr

```
4. Générer les formulaires pour les Bundles configurés