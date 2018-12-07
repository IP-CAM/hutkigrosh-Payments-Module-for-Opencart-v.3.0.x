<?php

/**
 * Created by PhpStorm.
 * User: nikit
 * Date: 30.09.2018
 * Time: 15:19
 */

namespace esas\hutkigrosh\view\admin;

use esas\hutkigrosh\view\admin\fields\ConfigField;
use esas\hutkigrosh\view\admin\fields\ConfigFieldCheckbox;
use esas\hutkigrosh\view\admin\fields\ConfigFieldList;
use esas\hutkigrosh\view\admin\fields\ConfigFieldPassword;
use esas\hutkigrosh\view\admin\fields\ConfigFieldStatusList;
use esas\hutkigrosh\view\admin\fields\ConfigFieldTextarea;
use esas\hutkigrosh\view\admin\fields\ListOption;

class ConfigFormOpencart extends ConfigFormHtml
{
    private $orderStatuses;

    /**
     * ConfigFieldsRenderOpencart constructor.
     */
    public function __construct($registry)
    {
        parent::__construct();
        $loader = $registry->get("load");
        $loader->model('localisation/order_status');
        $orderStatuses = $registry->get("model_localisation_order_status")->getOrderStatuses();
        foreach ($orderStatuses as $orderStatus) {
            $this->orderStatuses[] = new ListOption($orderStatus['order_status_id'], $orderStatus['name']);
        }
    }

    private function addValidationError(ConfigField $configField)
    {
        $validationResult = $configField->getValidationResult();
        if ($validationResult != null && !$validationResult->isValid())
            return '<div class="alert alert-danger">' . $validationResult->getErrorTextSimple() . '</div>';
        else
            return "";
    }

    private function addLabel(ConfigField $configField)
    {
        return '<label class="col-sm-2 control-label" for="input-' . $configField->getKey() . '">
                        <span data-toggle="tooltip" title="' . $configField->getDescription() . '">' . $configField->getName() . '</span>
                    </label>';
    }

    function generateTextField(ConfigField $configField)
    {
        return '<div class="form-group ' . ($configField->isRequired() ? 'required' : '') . '">'
            . $this->addValidationError($configField)
            . $this->addLabel($configField)
            . '<div class="col-sm-10">
                        <input type="text" name="' . $configField->getKey() . '" value="' . $configField->getValue() . '"
                                   placeholder="' . $configField->getName() . '"
                                   id="input-' . $configField->getKey() . '" class="form-control">
                    </div>
               </div>';
    }

    function generateextAreaField(ConfigFieldTextarea $configField)
    {
        return '<div class="form-group ' . ($configField->isRequired() ? 'required' : '') . '">'
            . $this->addValidationError($configField)
            . $this->addLabel($configField)
            . '<div class="col-sm-10">
                        <textarea name="' . $configField->getKey() . '"
                                   placeholder="' . $configField->getName() . '" 
                                   rows="' . $configField->getRows() . '"
                                   cols="' . $configField->getCols() . '"  
                                   id="input-' . $configField->getKey() . '" class="form-control">' . $configField->getValue() . '</textarea>
                    </div>
               </div>';
    }

    public function generatePasswordField(ConfigFieldPassword $configField)
    {
        return '<div class="form-group ' . ($configField->isRequired() ? 'required' : '') . '">'
            . $this->addValidationError($configField)
            . $this->addLabel($configField)
            . '<div class="col-sm-10">
                        <input type="password" name="' . $configField->getKey() . '" value="' . $configField->getValue() . '"
                                   placeholder="' . $configField->getName() . '"
                                   id="input-' . $configField->getKey() . '" class="form-control">
                    </div>
               </div>';
    }


    function generateCheckboxField(ConfigFieldCheckbox $configField)
    {
        return '<div class="form-group ' . ($configField->isRequired() ? 'required' : '') . '"> '
            . $this->addValidationError($configField)
            . $this->addLabel($configField)
            . '<div class="col-sm-10">
                        <input type="checkbox" 
                            name="' . $configField->getKey() . '" 
                            value="1"
                            placeholder="' . $configField->getName() . '"
                            id="input-' . $configField->getKey() . '"
                            ' . ($configField->getValue() ? 'checked="checked"' : '') . ' 
                            class="form-control">
                    </div>
               </div>';
    }

    function generateStatusListField(ConfigFieldStatusList $configField)
    {
        $configField->setOptions($this->orderStatuses);
        return $this->generateListField($configField);
    }

    function generateListField(ConfigFieldList $configField)
    {
        return '<div class="form-group">'
            . $this->addValidationError($configField)
            . $this->addLabel($configField)
            . '<div class="col-sm-10">
                        <select class="form-control" 
                            id="input-' . $configField->getKey() . '" 
                            name="' . $configField->getKey() . '">' . $this->createOptions($configField) . '
                        </select>
                    </div>
                </div>';
    }


    function createOptions(ConfigFieldList $configField)
    {
        $ret = "";
        foreach ($configField->getOptions() as $option) {
            if ($option->getValue() == $configField->getValue()) {
                $ret .= '<option value="' . $option->getValue() . '" selected="selected">' . $option->getName() . '</option>';
            } else {
                $ret .= '<option value="' . $option->getValue() . '">' . $option->getName() . '</option>';
            }
        }
        return $ret;
    }
}