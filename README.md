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

Cet outil a besoin de [Select2JS](http://geodesie.ign.fr:8088/gitlab/components/jquerybundle)  et de [JQuery](http://geodesie.ign.fr:8088/gitlab/components/jquerybundle). Deux bundles existent.
Attention, ils ne sont pas dans les dépendances, à vous de les ajouter !
Vous devez également les déclarer dans le header de votre page.

1. Ajouter les champs que vous voulez "ajaxer" dans config/config.yml

```
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
```
    imports:
    - { resource: sgn_forms.yml }
```

2. Dites à twig d'utiliser le template "fields.ajax.autocomplete.html.twig" dans config/config.yml en complétant les inforamtions twig :

```
twig:
    ...
    form:
        resources:
            - SGNFormsBundle::fields.ajax.autocomplete.html.twig
```

3. Importer les routes

Dans routing.yml, ajouter :

```
sgn_forms:
    resource: '@SGNFormsBundle/Resources/config/routing.xml'

``` 

4. Dans le formulaire

Il suffit enfin de déclarer votre champ de formulaire comme suit ;

```
class PointRefType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder   ->add('nom', null)
                   ->add('Site', 'sgn_ajax_autocomplete', array( 'entity_alias'=>'sites' ));
        ...
    }
 }          
``` 

Où : 
- sgn_ajax_autocomplete est le type du champ
- entity_alias contient la valeur que vous avez déclaré dans sgn_forms.yml


Et normalement, tout fonctionne !




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