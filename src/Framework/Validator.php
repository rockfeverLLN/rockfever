<?php

namespace Framework;

use Framework\Database\Table;
use Framework\Validator\ValidationError;
use Psr\Http\Message\UploadedFileInterface;

class Validator
{

    private const MIME_TYPES = [
        'jpg'   =>  'image/jpeg',
        'png'   =>  'image/png',
        'pdf'   =>  'application/pdf'
    ];

    /**
     * @var array
     */
    private $params;

    /**
     * @var string[]
     */
    private $errors=[];

    /**
     * Validator constructor.
     * @param array $params
     */
    public function __construct(array $params)
    {
        $this->params = $params;
    }

    /**
     * @param string ...$keys
     * @return Validator
     */
    public function required(string ...$keys) :self
    {
        foreach ($keys as $key) {
            $value = $this->getValue($key);
            if (is_null($value)) {
                $this->addError($key, 'required');
            }
        }
        return $this;
    }

    /**
     * @param string[] ...$keys
     * @return Validator
     */
    public function notEmpty(string ...$keys) : self
    {
        foreach ($keys as $key) {
            $value = $this->getValue($key);
            if (is_null($value) || empty($value)) {
                $this->addError($key, 'empty');
            }
        }
        return $this;
    }

    /**
     * @param string $key
     * @param int|null $min
     * @param int|null $max
     * @return Validator
     */
    public function length(string $key, ?int $min, ?int $max = null) : self
    {
        $value = $this->getValue($key);
        $length = mb_strlen($value);

        if (!is_null($min) && !is_null($max) && ($length < $min || $length > $max)) {
            $this->addError($key, 'betweenLength', [$min, $max]);
            return $this;
        }

        if (!is_null($min) && $length < $min) {
            $this->addError($key, 'minLength', [$min]);
            return $this;
        }

        if (!is_null($max) && $length > $max) {
            $this->addError($key, 'maxLength', [$max]);
            return $this;
        }
        return $this;
    }

    /**
     * @param string $key
     * @return Validator
     */
    public function slug(string $key) : self
    {
        $value = $this->getValue($key);
        $pattern = '/^[a-z0-9]+(-[a-z0-9]+)*$/';

        if (!is_null($value) && !preg_match($pattern, $value)) {
            $this->addError($key, 'slug');
        }
        return $this;
    }

    /**
     * @param string $key
     * @param string $format
     * @return Validator
     */
    public function date(string $key, string $format = 'jour/mois/année') : self
    {
        $value = (string) $this->getValue($key);

        // Typed as d/m/Y, or Y-m-d from a date field (<input type="date">)
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $parts)) {
            $explodedDate = [$parts[3], $parts[2], $parts[1]];
        } else {
            $explodedDate = explode('/', $value);
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$|[\d]{2}\/[\d]{2}\/[\d]{4}/', $value)
            || !checkdate((int) $explodedDate[1], (int) $explodedDate[0], (int) $explodedDate[2])
        ) {
            $this->addError($key, 'date', [$format]);
        }
        return $this;
    }

    /**
     * @param string $key
     * @param string $format
     * @return Validator
     */
    public function time(string $key, string $format = 'heure:minute') : self
    {
        $value = $this->getValue($key);

        if (!preg_match("/(2[0-3]|[01][0-9]):([0-5][0-9])/", $value)) {
            $this->addError($key, 'time', [$format]);
        }
        return $this;
    }


    public function facebookEventUrl(string $key) : self
    {
        $value = $this->getValue($key);
        if (!empty($value) && !preg_match("/(https?:\/\/)?(www\.)?facebook.com\/events\/[a-zA-Z0-9]+/", $value)) {
            $this->addError($key, 'facebookEventUrl');
        }
        return $this;
    }

    public function email(string $key) : self
    {
        $value = $this->getValue($key);
        if (!empty($value) && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $this->addError($key, 'email');
        }
        return $this;
    }

    /**
     * @param string $key
     * @param Table $table
     * @return Validator
     */
    public function exists(string $key, Table $table) : self
    {
        $id = $this->getValue($key);
        if (!$table->exists($id)) {
            $this->addError($key, 'exists', [$table->getTable()]);
        }
        return $this;
    }

    /**
     * @param string $key
     * @param Table $table
     * @param int|null $exclude
     * @return Validator
     */
    public function unique(string $key, Table $table, ?int $exclude = null) : self
    {
        $value = $this->getValue($key);
        if ($table->entryExists($key, $value, $exclude)) {
            $this->addError($key, 'unique', [$value]);
        }
        return $this;
    }

    /**
     * Verifies if file has well been uploaded
     * @param string $key
     * @return Validator
     */
    public function uploaded(string $key) : self
    {
        /** @var UploadedFileInterface $file */
        $file = $this->getValue($key);
        if ($file === null || $file->getError() !== UPLOAD_ERR_OK) {
            $this->addError($key, 'uploaded');
        }
        return $this;
    }

    /**
     * @param string $key
     * @param array $extensions
     * @return Validator
     */
    public function extension(string $key, array $extensions) : self
    {
        /** @var UploadedFileInterface $file */
        $file = $this->getValue($key);
        if ($file !== null && $file->getError() === UPLOAD_ERR_OK) {
            $type = $file->getClientMediaType();
            $extension = mb_strtolower(pathinfo($file->getClientFilename(), PATHINFO_EXTENSION));
            $expectedType = self::MIME_TYPES[$extension] ?? null;
            if (!in_array($extension, $extensions) || $expectedType !== $type) {
                $this->addError($key, 'filetype', [join(', ', $extensions)]);
            }
        }

        return $this;
    }


    /**
     * @return bool
     */
    public function isValid() : bool
    {
        return empty($this->errors);
    }

    /**
     * @return ValidationError[]
     */
    public function getErrors() : array
    {
        return $this->errors;
    }

    /**
     * @param string $key
     * @param string $rule
     * @param array $attributes
     */
    private function addError(string $key, string $rule, array $attributes = []) :void
    {
        $this->errors[$key] = new ValidationError($key, $rule, $attributes);
    }
    /**
     * @param string $key
     * @return mixed|null
     */
    private function getValue(string $key)
    {
        if (array_key_exists($key, $this->params)) {
            return $this->params[$key];
        }
        return null;
    }
}
