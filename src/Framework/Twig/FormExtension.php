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
        $class = 'input-field';
        $value = htmlspecialchars($this->convertValue($value));

        $id = isset($options['idSuffix']) ? $key.'-'.$options['idSuffix'] : $key;
        $error = $this->getErrorHtml($context, $key, $id);


        $attributes = [
            'class' =>  trim($options['class'] ?? ''),
            'name'  =>  $key,
            'id'    =>  $id
        ] + ($options['attributes'] ?? []);

        // The error is tied to its field, so a screen reader says it when the field gets focus
        if ($error) {
            $class .= ' has-danger';
            $attributes['class'] .= ' form-control-danger';
            $attributes['aria-invalid'] = 'true';
            $attributes['aria-describedby'] = "$id-error";
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
        } elseif ($type === 'email') {
            $input = $this->input($value, $attributes, 'email');
        } elseif ($type === 'password') {
            $input = $this->password($value, $attributes);
        } elseif ($type === 'date' || $type === 'time') {
            // The browser's own date and time pickers. A date field's value is Y-m-d, the entities' dates are d/m/Y
            if ($type === 'date') {
                $value = preg_replace('#^(\d{2})/(\d{2})/(\d{4})$#', '$3-$2-$1', $value);
            }
            $input = $this->input($value, $attributes, $type);
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
    private function input(?string $value, array $attributes, string $type = 'text') : string
    {
        return "<input type=\"$type\" ". $this->getHtmlFromArray($attributes) ." value=\"$value\">";
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

    private function getErrorHtml($context, $key, string $id) : string
    {
        $error = $context['errors'][$key] ?? false;
        if ($error) {
            return "<span class=\"helper-text red-text\" id=\"$id-error\">$error</span>";
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
