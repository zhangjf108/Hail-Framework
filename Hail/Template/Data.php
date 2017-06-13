<?php

namespace Hail\Template;

use LogicException;

/**
 * Preassigned template data.
 */
class Data
{
    /**
     * Variables shared by all templates.
     *
     * @var array
     */
    protected $sharedVariables = [];

    /**
     * Specific template variables.
     *
     * @var array
     */
    protected $templateVariables = [];

    /**
     * Add template data.
     *
     * @param  array             $data      ;
     * @param  null|string|array $templates ;
     *
     * @return $this
     * @throws LogicException
     */
    public function add(array $data, $templates = null)
    {
        if (null === $templates) {
            return $this->shareWithAll($data);
        }

        if (is_array($templates)) {
            return $this->shareWithSome($data, $templates);
        }

        if (is_string($templates)) {
            return $this->shareWithSome($data, [$templates]);
        }

        throw new LogicException(
            'The templates variable must be null, an array or a string, ' . gettype($templates) . ' given.'
        );
    }

    /**
     * Add data shared with all templates.
     *
     * @param  array $data ;
     *
     * @return $this
     */
    public function shareWithAll($data)
    {
        $this->sharedVariables = array_merge($this->sharedVariables, $data);

        return $this;
    }

    /**
     * Add data shared with some templates.
     *
     * @param  array $data      ;
     * @param  array $templates ;
     *
     * @return $this
     */
    public function shareWithSome($data, array $templates)
    {
        foreach ($templates as $template) {
            if (isset($this->templateVariables[$template])) {
                $this->templateVariables[$template] = array_merge($this->templateVariables[$template], $data);
            } else {
                $this->templateVariables[$template] = $data;
            }
        }

        return $this;
    }

    /**
     * Get template data.
     *
     * @param  null|string $template ;
     *
     * @return array
     */
    public function get($template = null)
    {
        if (isset($template, $this->templateVariables[$template])) {
            return array_merge($this->sharedVariables, $this->templateVariables[$template]);
        }

        return $this->sharedVariables;
    }
}
