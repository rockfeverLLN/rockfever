<?php
/**.
 * User: Nicolas van de Walle
 * Date: 15-Feb-18
 * Time: 21:24
 */

namespace Framework\Twig;

use function DI\string;

class FormExtension extends \Twig\Extension\AbstractExtension
{
    public function getFunctions() : array
    {
        return [
            new \Twig\TwigFunction('field', [$this, 'field'], [
                'is_safe' => ['html'],
                'needs_context' => true
            ])
        ];
    }

    /**
     * @param array $context
     * @param string $key
     * @param $value
     * @param string $label
     * @param array $options
     * @return string
     */
    public function field(array $context, string $key, $value, string $label, array $options = []) : string
    {
        $type = $options['type'] ?? 'text';
        $error = $this->getErrorHtml($context, $key);
        $class = 'input-field';
        $value = htmlspecialchars($this->convertValue($value));

        $id = isset($options['idSuffix']) ? $key.'-'.$options['idSuffix'] : $key;


        $attributes = [
            'class' =>  trim($options['class'] ?? ''),
            'name'  =>  $key,
            'id'    =>  $id
        ];

        if ($error) {
            $class .= ' has-danger';
            $attributes['class'] .= ' form-control-danger';
        }

        if ($type === 'textarea') {
            $attributes['class'] = trim($attributes['class'] . ' materialize-textarea');
            $input = $this->textarea($value, $attributes);
        } elseif ($type === 'tinymce') {
            $attributes['class'] = trim($attributes['class'] . ' tinymce');
            $input = $this->textarea($value, $attributes);
        } elseif ($type === 'file') {
            $input = $this->file($attributes);
        } elseif ($type === 'checkbox') {
            $input = $this->checkbox($value, $attributes);
        } elseif ($type === 'select' && array_key_exists('options', $options)) {
            $input = $this->select($value, $options['options'], $attributes);
        } elseif ($type === 'password') {
            $input = $this->password($value, $attributes);
        } else {
            $input = $this->input($value, $attributes);
        }

        return "<div class=\"$class\">
                {$input}
                <label for=\"$id\">$label</label>
                {$error}
            </div>";
    }

    /**
     * @param null|string $value
     * @param array $attributes
     * @return string
     */
    private function input(?string $value, array $attributes) : string
    {
        return "<input type=\"text\" ". $this->getHtmlFromArray($attributes) ." value=\"$value\">";
    }

    /**
     * @param null|string $value
     * @param array $attributes
     * @return string
     */
    private function password(?string $value, array $attributes) : string
    {
        return "<input type=\"password\" ". $this->getHtmlFromArray($attributes) ." value=\"$value\">";
    }

    /**
     * @param null|string $value
     * @param array $attributes
     * @return string
     */
    private function checkbox(?string $value, array $attributes) : string
    {
        $html = '<input type="hidden" name="' . $attributes['name'] . '" value="0"/>';
        if ($value) {
            $attributes['checked'] = true;
        }
        return $html . "<input type=\"checkbox\" ". $this->getHtmlFromArray($attributes) ." value=\"1\">";
    }

    private function file($attributes)
    {
        return "<input type=\"file\" ". $this->getHtmlFromArray($attributes) .">";
    }

    /**
     * @param null|string $value
     * @param array $attributes
     * @return string
     */
    private function textarea(?string $value, array $attributes) : string
    {
        return "<textarea ". $this->getHtmlFromArray($attributes) .">$value</textarea>";
    }

    /**
     * @param string $value
     * @param array $options
     * @param array $attributes
     * @return string
     */
    private function select(string $value, array $options, array $attributes) : string
    {
        $htmlOptions = array_reduce(array_keys($options), function (string $html, string $key) use ($options, $value) {
            $params = ['value' => $key, 'selected' => $key === $value];
            return $html . '<option ' . $this->getHtmlFromArray($params) . '>' . $options[$key] . '</option>';
        }, "");
        return "<select ". $this->getHtmlFromArray($attributes) .">$htmlOptions</select>";
    }

    private function getErrorHtml($context, $key) : string
    {
        $error = $context['errors'][$key] ?? false;
        if ($error) {
            return "<span class=\"helper-text red-text\" data-error=\"Erreur\" data-success=\"OK\">$error</span>";
            //return "<small class=\"form-text text-muted\">$error</small>";
        }
        return "";
    }

    private function getHtmlFromArray(array $attributes)
    {
        $htmlParts = [];
        foreach ($attributes as $key => $value) {
            if ($value === true) {
                $htmlParts[] = (string) $key;
            } elseif ($value !== false) {
                $htmlParts[] =  "$key=\"$value\"";
            }
        }

        return implode(' ', $htmlParts);
    }

    private function convertValue($value) : string
    {
        if ($value instanceof \DateTime) {
            $value = $value->format('Y-m-d H:i:s');
        }
        return (string)$value;
    }
}
