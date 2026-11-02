<?php
namespace Framework\Validator;

class ValidationError
{

    private $rule;

    private $messages = [
        'required'  =>  'Ce champs est requis',
        'empty'  =>  'Ce champ ne peut être vide',
        'minLength'  =>  'Ce champs doit contenir plus de %d caractères',
        'maxLength'  =>  'Ce champs doit contenir moins de %d caractères',
        'betweenLength'  =>  'Ce champs doit contenir entre %d et %d caractères',
        'date'  =>  'Ce champs doit contenir une date valide (%s)',
        'time'  =>  'Ce champs doit contenir une heure valide (%s)',
        'exists'  =>  'La valeur choisie n\'existe pas dans la base de données %s',
        'uploaded'  =>  'Vous devez choisir un fichier',
        'email'  =>  'Ce champ doit contenir une adresse email valide',
        'facebookEventUrl' =>'Ce lien ne pointe pas vers un événement facebook (https://www.facebook.com/events/[ID])'
    ];
    /**
     * @var array
     */
    private $attributes;

    public function __construct(string $rule, array $attributes = [])
    {
        $this->rule = $rule;
        $this->attributes = $attributes;
    }

    public function __toString()
    {
        $params = array_merge([$this->messages[$this->rule]], $this->attributes);
        return (string) call_user_func_array('sprintf', $params);
    }
}
