<?php
namespace Framework\Validator;

class ValidationError
{

    private $key;
    private $rule;

    private $messages = [
        'required'  =>  'Ce champs est requis',
        'empty'  =>  'Ce champ ne peut être vide',
        'slug'  =>  'Il ne s\'agit pas d\'un lien valide',
        'minLength'  =>  'Ce champs doit contenir plus de %d caractères',
        'maxLength'  =>  'Ce champs doit contenir moins de %d caractères',
        'betweenLength'  =>  'Ce champs doit contenir entre %d et %d caractères',
        'datetime'  =>  'Ce champs doit contenir une date valide (%s)',
        'date'  =>  'Ce champs doit contenir une date valide (%s)',
        'time'  =>  'Ce champs doit contenir une heure valide (%s)',
        'exists'  =>  'La valeur choisie n\'existe pas dans la base de données %s',
        'unique'  =>  'La valeur de ce champs doit être unique',
        'filetype'  =>  'Le fichier choisi n\'est pas au bon format (Allowed extensions : %s)',
        'uploaded'  =>  'Vous devez choisir un fichier',
        'facebookEventUrl' =>  'Ce lien ne pointe pas vers un événement facebook (https://www.facebook.com/events/[ID])'
    ];
    /**
     * @var array
     */
    private $attributes;

    public function __construct(string $key, string $rule, array $attributes = [])
    {
        $this->key = $key;
        $this->rule = $rule;
        $this->attributes = $attributes;
    }

    public function __toString()
    {
        $params = array_merge([$this->messages[$this->rule]], $this->attributes);
        return (string) call_user_func_array('sprintf', $params);
    }
}
