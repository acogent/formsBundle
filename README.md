# SGN FormsBundle

Ce Bundle est une boite à outil "Formulaire" (FormType) de Symfony2.
Vous n'êtes pas obligé de tout utiliser
Il permet aujourd'hui de :

1. générer des listes AJAX pour les relations Many2One, ce qui allège énormément la page chargée
2. fournir un template "bootstrap 3" des champs de formulaires


TODO
3. Création d'une interface générique pour les entités d'un bundle

## Installation

Mettre à jour le fichier composer.json de votre projet avec les éléments suivants : 

```json
    "require": {
        ...,
        "sgn/forms-bundle": "dev-master"
    },
    "repositories": [
        {
            "type": "composer",
            "url": "http://geodesie.ign.fr/satis/"
        }
    ]
```

Puis mettre à jour vos dépendance avec composer : 

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

## Utilisation

### Listes Ajax pour entités


1. Ajouter les champs que vous voulez "ajaxer" dans config/config.yml

```json
sgn_forms:
    autocomplete_entities:
        sites:
            class: BDGSDatabaseBundle:Site
            role: ROLE_USER
            property: numero
            search: begins_with # ends_with - LIKE '%value'  ou  contains - LIKE '%value%' begins_with - LIKE 'value%' (default)
        pointrefs:
            class: BDGSDatabaseBundle:PointRef
            role: ROLE_USER
            property: nomFR

```

Le mieux est de mettre le contenu ci-dessus dans un fichier séparé config/sgn_forms.yml et d'importer ce fichier dans votre config.yml :
```json
    imports:
    - { resource: sgn_forms.yml }
```

2. Dites à twig d'utiliser le template "fields.ajax.autocomplete.html.twig" dans config/config.yml en complétant les inforamtions twig :

```json
twig:
    ...
    form:
        resources:
            - SGNFormsBundle::fields.ajax.autocomplete.html.twig
```


### Le template bootstrap3

Dites à twig d'utiliser le template "forms.bootstrap3.html.twig" dans config/config.yml en complétant les inforamtions twig :

```json
twig:
    ...
    form:
        resources:
            - SGNFormsBundle::forms.bootstrap3.html.twig
```