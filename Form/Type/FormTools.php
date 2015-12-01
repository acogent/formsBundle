<?php

namespace SGN\FormsBundle\Form\Type;

use Symfony\Component\Form\AbstractType;

/**
 * Cette classe contient les méthodes communes aux formulaires du bundle.
 */
class FormTools extends AbstractType
{
    /**
     * Cette méthode renvoie la méthode JQuery permettant de vider
     * une liste de champs du formulaire (en y plaçant NULL).
     *
     * @param array $fieldTable La table des champs à vider
     *
     * @return string $jquery     La requête JQuery à exécuter
     */
    protected function attributeClear($fieldTable)
    {
        $jquery = '';

        foreach ($fieldTable as $fieldId) {
            $jquery .= "$('#".$this->getName().'_'.$fieldId."').val(null);";
        }

        return $jquery;
    }

    /**
     * Cette méthode renvoie la méthode JQuery permettant de remplir
     * le champ caché “ajax” du formulaire.
     *
     * @param string $bool La valeur à insérer
     *
     * @return string La requête JQuery à exécuter
     */
    protected function setAjax($bool)
    {
        return "$('#".$this->getName()."_ajax').val('".$bool."');";
    }

    /**
     * Cette méthode renvoie le texte en JQuery à exécuter lors de l’événement
     * onChange d’un champ du formulaire.
     *
     * @return string La requête JQuery à exécuter
     */
    protected function onChangeField($bool = 'true')
    {
        return $this->setAjax($bool).'ajaxFormRequest(this.form, "dynamic");';
    }

    /**
     * Cette méthode renvoie le texte en JQuery à exécuter lors de l’événement
     * onClickSubmitForm d’un champ du formulaire.
     *
     * @return string La requête JQuery à exécuter
     */
    protected function onClickSubmitForm($bool = 'false')
    {
        return $this->setAjax($bool).'ajaxFormRequest(this.form, "validate");';
    }

    /**
     * Cette méthode renvoie le texte en JQuery à exécuter lors de l’événement
     * onClickChoiseForm d’un champ du formulaire.
     *
     * @return string La requête JQuery à exécuter
     */
    protected function onClickChoiseForm($bool = 'false')
    {
        return $this->setAjax($bool).'ajaxFormRequest(this.form, "dynamicChoise");';
    }

    /**
     * Pour permettre l’héritage.
     *
     * @return string
     */
    public function getName()
    {
        return 'formtools_type';
    }
}
