<?php

namespace Romm\SiteFactory\Form\Validation;

use Romm\SiteFactory\Core\Core;
use Romm\SiteFactory\Form\Fields\AbstractField;

/**
 * Custom validator for the Site Factory.
 */
class GoogleFontUrlValidator extends AbstractValidator
{

    /**
     * Checks if the field value matches a Google Font URL.
     *
     * @param AbstractField $field The field.
     */
    protected function isValid($field)
    {
        if (!preg_match('/^$|^(https:\/\/)?(www.)?fonts.googleapis.com\/css\?family=.+$/', $field->getValue())) {
            $this->addError(
                $this->translateErrorMessage(
                    'fields.validation.google_font_url_value',
                    Core::getExtensionKey()
                ),
                1541683955
            );
        }
    }
}
